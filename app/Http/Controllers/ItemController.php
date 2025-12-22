<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemController extends Controller
{
    /**
     * Menampilkan daftar item dengan sistem navigasi folder.
     */
    public function index(Request $request)
    {
        $folderId = $request->query('folder_id');
        $query = Item::orderBy('nama');

        if ($folderId) {
            $query->where('folder_id', $folderId);
            $currentFolder = Item::findOrFail($folderId);
        } else {
            // Tampilan Root: tampilkan item yang tidak punya folder dan bukan varian anak
            $query->whereNull('folder_id')->whereNull('parent_id');
            $currentFolder = null;
        }

        $items = $query->paginate(15);
        $allFolders = Item::where('tags', 'like', '%"folder"%')->orderBy('nama')->get();

        return view('item.index', compact('items', 'currentFolder', 'allFolders'));
    }

    /**
     * Form Create.
     */
    public function create()
    {
        $allMaterials = Item::whereNull('materials')->where('tags', 'not like', '%"folder"%')->orderBy('nama')->get();
        $allFolders = Item::where('tags', 'like', '%"folder"%')->orderBy('nama')->get();
        return view('item.create', compact('allMaterials', 'allFolders'));
    }

    /**
     * Simpan Universal dengan Folder Guard & Uniqueness.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:100',
            'type' => 'required|in:item,bom,folder',
            'folder_id' => 'nullable|exists:items,id',
            'stok_saat_ini' => 'nullable|numeric',
        ]);

        // PROTEKSI 1: Nama unik di level folder yang sama
        $exists = Item::where('nama', $request->nama)
            ->where('folder_id', $request->folder_id)
            ->exists();

        if ($exists) {
            return redirect()->back()->withInput()->with('error', "Gagal: Nama '{$request->nama}' sudah digunakan di lokasi ini.");
        }

        DB::beginTransaction();
        try {
            $tagsArray = array_map('trim', explode(',', $request->tags_input ?? ''));
            if ($request->type === 'folder') $tagsArray[] = 'folder';
            $tags = array_values(array_filter(array_unique($tagsArray)));

            $dimensions = json_decode($request->variant_dimensions, true) ?: [];

            if (empty($dimensions)) {
                $item = Item::create([
                    'nama' => $request->nama,
                    'sku' => ($request->type === 'folder') ? null : $this->generateUniqueSku($request->nama),
                    'satuan' => $request->satuan ?? 'pcs',
                    'stok_saat_ini' => $request->stok_saat_ini ?? 0,
                    'stok_minimum' => $request->stok_minimum ?? 0,
                    'harga_jual' => floor($request->harga_jual ?? 0),
                    'folder_id' => $request->folder_id,
                    'materials' => ($request->type === 'bom') ? json_decode($request->materials_data, true) : null,
                    'tags' => $tags,
                    'note' => $request->note,
                ]);

                $item->updatePath();

                if ($request->type !== 'folder' && $request->stok_saat_ini > 0) {
                    $this->logActivity($item->id, $request->stok_saat_ini, 'Saldo awal item baru');
                }
            } else {
                $parent = Item::create([
                    'nama' => $request->nama,
                    'sku' => null,
                    'satuan' => $request->satuan ?? 'pcs',
                    'stok_saat_ini' => 0,
                    'harga_jual' => floor($request->harga_jual ?? 0),
                    'folder_id' => $request->folder_id,
                    'tags' => $tags,
                    'note' => $request->note,
                ]);
                $parent->updatePath();
                $this->createItemVariants($parent, $dimensions);
            }

            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $request->folder_id])->with('success', 'Data berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Detail Item.
     */
    public function show(Item $item)
    {
        $history = $item->transaksis()->orderBy('tanggal_produksi', 'desc')->get();
        $allFolders = Item::where('tags', 'like', '%"folder"%')->where('id', '!=', $item->id)->get();
        return view('item.show', compact('item', 'history', 'allFolders'));
    }

    /**
     * Form Edit.
     */
    public function edit(Item $item)
    {
        $allFolders = Item::where('tags', 'like', '%"folder"%')->where('id', '!=', $item->id)->get();
        return view('item.edit', compact('item', 'allFolders'));
    }

    /**
     * Update dengan Logika Path & History.
     */
    public function update(Request $request, Item $item)
    {
        $request->validate([
            'nama' => 'required|string|max:100',
            'stok_saat_ini' => 'nullable|numeric',
        ]);

        // PROTEKSI 1: Nama unik di level folder yang sama (abaikan diri sendiri)
        $exists = Item::where('nama', $request->nama)
            ->where('folder_id', $request->folder_id)
            ->where('id', '!=', $item->id)
            ->exists();

        if ($exists) {
            return redirect()->back()->withInput()->with('error', "Gagal: Nama '{$request->nama}' sudah ada di folder ini.");
        }

        // PROTEKSI 2: Cek Circular Reference jika folder_id berubah
        if ($request->folder_id != $item->folder_id) {
            if ($request->folder_id == $item->id) {
                return redirect()->back()->withInput()->with('error', 'Gagal: Item tidak bisa dipindahkan ke dirinya sendiri.');
            }
            if ($request->folder_id) {
                $targetFolder = Item::findOrFail($request->folder_id);
                if ($targetFolder->isDescendantOf($item->id)) {
                    return redirect()->back()->withInput()->with('error', 'Gagal: Folder tidak boleh dipindahkan ke dalam subfolder-nya sendiri.');
                }
            }
        }

        $oldStock = (float) $item->stok_saat_ini;
        $newStock = (float) ($request->stok_saat_ini ?? 0);
        $oldFolder = $item->folder_id;

        if ($oldStock != $newStock) {
            $this->logActivity($item->id, $newStock - $oldStock, 'Penyesuaian stok via edit');
        }

        $tagsArray = array_map('trim', explode(',', $request->tags_input ?? ''));
        if ($item->is_folder) $tagsArray[] = 'folder';

        $item->update([
            'nama' => $request->nama,
            'satuan' => $request->satuan,
            'stok_saat_ini' => $newStock,
            'stok_minimum' => $request->stok_minimum,
            'harga_jual' => floor($request->harga_jual),
            'folder_id' => $request->folder_id,
            'note' => $request->note,
            'tags' => array_values(array_filter(array_unique($tagsArray))),
        ]);

        // Jika folder berubah, sinkronkan path silsilah
        if ($oldFolder != $request->folder_id) {
            $item->updatePath();
            $this->syncDescendantPaths($item);
        }

        return redirect()->route('item.show', $item->id)->with('success', 'Data berhasil diperbarui.');
    }

    /**
     * Move Item dengan Ancestry Guard (Anti-Circular).
     */
    public function move(Request $request, Item $item)
    {
        $targetId = $request->folder_id;

        // PROTEKSI 1: Cek Circular Reference
        if ($targetId == $item->id) {
            return redirect()->back()->with('error', 'Gagal: Tidak bisa memindahkan item ke dirinya sendiri.');
        }

        if ($targetId) {
            $targetFolder = Item::findOrFail($targetId);
            if ($targetFolder->isDescendantOf($item->id)) {
                return redirect()->back()->with('error', 'Gagal: Folder tidak boleh dipindahkan ke dalam subfolder-nya sendiri (Circular Reference).');
            }
        }

        // PROTEKSI 2: Cek Nama Unik di lokasi tujuan
        $exists = Item::where('nama', $item->nama)
            ->where('folder_id', $targetId)
            ->where('id', '!=', $item->id)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', "Gagal: Nama '{$item->nama}' sudah ada di lokasi tujuan.");
        }

        DB::beginTransaction();
        try {
            $item->update(['folder_id' => $targetId]);
            $item->updatePath();
            $this->syncDescendantPaths($item);
            DB::commit();
            return redirect()->back()->with('success', "Item '{$item->nama}' berhasil dipindahkan.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan sistem saat memindahkan data.');
        }
    }

    /**
     * Penyesuaian stok cepat.
     */
    public function updateQuantity(Request $request, Item $item)
    {
        $request->validate(['qty' => 'required|numeric']);
        $item->increment('stok_saat_ini', $request->qty);
        $this->logActivity($item->id, $request->qty, 'Update stok cepat (+/-)');
        return redirect()->back()->with('success', 'Stok berhasil diperbarui.');
    }

    /**
     * Hapus Item (Soft Delete).
     */
    public function destroy(Item $item)
    {
        if ($item->itemsInFolder()->exists()) {
            return redirect()->back()->with('error', 'Gagal: Folder tidak bisa dihapus karena masih berisi item lain.');
        }
        $item->delete();
        return redirect()->route('item.index')->with('success', "Item '{$item->nama}' berhasil dihapus.");
    }

    /**
     * Bulk Update sederhana.
     */
    public function bulkUpdate(Request $request)
    {
        $itemIds = json_decode($request->selected_items, true);
        if (empty($itemIds)) return redirect()->back()->with('error', 'Silakan pilih setidaknya satu item.');

        if ($request->filled('min_level_value')) {
            Item::whereIn('id', $itemIds)->update(['stok_minimum' => $request->min_level_value]);
        }
        return redirect()->back()->with('success', count($itemIds) . ' item berhasil diperbarui secara massal.');
    }

    /**
     * Ekspor CSV.
     */
    public function exportCsv() {
        $items = Item::all();
        $fileName = 'inventory_' . date('Ymd_His') . '.csv';

        return new StreamedResponse(function() use ($items) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Nama', 'SKU', 'Stok', 'Satuan', 'Harga']);
            foreach ($items as $item) {
                fputcsv($file, [
                    $item->id,
                    $item->nama,
                    $item->sku,
                    $item->calculated_stock,
                    $item->satuan,
                    $item->harga_jual
                ]);
            }
            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$fileName\""
        ]);
    }

    // --- HELPER PRIVAT ---

    private function logActivity($itemId, $qty, $note) {
        Transaksi::create([
            'item_id' => $itemId,
            'jumlah_produksi' => $qty,
            'tanggal_produksi' => now(),
            'catatan' => $note
        ]);
    }

    private function syncDescendantPaths(Item $item) {
        $descendants = Item::where('path', 'LIKE', $item->path . '%')->where('id', '!=', $item->id)->get();
        foreach ($descendants as $desc) {
            $desc->updatePath();
        }
    }

    private function generateUniqueSku($name) {
        $sku = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3)) . '-' . strtoupper(Str::random(6));
        return Item::where('sku', $sku)->exists() ? $this->generateUniqueSku($name) : $sku;
    }

    private function createItemVariants(Item $parent, array $dimensions) {
        $combinations = $this->generateCombinations($dimensions);
        foreach ($combinations as $combo) {
            $name = $parent->nama . ' (' . implode(', ', $combo) . ')';
            $variant = Item::create([
                'nama' => $name,
                'sku' => $this->generateUniqueSku($name),
                'satuan' => $parent->satuan,
                'stok_saat_ini' => 0,
                'harga_jual' => $parent->harga_jual,
                'folder_id' => $parent->folder_id,
                'parent_id' => $parent->id,
                'tags' => $parent->tags,
            ]);
            $variant->updatePath();
        }
    }

    private function generateCombinations($dimensions, $index = 0, $current = []) {
        if ($index == count($dimensions)) return [$current];
        $res = [];
        $options = array_map('trim', explode(',', $dimensions[$index]['options'] ?? ''));
        foreach ($options as $opt) {
            $next = $current; $next[] = $opt;
            $res = array_merge($res, $this->generateCombinations($dimensions, $index + 1, $next));
        }
        return $res;
    }
}
