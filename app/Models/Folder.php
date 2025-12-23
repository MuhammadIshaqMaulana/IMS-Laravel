<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = ['nama', 'parent_id', 'path'];

    public function parent() { return $this->belongsTo(Folder::class, 'parent_id'); }
    public function children() { return $this->hasMany(Folder::class, 'parent_id'); }
    public function items() { return $this->hasMany(Item::class, 'folder_id'); }

    /**
     * Mengambil urutan folder dari Root sampai folder ini untuk Breadcrumb.
     */
    public function getBreadcrumbs()
    {
        $breadcrumbs = collect([]);
        $current = $this;

        while ($current) {
            $breadcrumbs->prepend($current);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    public function updatePath(): void
    {
        if ($this->parent_id) {
            $parentFolder = self::find($this->parent_id);
            $this->path = ($parentFolder->path ?? '/') . $this->id . '/';
        } else {
            $this->path = '/' . $this->id . '/';
        }
        $this->saveQuietly();
    }

    public function isDescendantOf($id): bool
    {
        if (!$this->path) return false;
        return str_contains($this->path, "/{$id}/");
    }
}
