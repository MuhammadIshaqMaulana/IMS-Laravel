<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Import trait ini
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Folder extends Model
{
    use HasFactory, SoftDeletes; // Tambahkan SoftDeletes di sini

    protected $fillable = ['nama', 'parent_id', 'path'];

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
        static::created(function ($folder) {
            if ($folder->parent_id) {
                \App\Models\Folder::where('id', $folder->parent_id)->increment('children_count');
            }
        });

        static::deleted(function ($folder) {
            if ($folder->parent_id) {
                \App\Models\Folder::where('id', $folder->parent_id)
                    ->where('children_count', '>', 0)
                    ->decrement('children_count');
            }
        });
    }

}
