<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateOldDataSeeder extends Seeder
{
    /**
     * Menjalankan data migrasi dari bahan_mentahs dan produk_jadis ke tabel items.
     */
    public function run(): void
    {
        // Cek apakah tabel lama masih ada untuk menghindari error
        if (!Schema::hasTable('bahan_mentahs') && !Schema::hasTable('produk_jadis')) {
            $this->command->warn('Tabel lama (bahan_mentahs & produk_jadis) tidak ditemukan. Melewati migrasi data.');
            return;
        }

        $this->command->info('Memulai migrasi data Bahan Mentah dan Produk Jadi ke tabel items...');

        // 1. Migrasi Data Bahan Mentah
        if (Schema::hasTable('bahan_mentahs')) {
            $bahanMentahs = DB::table('bahan_mentahs')->get();

            foreach ($bahanMentahs as $bahan) {
                DB::table('items')->insert([
                    'nama' => $bahan->nama,
                    'satuan' => $bahan->satuan,
                    'stok_saat_ini' => $bahan->stok_saat_ini,
                    'stok_minimum' => $bahan->stok_minimum,
                    'pemasok' => $bahan->pemasok,
                    'jenis_item' => 'bahan_mentah', // Klasifikasi
                    'note' => 'Migrasi dari Bahan Mentah ID: ' . $bahan->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->command->info('Sukses memindahkan ' . $bahanMentahs->count() . ' data Bahan Mentah.');
        }

        // 2. Migrasi Data Produk Jadi
        if (Schema::hasTable('produk_jadis')) {
            $produkJadis = DB::table('produk_jadis')->get();

            foreach ($produkJadis as $produk) {
                DB::table('items')->insert([
                    'nama' => $produk->nama,
                    'sku' => $produk->sku,
                    'stok_saat_ini' => $produk->stok_di_tangan, // Stok lama dipetakan ke stok_saat_ini
                    'harga_jual' => $produk->harga_jual,
                    'jenis_item' => 'produk_jadi', // Klasifikasi
                    'note' => 'Migrasi dari Produk Jadi ID: ' . $produk->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                    // Catatan: Satuan, Stok Minimum, dan Pemasok mungkin kosong karena tidak ada di tabel lama
                ]);
            }
            $this->command->info('Sukses memindahkan ' . $produkJadis->count() . ' data Produk Jadi.');
        }

        $this->command->info('Migrasi data selesai!');
    }
}
