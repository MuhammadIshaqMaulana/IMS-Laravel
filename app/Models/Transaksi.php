<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    use HasFactory;

    // Model Transaksi akan merujuk ke tabel 'transaksis'
    protected $table = 'transaksis';

    protected $fillable = [
        'produk_jadi_id',
        'jumlah_produksi',
        'tanggal_produksi',
        'catatan',
    ];

    protected $casts = [
        'tanggal_produksi' => 'datetime',
    ];

    /**
     * Relasi: Transaksi ini dibuat untuk satu Produk Jadi.
     */
    public function produkJadi(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProdukJadi::class, 'produk_jadi_id');
    }
}
