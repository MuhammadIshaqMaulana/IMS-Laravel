<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DaftarBahan extends Model
{
    use HasFactory;

    // Nama tabel pivot
    protected $table = 'daftar_bahan';

    protected $fillable = [
        'produk_jadi_id',
        'bahan_mentah_id',
        'jumlah_digunakan',
    ];

    /**
     * Relasi: Bahan ini adalah bagian dari satu Produk Jadi.
     */
    public function produkJadi(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProdukJadi::class, 'produk_jadi_id');
    }

    /**
     * Relasi: Bahan ini merujuk ke satu item Bahan Mentah.
     */
    public function bahanMentah(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BahanMentah::class, 'bahan_mentah_id');
    }
}
