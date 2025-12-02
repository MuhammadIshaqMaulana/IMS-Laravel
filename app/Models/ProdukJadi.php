<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdukJadi extends Model
{
    use HasFactory;

    protected $table = 'produk_jadi';

    protected $fillable = [
        'nama',
        'sku',
        'harga_jual',
        'stok_di_tangan',
        'aktif',
    ];

    /**
     * Relasi: Produk Jadi ini memiliki banyak bahan (DaftarBahan) dalam resepnya.
     */
    public function resep(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DaftarBahan::class, 'produk_jadi_id');
    }
}
