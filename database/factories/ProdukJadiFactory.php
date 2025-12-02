<?php

namespace Database\Factories;

use App\Models\ProdukJadi;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProdukJadiFactory extends Factory
{
    protected $model = ProdukJadi::class;

    /**
     * Definisikan state model default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Daftar produk jadi khas toko roti
        $produkNames = [
            'Roti Tawar Premium',
            'Donat Gula Halus',
            'Kue Cokelat Fudge',
            'Croissant Mentega',
            'Roti Sobek Keju',
            'Bagel Gandum',
        ];

        // Ambil nama produk dan buat SKU dari nama tersebut
        $nama = $this->faker->unique()->randomElement($produkNames);
        $sku = 'PRD-' . strtoupper(substr($nama, 0, 3)) . '-' . $this->faker->unique()->randomNumber(3);

        return [
            'nama' => $nama,
            'sku' => $sku,
            'harga_jual' => $this->faker->randomFloat(2, 10000, 50000), // Harga jual realistis
            'stok_di_tangan' => $this->faker->numberBetween(10, 150), // Stok awal
            'aktif' => $this->faker->boolean(90), // 90% kemungkinan aktif
        ];
    }
}
