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

    // --- ACCESSORS UNTUK TABEL INDEX ---

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
        if (str_contains($c, 'menambahkan tag')) return 'Update';
        return 'Lainnya';
    }

    public function getPerubahanStokAttribute()
    {
        preg_match("/'([^']+)'/", $this->catatan, $matches);
        return $matches[1] ?? '-';
    }

    public function getTargetCountAttribute()
    {
        if (preg_match('/"([^"]+)"/', $this->catatan, $matches)) return $matches[1];
        if (preg_match('/\|\|(\d+)\|\|/', $this->catatan) || preg_match('/\{(\d+)\}/', $this->catatan)) return 1;
        return '-';
    }

    public function getFolderAsalAttribute()
    {
        if (preg_match('/\[(\d+)\]/', $this->catatan, $matches)) {
            $id = (int)$matches[1];
            if ($id === 0) return 'ROOT';
            $f = \App\Models\Folder::find($id);
            return $f ? $f->nama : "Folder #$id";
        }
        return '-';
    }

    public function getFolderTujuanAttribute()
    {
        if (preg_match('/-(\d+)-/', $this->catatan, $matches)) {
            $id = (int)$matches[1];
            if ($id === 0) return 'ROOT';
            $f = \App\Models\Folder::find($id);
            return $f ? $f->nama : "Folder #$id";
        }
        return '-';
    }

    // --- THE MASTER PARSER (Anti-Collision Logic) ---

    public function getParsedCatatanAttribute()
    {
        // 1. Escape HTML bawaan agar tidak XSS, baru kita suntik HTML kita sendiri
        $text = e($this->catatan);

        // 2. Parse Folder Tujuan: -id- (Lakukan awal karena unik)
        $text = preg_replace_callback('/-(\d+)-/', function($m) {
            $id = (int)$m[1];
            $name = ($id === 0) ? 'ROOT' : (\App\Models\Folder::find($id)->nama ?? "Folder #$id");
            return "<span class='text-success fw-bold'><i class='fas fa-arrow-right mx-1'></i> $name</span>";
        }, $text);

        // 3. Parse Folder Eksekusi/Asal: [id]
        $text = preg_replace_callback('/\[(\d+)\]/', function($m) {
            $id = (int)$m[1];
            $name = ($id === 0) ? 'ROOT' : (\App\Models\Folder::find($id)->nama ?? "Folder #$id");
            return "<span class='badge bg-light text-brown border'>[$name]</span>";
        }, $text);

        // 4. Parse Item: ||id|| (Simbol Baru pengganti <>)
        $text = preg_replace_callback('/\|\|(\d+)\|\|/', function($m) {
            $item = \App\Models\Item::find($m[1]);
            $name = $item ? $item->nama : "Item #{$m[1]}";
            return "<span class='text-primary fw-bold'>$name</span>";
        }, $text);

        // 5. Parse Folder Objek: {id}
        $text = preg_replace_callback('/\{(\d+)\}/', function($m) {
            $f = \App\Models\Folder::find($m[1]);
            $name = $f ? $f->nama : "Folder #{$m[1]}";
            return "<span class='text-warning fw-bold'>$name</span>";
        }, $text);

        // 6. Parse User: (id) - Hanya yang pertama biasanya user
        $text = preg_replace_callback('/\(([\d]+)\)/', function($m) {
            $user = \App\Models\User::find($m[1]);
            return "<strong class='text-dark'>" . ($user ? $user->name : "User #{$m[1]}") . "</strong>";
        }, $text, 1);

        // 7. Simbol Value Lama: `val` (Coret)
        $text = preg_replace('/`([^`]+)`/', "<span class='text-decoration-line-through text-muted small'>$1</span>", $text);

        // 8. Simbol Value Target: ~val~ (Bold Italic)
        $text = preg_replace('/~([^~]+)~/', "<span class='text-dark fw-bold italic'>\"$1\"</span>", $text);

        // 9. Simbol Qty: 'val'
        $text = preg_replace_callback("/'([^']+)'/", function($m) {
            $color = str_contains($m[1], '-') ? 'text-danger' : 'text-success';
            return "<span class='$color fw-bold'>$m[1]</span>";
        }, $text);

        // 10. Simbol Bulk Count: "val"
        $text = preg_replace('/"([^"]+)"/', "<span class='badge bg-warning text-dark'>$1 item</span>", $text);

        return $text;
    }

    public function itemProduksi() { return $this->belongsTo(Item::class, 'item_id'); }
}
