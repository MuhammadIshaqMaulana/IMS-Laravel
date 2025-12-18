<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Tambahkan SoftDeletes

class Transaksi extends Model
{
    use HasFactory, SoftDeletes; // Gunakan SoftDeletes

    protected $table = 'transaksis';

    protected $fillable = [
        'item_id',
        'jumlah_produksi',
        'tanggal_produksi',
        'catatan',
    ];

    protected $casts = [
        'tanggal_produksi' => 'datetime',
    ];

    public function itemProduksi(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
