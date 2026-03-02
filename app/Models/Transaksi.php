<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaksi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaksis';
    protected $fillable = ['user_id', 'item_id', 'folder_id', 'catatan'];

    // Relasi ke User (Pelaku)
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getTipeAksiAttribute()
    {
        $c = $this->catatan;
        if (str_contains($c, 'memindahkan')) return 'Move';
        if (str_contains($c, 'menghapus')) return 'Delete';
        if (str_contains($c, 'membuat')) return 'Create';
        if (str_contains($c, 'clone')) return 'Create';
        if (str_contains($c, 'mengimport')) return 'Import';
        if (str_contains($c, 'update stok')) return 'Update Stok';
        if (str_contains($c, 'update material')) return 'Update BOM';
        if (str_contains($c, 'update')) return 'Update';
        return 'Lainnya';
    }

    public function getPerubahanStokAttribute()
    {
        // Mencari nilai di dalam ''
        preg_match("/'([^']+)'/", $this->catatan, $matches);
        return $matches[1] ?? '-';
    }

    public function getTargetCountAttribute()
    {
        // 1. Jika ada simbol "" (Bulk), ambil angkanya
        if (preg_match('/"([^"]+)"/', $this->catatan, $matches)) return $matches[1];

        // 2. Jika ada simbol `` (Single), otomatis targetnya 1
        if (preg_match('/`([^`]+)`/', $this->catatan)) return 1;

        return '-';
    }

    // Ambil Nama Folder Asal/Eksekusi dari log [Nama Folder]
    public function getFolderAsalAttribute()
    {
        if (preg_match('/\[([^\]]+)\]/', $this->catatan, $matches)) {
            return $matches[1];
        }
        return '-';
    }

    // Ambil Nama Folder Tujuan dari log -Nama Folder-
    public function getFolderTujuanAttribute()
    {
        if (preg_match('/-([^-]+)-/', $this->catatan, $matches)) {
            return $matches[1];
        }
        return '-';
    }

    public function getParsedCatatanAttribute()
    {
        // Murni teks tanpa tag HTML agar tidak pecah di PDF/View
        // Cukup hapus simbol-simbol penanda agar enak dibaca
        return str_replace(['`', '~', '"'], '', $this->catatan);
    }

    public function itemProduksi() {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
