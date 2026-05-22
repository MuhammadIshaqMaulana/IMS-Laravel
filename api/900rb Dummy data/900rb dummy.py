import csv
import random
import string

def generate_random_string(length=8):
    return ''.join(random.choices(string.ascii_uppercase + string.digits, k=length))

def generate_data(total_rows=900000):
    filename = 'dummy_inventory_900k.csv'
    header = ['nomor', 'nama', 'satuan', 'stok_saat_ini', 'stok_minimum', 'harga_jual', 'harga_beli', 'note', 'materials', 'tags']
    
    satuan_list = ['pcs', 'kg', 'liter', 'box', 'gram', 'meter']
    tags_pool = ['elektronik', 'bahan-baku', 'import', 'lokal', 'fast-moving', 'promo', 'premium']
    
    print(f"Memulai pembuatan {total_rows} data...")

    with open(filename, mode='w', newline='', encoding='utf-8') as file:
        writer = csv.writer(file)
        writer.writerow(header)

        for i in range(1, total_rows + 1):
            # Logika Nama & Nomor
            nama = f"Produk Dummy {i} {generate_random_string(4)}"
            nomor = i
            satuan = random.choice(satuan_list)
            
            # Harga & Stok
            harga_beli = random.randint(1000, 100000)
            harga_jual = harga_beli + random.randint(500, 50000)
            stok_awal = random.randint(0, 1000)
            stok_min = random.randint(5, 50)
            
            # Gimmick Tags
            tags = ",".join(random.sample(tags_pool, k=random.randint(1, 3)))
            
            # Logika BOM (Kira-kira 5% data adalah BOM)
            materials = ""
            if i > 100 and random.random() < 0.05:
                # Ambil 2-3 nomor secara acak dari data yang sudah terbuat sebelumnya (mencegah loop)
                mat_list = []
                for _ in range(random.randint(2, 3)):
                    ref_num = random.randint(1, i - 1)
                    qty = random.randint(1, 10)
                    mat_list.append(f"{ref_num}({qty})")
                materials = ",".join(mat_list)
                stok_awal = 0 # Sesuai rule: jika BOM, stok awal diabaikan/0

            writer.writerow([
                nomor, 
                nama, 
                satuan, 
                stok_awal, 
                stok_min, 
                harga_jual, 
                harga_beli, 
                f"Catatan dummy untuk produk ke-{i}", 
                materials, 
                tags
            ])
            
            if i % 100000 == 0:
                print(f"Proses: {i} data selesai...")

    print(f"Selesai! File tersimpan sebagai: {filename}")

if __name__ == "__main__":
    generate_data(900000)