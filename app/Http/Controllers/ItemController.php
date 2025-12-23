<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Folder;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemController extends Controller
{
    /**
     * Tampilan Utama: Folder + Item + Search Global + Breadcrumbs.
     */
    public function index(Request $request)
    {
        $folderId = $request->query('folder_id');
        $search = $request->query('q');
        $breadcrumbs = collect([]);

        $folderQuery = Folder::query();
        $itemQuery = Item::query();

        if ($search) {
            // PENCARIAN GLOBAL
            $subFolders = $folderQuery->where('nama', 'LIKE', "%{$search}%")->orderBy('nama')->get();
            $items = $itemQuery->where(function($q) use ($search) {
                        $q->where('nama', 'LIKE', "%{$search}%")
                          ->orWhere('sku', 'LIKE', "%{$search}%")
                          ->orWhere('tags', 'LIKE', "%{$search}%");
                    })->orderBy('nama')->paginate(15)->appends(['q' => $search]);
            $currentFolder = null;
        } else {
            // NAVIGASI BIASA
            if ($folderId) {
                $currentFolder = Folder::with('parent')->findOrFail($folderId);
                $breadcrumbs = $currentFolder->getBreadcrumbs();
                $subFolders = Folder::where('parent_id', $folderId)->orderBy('nama')->get();
                $items = Item::where('folder_id', $folderId)->orderBy('nama')->paginate(15)->appends(['folder_id' => $folderId]);
            } else {
                $currentFolder = null;
                $subFolders = Folder::whereNull('parent_id')->orderBy('nama')->get();
                $items = Item::whereNull('folder_id')->orderBy('nama')->paginate(15);
            }
        }

        $allFolders = Folder::orderBy('nama')->get();

        return view('item.index', compact('items', 'subFolders', 'currentFolder', 'allFolders', 'breadcrumbs', 'search'));
    }

    public function create()
    {
        $allMaterials = Item::whereNull('materials')->orderBy('nama')->get();
        $allFolders = Folder::orderBy('nama')->get();
        return view('item.create', compact('allMaterials', 'allFolders'));
    }

    public function store(Request $request)
    {
        $request->validate(['nama' => 'required|string|max:100', 'type' => 'required|in:item,bom,folder']);
        $folderId = $request->folder_id;

        if (Folder::where('nama', $request->nama)->where('parent_id', $folderId)->exists() ||
            Item::where('nama', $request->nama)->where('folder_id', $folderId)->exists()) {
            return redirect()->back()->withInput()->with('error', "Gagal: Nama sudah ada di lokasi ini.");
        }

        DB::beginTransaction();
        try {
            if ($request->type === 'folder') {
                $folder = Folder::create(['nama' => $request->nama, 'parent_id' => $folderId]);
                $folder->updatePath();
            } else {
                $dimensions = json_decode($request->variant_dimensions, true) ?: [];
                if (empty($dimensions)) {
                    $this->createNewItem($request, $request->nama, $folderId);
                } else {
                    $this->generateIndependentVariants($request, $dimensions, $folderId);
                }
            }
            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $folderId])->with('success', 'Berhasil dibuat!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Item $item)
    {
        $history = $item->transaksis()->orderBy('tanggal_produksi', 'desc')->get();
        $stats = [
            'total_in' => $item->transaksis()->where('jumlah_produksi', '>', 0)->sum('jumlah_produksi'),
            'total_out' => abs($item->transaksis()->where('jumlah_produksi', '<', 0)->sum('jumlah_produksi')),
        ];
        return view('item.show', compact('item', 'history', 'stats'));
    }

    public function edit(Item $item)
    {
        $allFolders = Folder::orderBy('nama')->get();
        return view('item.edit', compact('item', 'allFolders'));
    }

    public function update(Request $request, Item $item)
    {
        $request->validate(['nama' => 'required|string|max:100']);

        $oldStock = (float) $item->stok_saat_ini;
        $newStock = (float) ($request->stok_saat_ini ?? 0);

        if ($oldStock != $newStock) {
            $this->logActivity($item->id, $newStock - $oldStock, 'Penyesuaian stok via edit item');
        }

        $item->update([
            'nama' => $request->nama,
            'satuan' => $request->satuan,
            'stok_saat_ini' => $newStock,
            'stok_minimum' => $request->stok_minimum,
            'harga_jual' => floor($request->harga_jual ?? 0),
            'folder_id' => $request->folder_id,
            'note' => $request->note,
            'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
        ]);

        return redirect()->route('item.show', $item->id)->with('success', 'Item diperbarui.');
    }

    public function updateFolder(Request $request, Folder $folder)
    {
        $request->validate(['nama' => 'required|string|max:100']);
        $folder->update(['nama' => $request->nama]);
        return redirect()->back()->with('success', 'Folder diubah.');
    }

    public function move(Request $request)
    {
        $request->validate(['target_type' => 'required', 'id' => 'required', 'folder_id' => 'nullable']);
        $targetId = $request->folder_id;

        if ($request->target_type === 'folder') {
            $folder = Folder::findOrFail($request->id);
            if ($targetId && Folder::findOrFail($targetId)->isDescendantOf($folder->id)) {
                return redirect()->back()->with('error', 'Gagal: Silsilah melingkar.');
            }
            $folder->update(['parent_id' => $targetId]);
            $folder->updatePath();
            $this->syncDescendantPaths($folder);
        } else {
            Item::where('id', $request->id)->update(['folder_id' => $targetId]);
        }
        return redirect()->back()->with('success', 'Pindah lokasi berhasil.');
    }

    public function updateQuantity(Request $request, Item $item)
    {
        $request->validate(['qty' => 'required|numeric']);
        $item->increment('stok_saat_ini', $request->qty);
        $this->logActivity($item->id, $request->qty, 'Update cepat (+/-)');
        return redirect()->back()->with('success', 'Stok diupdate.');
    }

    /**
     * LENGKAP: Bulk Update (Fields, Tags, Move).
     */
    public function bulkUpdate(Request $request)
    {
        $itemIds = json_decode($request->selected_items, true);
        if (empty($itemIds)) return redirect()->back()->with('error', 'Pilih item.');

        $updateData = [];
        if ($request->filled('min_level_value')) $updateData['stok_minimum'] = $request->min_level_value;
        if ($request->filled('harga_jual_value')) $updateData['harga_jual'] = floor($request->harga_jual_value);
        if ($request->filled('satuan_value')) $updateData['satuan'] = $request->satuan_value;
        if ($request->filled('folder_id_value')) $updateData['folder_id'] = $request->folder_id_value === 'NULL' ? null : $request->folder_id_value;

        DB::beginTransaction();
        try {
            if (!empty($updateData)) Item::whereIn('id', $itemIds)->update($updateData);
            if ($request->filled('tags_input_value')) {
                $newTags = array_filter(array_map('trim', explode(',', $request->tags_input_value)));
                foreach (Item::whereIn('id', $itemIds)->get() as $item) {
                    $item->update(['tags' => array_values(array_unique(array_merge($item->tags ?? [], $newTags)))]);
                }
            }
            DB::commit();
            return redirect()->back()->with('success', count($itemIds) . ' item diupdate.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', 'Gagal.'); }
    }

    /**
     * BARU: Bulk Clone Data.
     */
    public function bulkClone(Request $request)
    {
        $itemIds = json_decode($request->selected_items, true);
        if (empty($itemIds)) return redirect()->back()->with('error', 'Pilih item.');

        DB::beginTransaction();
        try {
            foreach ($itemIds as $id) {
                $original = Item::findOrFail($id);
                $clone = $original->replicate();
                $clone->nama = $original->nama . ' - copy';
                if ($original->sku) $clone->sku = strtoupper(substr($clone->nama, 0, 3)) . '-' . strtoupper(Str::random(6));
                $clone->save();
            }
            DB::commit();
            return redirect()->back()->with('success', count($itemIds) . ' item berhasil di-clone.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', 'Gagal clone.'); }
    }

    /**
     * BARU: Bulk Quantity Adjustment.
     */
    public function bulkUpdateQuantity(Request $request)
    {
        $itemIds = json_decode($request->selected_items, true);
        $change = (float) $request->qty_adjustment;
        if (empty($itemIds) || $change == 0) return redirect()->back()->with('error', 'Input tidak valid.');

        DB::beginTransaction();
        try {
            foreach (Item::whereIn('id', $itemIds)->get() as $item) {
                $item->increment('stok_saat_ini', $change);
                $this->logActivity($item->id, $change, 'Penyesuaian stok massal');
            }
            DB::commit();
            return redirect()->back()->with('success', 'Stok ' . count($itemIds) . ' item diupdate.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', 'Gagal.'); }
    }

    public function exportCsv() {
        $items = Item::all();
        return new StreamedResponse(function() use ($items) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Nama', 'SKU', 'Stok', 'Satuan', 'Harga']);
            foreach ($items as $item) fputcsv($file, [$item->id, $item->nama, $item->sku, $item->calculated_stock, $item->satuan, $item->harga_jual]);
            fclose($file);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"inventory.csv\""]);
    }

    public function exportPdf() {
        $items = Item::with('folder')->get();
        return view('item.export_pdf', compact('items'));
    }

    public function destroy(Item $item) { $item->delete(); return redirect()->route('item.index')->with('success', 'Dihapus.'); }

    public function destroyFolder(Folder $folder) {
        if ($folder->children()->exists() || $folder->items()->exists()) return redirect()->back()->with('error', 'Folder tidak kosong.');
        $folder->delete(); return redirect()->back()->with('success', 'Folder dihapus.');
    }

    // --- HELPERS ---
    private function createNewItem($request, $name, $folderId) {
        $item = Item::create([
            'nama' => $name,
            'sku' => ($request->type === 'bom') ? 'BOM-' . strtoupper(Str::random(6)) : $this->generateUniqueSku($name),
            'satuan' => $request->satuan ?? 'pcs',
            'stok_saat_ini' => $request->stok_saat_ini ?? 0,
            'stok_minimum' => $request->stok_minimum ?? 0,
            'harga_jual' => floor($request->harga_jual ?? 0),
            'folder_id' => $folderId,
            'materials' => ($request->type === 'bom') ? json_decode($request->materials_data, true) : null,
            'note' => $request->note,
            'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
        ]);
        if ($request->stok_saat_ini > 0) $this->logActivity($item->id, $request->stok_saat_ini, 'Saldo awal');
    }

    private function generateIndependentVariants($request, $dimensions, $folderId) {
        $combinations = $this->generateCombinations($dimensions);
        foreach ($combinations as $combo) {
            $name = $request->nama . ' - ' . implode(', ', $combo);
            Item::create([
                'nama' => $name, 'sku' => $this->generateUniqueSku($name), 'satuan' => $request->satuan ?? 'pcs',
                'stok_saat_ini' => 0, 'stok_minimum' => $request->stok_minimum ?? 0, 'harga_jual' => floor($request->harga_jual ?? 0),
                'folder_id' => $folderId, 'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
            ]);
        }
    }

    private function generateCombinations($dims, $idx = 0, $curr = []) {
        if ($idx == count($dims)) return [$curr];
        $res = [];
        foreach (array_map('trim', explode(',', $dims[$idx]['options'])) as $opt) {
            $next = $curr; $next[] = $opt; $res = array_merge($res, $this->generateCombinations($dims, $idx + 1, $next));
        }
        return $res;
    }

    private function syncDescendantPaths(Folder $folder) {
        foreach (Folder::where('path', 'LIKE', $folder->path . '%')->where('id', '!=', $folder->id)->get() as $desc) $desc->updatePath();
    }

    private function logActivity($itemId, $qty, $note) {
        Transaksi::create(['item_id' => $itemId, 'jumlah_produksi' => $qty, 'tanggal_produksi' => now(), 'catatan' => $note]);
    }

    private function generateUniqueSku($name) {
        $sku = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3)) . '-' . strtoupper(Str::random(6));
        return Item::where('sku', $sku)->exists() ? $this->generateUniqueSku($name) : $sku;
    }
}
