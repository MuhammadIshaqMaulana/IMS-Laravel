<?php

namespace Database\Factories;

use App\Models\BahanMentah;
use App\Models\DaftarBahan;
use App\Models\ProdukJadi;
use Illuminate\Database\Eloquent\Factories\Factory;

class DaftarBahanFactory extends Factory
{
    protected $model = DaftarBahan::class;

    /**
     * Definisikan state model default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Pastikan Anda memanggil factory ini setelah BahanMentah dan ProdukJadi dibuat.
        return [
            // Pilih ID Produk Jadi secara acak
            'produk_jadi_id' => ProdukJadi::inRandomOrder()->first()->id,

            // Pilih ID Bahan Mentah secara acak
            'bahan_mentah_id' => BahanMentah::inRandomOrder()->first()->id,

            // Jumlah yang digunakan per 1 unit produk jadi
            'jumlah_digunakan' => $this->faker->randomFloat(3, 0.05, 1.5),
        ];
    }

    /**
     * Fungsi untuk memastikan kombinasi produk dan bahan unik
     * Agar tidak terjadi duplikasi resep, kita akan menangani ini di Seeder.
     * Factory ini tetap sederhana.
     */
}
