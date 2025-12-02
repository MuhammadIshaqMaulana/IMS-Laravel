<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BahanMentah extends Model
{
    use HasFactory;

    // Definisikan nama tabel secara eksplisit (jika tidak mengikuti konvensi jamak bahasa Inggris)
    protected $table = 'bahan_mentah';

    // Kolom yang aman untuk mass assignment
    protected $fillable = [
        'nama',
        'satuan',
        'stok_saat_ini',
        'stok_minimum',
        'pemasok',
    ];

    /**
     * Relasi: Bahan Mentah ini digunakan di banyak resep (DaftarBahan).
     */
    public function resep(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DaftarBahan::class, 'bahan_mentah_id');
    }
}
