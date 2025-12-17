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
        'pemasok',
        'note',
        'tags',
        'custom_fields',
        'image_link',
        'materials',
        'folder_id',
        'parent_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'custom_fields' => 'array',
        'materials' => 'array',
        'stok_saat_ini' => 'float',
        'stok_minimum' => 'float',
        'harga_jual' => 'integer',
    ];

    // --- ACCESOR BARU UNTUK STOK TERHITUNG (CALCULATED STOCK) ---
    protected $appends = ['calculated_stock']; // Tambahkan accessor ke array JSON

    /**
     * Accessor untuk mendapatkan Stok Terhitung (Kapasitas Produksi Maksimum).
     * Jika item adalah BOM (memiliki materials), stok dihitung dari material.
     * Jika item bukan BOM, stoknya adalah stok_saat_ini.
     */
    public function getCalculatedStockAttribute(): float
    {
        // Jika item ini BUKAN BOM/Kit
        if (empty($this->materials)) {
            return (float) $this->stok_saat_ini;
        }

        // --- Logika Perhitungan Stok BOM ---

        $minCapacity = INF; // Mulai dengan kapasitas tak terhingga

        // Loop melalui setiap material penyusun BOM
        foreach ($this->materials as $materialData) {
            $materialId = $materialData['item_id'];
            $qtyRequired = (float) $materialData['qty'];

            // Ambil data stok material dari database
            // Kita gunakan find() untuk menghindari relasi rekursif yang kompleks di sini
            $materialItem = Item::find($materialId);

            // Jika material tidak ditemukan atau jumlah yang dibutuhkan 0, lewati
            if (!$materialItem || $qtyRequired <= 0) {
                continue;
            }

            // Kapasitas yang dapat dibuat berdasarkan material ini
            $capacity = floor($materialItem->stok_saat_ini / $qtyRequired);

            // Batasan (Bottleneck): Ambil kapasitas terendah
            if ($capacity < $minCapacity) {
                $minCapacity = $capacity;
            }
        }

        // Jika tidak ada material atau hitungan tidak valid, stok dianggap 0
        return $minCapacity === INF ? 0.0 : (float) $minCapacity;
    }

    // --- RELASI SISA ---

    public function folder()
    {
        return $this->belongsTo(Item::class, 'folder_id');
    }

    public function parent()
    {
        return $this->belongsTo(Item::class, 'parent_id');
    }

    public function transaksis()
    {
        // Menggunakan produk_jadi_id sebagai kolom FK karena belum direname
        return $this->hasMany(Transaksi::class, 'produk_jadi_id');
    }
}
