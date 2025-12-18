<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama', 'sku', 'satuan', 'stok_saat_ini', 'stok_minimum',
        'harga_jual', 'pemasok', 'note', 'tags', 'custom_fields',
        'image_link', 'materials', 'folder_id', 'parent_id',
    ];

    protected $casts = [
        'tags' => 'array',
        'custom_fields' => 'array',
        'materials' => 'array',
        'stok_saat_ini' => 'float',
        'stok_minimum' => 'float',
        'harga_jual' => 'integer',
    ];

    protected $appends = ['calculated_stock', 'is_folder', 'is_bom'];

    // --- ACCESSORS ---

    public function getIsFolderAttribute(): bool {
        return is_array($this->tags) && in_array('folder', $this->tags);
    }

    public function getIsBomAttribute(): bool {
        return !empty($this->materials);
    }

    public function getCalculatedStockAttribute(): float {
        if (!$this->is_bom) return (float) $this->stok_saat_ini;
        $minCapacity = INF;
        foreach ($this->materials as $mat) {
            $mItem = Item::find($mat['item_id']);
            if (!$mItem || $mat['qty'] <= 0) continue;
            $cap = floor($mItem->stok_saat_ini / $mat['qty']);
            if ($cap < $minCapacity) $minCapacity = $cap;
        }
        return $minCapacity === INF ? 0.0 : (float) $minCapacity;
    }

    /**
     * Helper untuk mengambil gambar terakhir dari item di dalam folder ini
     */
    public function getLastItemImage() {
        $lastItem = $this->itemsInFolder()->whereNotNull('image_link')->latest()->first();
        return $lastItem ? $lastItem->image_link : null;
    }

    // --- RELATIONS ---
    public function folder() { return $this->belongsTo(Item::class, 'folder_id'); }
    public function itemsInFolder() { return $this->hasMany(Item::class, 'folder_id'); }
    public function parent() { return $this->belongsTo(Item::class, 'parent_id'); }
    public function variants() { return $this->hasMany(Item::class, 'parent_id'); }
    public function transaksis() { return $this->hasMany(Transaksi::class, 'item_id'); }
}
