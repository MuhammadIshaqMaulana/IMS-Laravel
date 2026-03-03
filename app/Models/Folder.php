<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Import trait ini
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Folder extends Model
{
    use HasFactory, SoftDeletes; // Tambahkan SoftDeletes di sini

    protected $fillable = ['nama', 'parent_id', 'path', 'folders_count', 'items_count'];

    // Relasi ke folder induk
    public function parent() {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    // Relasi ke sub-folder
    public function children() {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    // Relasi ke item
    public function items() {
        return $this->hasMany(Item::class);
    }



    /**
     * Mengambil silsilah folder untuk breadcrumbs.
     */
    public function getBreadcrumbs() {
        $breadcrumbs = collect([]);
        $folder = $this;
        while ($folder) {
            $breadcrumbs->prepend($folder);
            $folder = $folder->parent;
        }
        return $breadcrumbs;
    }

    /**
     * Update Materialized Path (e.g., "1/5/12")
     */
    public function updatePath() {
        if ($this->parent) {
            $this->path = $this->parent->path . '/' . $this->id;
        } else {
            $this->path = (string) $this->id;
        }
        $this->save();
    }

    public function isDescendantOf($folderId) {
        $ancestorIds = explode('/', $this->path);
        return in_array((string)$folderId, $ancestorIds);
    }

    protected static function booted()
    {
        // 1. Saat Sub-Folder Baru Dibuat
        static::created(function ($folder) {
            if ($folder->parent_id) {
                Folder::where('id', $folder->parent_id)->increment('folders_count');
            }
        });

        // 2. Saat Sub-Folder Dihapus (Soft Delete)
        static::deleted(function ($folder) {
            if ($folder->parent_id) {
                Folder::where('id', $folder->parent_id)
                    ->where('folders_count', '>', 0)
                    ->decrement('folders_count');
            }
        });

        // 3. Saat Sub-Folder Dikembalikan (Restore)
        static::restored(function ($folder) {
            if ($folder->parent_id) {
                Folder::where('id', $folder->parent_id)->increment('folders_count');
            }
        });
    }

}
