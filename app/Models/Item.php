<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'sku',
        'satuan',
        'stok_saat_ini',
        'stok_minimum',
        'harga_jual',
        'jenis_item',
        'pemasok',
        'note',
        'tags',
        'custom_fields',
        'image_link'
    ];

    protected $casts = [
        'tags' => 'array',
        'custom_fields' => 'array',
        'stok_saat_ini' => 'float',
        'stok_minimum' => 'float',
        'harga_jual' => 'float',
    ];

    // Relasi yang diperbarui (nantinya kita akan mengubah Transaksi dan DaftarBahan)
    public function resepItem()
    {
        return $this->hasMany(DaftarBahan::class, 'produk_jadi_id');
    }
}
