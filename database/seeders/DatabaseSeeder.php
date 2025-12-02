<?php

namespace Database\Seeders;

use App\Models\BahanMentah;
use App\Models\DaftarBahan;
use App\Models\ProdukJadi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Jalankan seed aplikasi database.
     */
    public function run(): void
    {
        // Nonaktifkan Foreign Key Check sementara untuk membersihkan tabel
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        BahanMentah::truncate();
        ProdukJadi::truncate();
        DaftarBahan::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. Buat Bahan Mentah (Sekitar 10 item)
        // Kita tidak perlu menggunakan truncate lagi di sini karena sudah di atas.
        BahanMentah::factory()->count(10)->create();

        // 2. Buat Produk Jadi (Sekitar 6 item)
        ProdukJadi::factory()->count(6)->create();

        // 3. Buat Daftar Bahan (Resep)
        $produkJadi = ProdukJadi::all();
        $bahanMentahIds = BahanMentah::pluck('id');

        foreach ($produkJadi as $produk) {
            // Tentukan jumlah bahan acak (antara 4 sampai 6)
            $jumlahBahan = rand(4, 6);

            // Ambil ID bahan mentah unik secara acak
            $bahanUntukResep = $bahanMentahIds->shuffle()->take($jumlahBahan);

            foreach ($bahanUntukResep as $bahanId) {
                // *** PERBAIKAN LOGIKA FACTORY ***
                // Kita buat atribut dummy lengkap dari factory
                $attributes = DaftarBahan::factory()->make()->toArray();

                // Kemudian kita timpa ID produk dan bahan, serta menambahkan jumlah_digunakan
                DaftarBahan::create([
                    'produk_jadi_id' => $produk->id,
                    'bahan_mentah_id' => $bahanId,
                    'jumlah_digunakan' => $attributes['jumlah_digunakan'] // Mengambil nilai dari factory
                ]);
            }
        }

        $this->command->info('Data dummy IMS Toko Roti berhasil diisi!');
    }
}
