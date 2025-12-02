<?php

namespace Database\Factories;

use App\Models\BahanMentah;
use Illuminate\Database\Eloquent\Factories\Factory;

class BahanMentahFactory extends Factory
{
    /**
     * Nama model yang sesuai dengan factory ini.
     * @var string
     */
    protected $model = BahanMentah::class;

    /**
     * Definisikan state model default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Daftar nama bahan yang umum di toko roti
        $bahanNames = [
            'Tepung Terigu Protein Tinggi',
            'Gula Pasir',
            'Ragi Instan',
            'Telur Ayam',
            'Margarin',
            'Garam Halus',
            'Susu Bubuk',
            'Cokelat Batang',
            'Keju Cheddar',
            'Pewarna Makanan Hijau',
        ];

        // Pastikan nama bahan unik dan ambil satu
        $nama = $this->faker->unique()->randomElement($bahanNames);

        // Tentukan satuan dan stok secara acak
        $satuan = $this->faker->randomElement(['kg', 'gram', 'butir', 'liter']);
        $stok_saat_ini = $this->faker->randomFloat(2, 5, 200); // Stok antara 5 hingga 200 unit

        return [
            'nama' => $nama,
            'satuan' => $satuan,
            'stok_saat_ini' => $stok_saat_ini,
            'stok_minimum' => $this->faker->randomFloat(2, 1, 10), // Stok minimum lebih kecil
            'pemasok' => $this->faker->company(),
        ];
    }
}
