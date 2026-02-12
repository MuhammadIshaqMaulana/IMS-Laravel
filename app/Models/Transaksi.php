<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaksi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaksis';
    protected $fillable = ['item_id', 'folder_id', 'catatan'];

    // --- LOGIC PARSER UNTUK VIEW (Sortly Style) ---

    public function getTipeAksiAttribute()
    {
        $c = $this->catatan;
        if (str_contains($c, 'memindahkan')) return 'Pindah';
        if (str_contains($c, 'mengimport')) return 'Import';
        if (str_contains($c, 'Dibuat')) return 'Tambah';
        if (str_contains($c, 'update stok')) return 'Update Stok';
        if (str_contains($c, 'Update:')) return 'Edit Data';
        if (str_contains($c, 'Menghapus')) return 'Hapus';
        return 'Lainnya';
    }

    public function getPerubahanStokAttribute()
    {
        // Cari angka di dalam tanda kutip tunggal, misal '+5' atau '-10'
        preg_match("/'([^']+)'/", $this->catatan, $matches);
        return $matches[1] ?? '-';
    }

    public function getTargetCountAttribute()
    {
        // Cari pola "X item" untuk bulk
        if (preg_match('/(\d+) item/', $this->catatan, $matches)) return $matches[1];
        // Jika single item (punya item_id), berarti 1
        return $this->item_id ? 1 : '-';
    }

    public function getParsedUserAttribute()
    {
        preg_match('/\(([\d]+)\)/', $this->catatan, $m);
        if (isset($m[1])) {
            $user = \App\Models\User::find($m[1]);
            return $user ? $user->name : "User #{$m[1]}";
        }
        return 'System';
    }

    public function getFolderAsalAttribute()
    {
        preg_match_all('/\[([\d]+)\]/', $this->catatan, $m);
        if (isset($m[1][0])) {
            $f = \App\Models\Folder::find($m[1][0]);
            return $f ? $f->nama : 'Root';
        }
        return '-';
    }

    public function getFolderTujuanAttribute()
    {
        preg_match_all('/\[([\d]+)\]/', $this->catatan, $m);
        // Folder tujuan biasanya ID folder kedua dalam string pindah
        if (isset($m[1][1])) {
            $f = \App\Models\Folder::find($m[1][1]);
            return $f ? $f->nama : 'Root';
        }
        return '-';
    }

    public function getParsedCatatanAttribute()
    {
        $text = $this->catatan;
        // Parse User (..)
        $text = preg_replace_callback('/\(([\d]+)\)/', function($m) {
            $user = \App\Models\User::find($m[1]);
            return $user ? "<strong class='text-dark'>{$user->name}</strong>" : "User #{$m[1]}";
        }, $text, 1);
        // Parse Item (..)
        $text = preg_replace_callback('/\(([\d]+)\)/', function($m) {
            $item = \App\Models\Item::find($m[1]);
            return $item ? "<span class='text-primary fw-bold'>{$item->nama}</span>" : "Item #{$m[1]}";
        }, $text);
        // Parse Folder [..]
        $text = preg_replace_callback('/\[([\d]+)\]/', function($m) {
            $folder = \App\Models\Folder::find($m[1]);
            return $folder ? "<span class='text-warning'>[{$folder->nama}]</span>" : "[Root]";
        }, $text);
        return $text;
    }

    public function itemProduksi()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
