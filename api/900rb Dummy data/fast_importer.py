import pandas as pd
import mysql.connector
from mysql.connector import Error
import json
import os
import sys

# --- KONFIGURASI DATABASE ---
DB_CONFIG = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '',
    'database': 'IMS_Roti' 
}

REQUIRED_HEADERS = ['nomor', 'nama', 'satuan', 'stok_saat_ini', 'stok_minimum', 'harga_jual', 'harga_beli', 'note', 'materials', 'tags']

def fast_import(file_path, parent_folder_id=None):
    try:
        # 1. LOAD DATA (Super Fast with Pandas)
        print(f"[*] Membaca file {file_path}...")
        df = pd.read_csv(file_path)
        df.columns = df.columns.str.strip()
        
        # 2. VALIDASI HEADER
        if list(df.columns) != REQUIRED_HEADERS:
            print("[!] Error: Table header tidak sesuai!")
            print(f"Dibutuhkan: {REQUIRED_HEADERS}")
            return

        # 3. PRE-VALIDATION (FAIL FAST)
        print("[*] Menjalankan validasi integritas data...")
        
        # Cek Duplikat Nomor di CSV
        if df['nomor'].duplicated().any():
            print("[!] Error: Terdapat nomor duplikat di dalam file CSV.")
            return

        # Cek Duplikat Nama di CSV
        if df['nama'].duplicated().any():
            print("[!] Error: Terdapat nama produk duplikat di dalam file CSV.")
            return

        # Identifikasi siapa saja yang BOM di CSV
        bom_indices = df[df['materials'].notna() & (df['materials'] != "")].index
        bom_nomors = set(df.loc[bom_indices, 'nomor'].astype(str))

        # Validasi Aturan BOM
        for idx, row in df.iterrows():
            if pd.notna(row['materials']) and row['materials'] != "":
                m_parts = str(row['materials']).split(',')
                seen_materials = set()
                curr_nomor = str(row['nomor'])

                for part in m_parts:
                    # Parsing nomor(qty) atau nomor
                    m_num = part.split('(')[0].strip()
                    
                    if m_num == curr_nomor:
                        print(f"[!] Error: Baris {idx+2} mereferensikan diri sendiri.")
                        return
                    if m_num in bom_nomors:
                        print(f"[!] Error: Baris {idx+2} menggunakan BOM lain (#{m_num}) sebagai material.")
                        return
                    if m_num in seen_materials:
                        print(f"[!] Error: Baris {idx+2} memiliki material duplikat (#{m_num}).")
                        return
                    seen_materials.add(m_num)

        # 4. KONEKSI DATABASE & TRANSACTION
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        conn.autocommit = False # Mulai Transaksi

        # Cek Duplikat Nama di Database
        print("[*] Sinkronisasi nama dengan database...")
        cursor.execute("SELECT nama FROM items WHERE deleted_at IS NULL")
        existing_names = {r[0] for r in cursor.fetchall()}
        
        intersect = set(df['nama']).intersection(existing_names)
        if intersect:
            print(f"[!] Error: {len(intersect)} Nama sudah ada di database (Contoh: {list(intersect)[0]})")
            conn.close()
            return

        # 5. LOGIKA NAMA FOLDER UNIK
        base_folder_name = os.path.splitext(os.path.basename(file_path))[0]
        folder_name = base_folder_name
        counter = 1
        while True:
            cursor.execute("SELECT id FROM folders WHERE nama = %s AND parent_id <=> %s AND deleted_at IS NULL", 
                           (folder_name, parent_folder_id))
            if not cursor.fetchone():
                break
            counter += 1
            folder_name = f"{base_folder_name} ({counter})"

        # Buat Folder
        cursor.execute("INSERT INTO folders (nama, parent_id, created_at, updated_at) VALUES (%s, %s, NOW(), NOW())", 
                       (folder_name, parent_folder_id))
        new_folder_id = cursor.lastrowid
        # Update Path (Sederhana, nanti Laravel bisa re-sync)
        cursor.execute("UPDATE folders SET path = %s WHERE id = %s", (str(new_folder_id), new_folder_id))

        # 6. PASS 1: BATCH INSERT ITEMS
        print(f"[*] Memulai Batch Insert 900rb data ke folder '{folder_name}'...")
        insert_query = """
            INSERT INTO items (nama, sku, satuan, stok_saat_ini, stok_minimum, harga_jual, harga_beli, note, tags, folder_id, created_at, updated_at)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW(), NOW())
        """
        
        items_to_insert = []
        temp_nomor_to_id = {} # Untuk mapping ID database nantinya

        for idx, row in df.iterrows():
            is_bom = pd.notna(row['materials']) and row['materials'] != ""
            sku = f"AUTO-{idx}-{new_folder_id}" # Placeholder SKU, bisa diganti logic lain
            
            # Format Tags ke JSON string jika ada
            tags_json = json.dumps(str(row['tags']).split(',')) if pd.notna(row['tags']) else None
            
            items_to_insert.append((
                row['nama'], sku, row['satuan'], 
                0 if is_bom else row['stok_saat_ini'],
                row['stok_minimum'], row['harga_jual'], row['harga_beli'],
                row['note'], tags_json, new_folder_id
            ))

        # Gunakan executemany untuk kecepatan tinggi
        batch_size = 10000
        for i in range(0, len(items_to_insert), batch_size):
            cursor.executemany(insert_query, items_to_insert[i:i+batch_size])
            print(f"    [>] Berhasil memasukkan {i + len(items_to_insert[i:i+batch_size])} baris...")

        # Ambil semua ID yang baru dibuat untuk mapping BOM
        print("[*] Membuat mapping ID untuk BOM...")
        cursor.execute("SELECT id, nama FROM items WHERE folder_id = %s", (new_folder_id,))
        db_items = cursor.fetchall()
        name_to_real_id = {name: id for id, name in db_items}
        
        # Buat mapping dari Nomor CSV ke ID Database
        for idx, row in df.iterrows():
            temp_nomor_to_id[str(row['nomor'])] = name_to_real_id[row['nama']]

        # 7. PASS 2: UPDATE BOM MATERIALS
        print("[*] Menghubungkan relasi BOM...")
        update_materials = []
        for idx, row in df.iterrows():
            if pd.notna(row['materials']) and row['materials'] != "":
                m_parts = str(row['materials']).split(',')
                final_materials = []
                for part in m_parts:
                    # Parse nomor(qty)
                    if '(' in part:
                        m_num = part.split('(')[0].strip()
                        m_qty = part.split('(')[1].replace(')', '').strip()
                    else:
                        m_num = part.strip()
                        m_qty = 1.0
                    
                    if m_num in temp_nomor_to_id:
                        final_materials.append({
                            'item_id': temp_nomor_to_id[m_num],
                            'qty': float(m_qty)
                        })
                
                if final_materials:
                    update_materials.append((json.dumps(final_materials), temp_nomor_to_id[str(row['nomor'])]))

        if update_materials:
            cursor.executemany("UPDATE items SET materials = %s WHERE id = %s", update_materials)

        conn.commit()
        print(f"[SUCCESS] 900.000 Data berhasil diimport ke folder '{folder_name}'!")

    except Error as e:
        print(f"[!] Database Error: {e}")
        if 'conn' in locals() and conn.is_connected():
            conn.rollback()
    except Exception as e:
        print(f"[!] General Error: {e}")
        if 'conn' in locals() and conn.is_connected():
            conn.rollback()
    finally:
        if 'conn' in locals() and conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python fast_importer.py <path_ke_file_csv>")
    else:
        fast_import(sys.argv[1])