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
     * Tampilan Utama: Mendukung navigasi folder, pencarian global,
     * dual counter folder, dan mapping material untuk preview BOM.
     */
    public function index(Request $request)
    {
        $folderId = $request->query('folder_id');
        $search = $request->query('q');
        $breadcrumbs = collect([]);

        // 1. Logika Query Folder & Item
        $folderQuery = Folder::query();
        $itemQuery = Item::query();

        if ($search) {
            $subFolders = $folderQuery->where('nama', 'LIKE', "%{$search}%")->orderBy('nama')->get();
            $items = $itemQuery->where(function($q) use ($search) {
                        $q->where('nama', 'LIKE', "%{$search}%")
                          ->orWhere('sku', 'LIKE', "%{$search}%")
                          ->orWhere('tags', 'LIKE', "%{$search}%");
                    })->orderBy('nama')->paginate(15)->appends(['q' => $search]);
            $currentFolder = null;
        } else {
            if ($folderId) {
                $currentFolder = Folder::withCount(['children', 'items'])->findOrFail($folderId);
                $breadcrumbs = $currentFolder->getBreadcrumbs();
                $subFolders = Folder::where('parent_id', $folderId)->withCount(['children', 'items'])->orderBy('nama')->get();
                $items = Item::where('folder_id', $folderId)->orderBy('nama')->paginate(15)->appends(['folder_id' => $folderId]);
            } else {
                $currentFolder = null;
                $subFolders = Folder::whereNull('parent_id')->withCount(['children', 'items'])->orderBy('nama')->get();
                $items = Item::whereNull('folder_id')->orderBy('nama')->paginate(15);
            }
        }

        // 2. Mapping Nama Material untuk Preview di Card BOM (Ubah ID ke Nama)
        $materialIds = [];
        foreach ($items as $item) {
            if ($item->is_bom && is_array($item->materials)) {
                foreach ($item->materials as $m) {
                    $materialIds[] = $m['item_id'];
                }
            }
        }
        $materialMap = Item::whereIn('id', array_unique($materialIds))->pluck('nama', 'id');

        $allFolders = Folder::orderBy('nama')->get();

        return view('item.index', compact('items', 'subFolders', 'currentFolder', 'allFolders', 'breadcrumbs', 'search', 'materialMap'));
    }

    /**
     * Form Create: Mengambil folder dan bahan baku murni (bukan BOM).
     */
    public function create()
    {
        $allMaterials = Item::whereNull('materials')->orderBy('nama')->get();
        $allFolders = Folder::orderBy('nama')->get();
        return view('item.create', compact('allMaterials', 'allFolders'));
    }

    /**
     * Store Universal: Menangani Folder, Item Biasa, BOM, dan Varian (BOM/Fisik).
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:100',
            'type' => 'required|in:item,folder',
            'folder_id' => 'nullable|exists:folders,id'
        ]);

        $folderId = $request->folder_id;

        // Guard: Validasi Nama Unik
        if (Folder::where('nama', $request->nama)->where('parent_id', $folderId)->exists() ||
            Item::where('nama', $request->nama)->where('folder_id', $folderId)->exists()) {
            return redirect()->back()->withInput()->with('error', "Gagal: Nama sudah digunakan di lokasi ini.");
        }

        DB::beginTransaction();
        try {
            if ($request->type === 'folder') {
                $folder = Folder::create(['nama' => $request->nama, 'parent_id' => $folderId]);
                $folder->updatePath();
            } else {
                // Ambil data BOM dan Varian dari Request
                $isBomActive = $request->is_bom === 'on';
                $materials = $isBomActive ? json_decode($request->materials_data, true) : null;
                $dimensions = json_decode($request->variant_dimensions, true) ?: [];

                if (empty($dimensions)) {
                    // Simpan Item/BOM tunggal
                    $this->saveSingleItem($request, $request->nama, $folderId, $materials);
                } else {
                    // Simpan Varian (mendukung resep material warisan)
                    $this->generateVariantsWithMaterials($request, $dimensions, $folderId, $materials);
                }
            }
            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $folderId])->with('success', 'Data berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    /**
     * Detail Item dengan Ringkasan Statistik In/Out.
     */
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
        $allMaterials = Item::whereNull('materials')->where('id', '!=', $item->id)->orderBy('nama')->get();
        $allFolders = Folder::orderBy('nama')->get();
        return view('item.edit', compact('item', 'allMaterials', 'allFolders'));
    }

    /**
     * Update dengan Integrasi Harga Beli & BOM Toggle.
     */
    public function update(Request $request, Item $item)
    {
        $request->validate(['nama' => 'required|string|max:100']);

        $isBomActive = $request->is_bom === 'on';
        $materials = $isBomActive ? json_decode($request->materials_data, true) : null;

        $oldStock = (float) $item->stok_saat_ini;
        // Jika BOM Aktif, stok fisik dipaksa 0 (karena BOM adalah hasil rakitan)
        $newStock = $isBomActive ? 0 : (float) ($request->stok_saat_ini ?? 0);

        if (!$isBomActive && $oldStock != $newStock) {
            $this->logActivity($item->id, $newStock - $oldStock, 'Penyesuaian stok via edit item');
        }

        $item->update([
            'nama' => $request->nama,
            'satuan' => $request->satuan,
            'stok_saat_ini' => $newStock,
            'stok_minimum' => $request->stok_minimum,
            'harga_jual' => floor($request->harga_jual ?? 0),
            'harga_beli' => floor($request->harga_beli ?? 0), // INTEGRASI HARGA BELI
            'folder_id' => $request->folder_id,
            'note' => $request->note,
            'materials' => $materials,
            'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
        ]);

        return redirect()->route('item.show', $item->id)->with('success', 'Data diperbarui.');
    }

    /**
     * Bulk Updates: Menangani Fields Dasar, Tags, dan Perpindahan Lokasi.
     */
    public function bulkUpdate(Request $request)
    {
        $itemIds = json_decode($request->selected_items, true);
        if (empty($itemIds)) return redirect()->back()->with('error', 'Pilih item.');

        $updateData = [];
        if ($request->filled('min_level_value')) $updateData['stok_minimum'] = $request->min_level_value;
        if ($request->filled('harga_jual_value')) $updateData['harga_jual'] = floor($request->harga_jual_value);
        if ($request->filled('harga_beli_value')) $updateData['harga_beli'] = floor($request->harga_beli_value);
        if ($request->filled('satuan_value')) $updateData['satuan'] = $request->satuan_value;
        if ($request->filled('folder_id_value')) {
            $updateData['folder_id'] = $request->folder_id_value === 'NULL' ? null : $request->folder_id_value;
        }

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
            return redirect()->back()->with('success', count($itemIds) . ' item diupdate secara massal.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', 'Gagal update massal.'); }
    }

    /**
     * Bulk Clone: Duplikasi data dengan suffix '- copy'.
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
                if ($original->sku) $clone->sku = $this->generateUniqueSku($clone->nama);
                $clone->save();
            }
            DB::commit();
            return redirect()->back()->with('success', count($itemIds) . ' item dikloning.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', 'Gagal clone.'); }
    }

    /**
     * Bulk Quantity Adjustment: Menambah/Mengurangi stok secara massal (+/-).
     */
    public function bulkUpdateQuantity(Request $request)
    {
        $itemIds = json_decode($request->selected_items, true);
        $change = (float) $request->qty_adjustment;
        if (empty($itemIds) || $change == 0) return redirect()->back()->with('error', 'Input tidak valid.');

        DB::beginTransaction();
        try {
            foreach (Item::whereIn('id', $itemIds)->get() as $item) {
                if (!$item->is_bom) { // Hanya item fisik yang bisa di-adjust stoknya
                    $item->increment('stok_saat_ini', $change);
                    $this->logActivity($item->id, $change, 'Penyesuaian stok massal');
                }
            }
            DB::commit();
            return redirect()->back()->with('success', 'Stok berhasil disesuaikan.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', 'Gagal.'); }
    }

    /**
     * Exports.
     */
    public function exportCsv() {
        $items = Item::all();
        return new StreamedResponse(function() use ($items) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Nama', 'SKU', 'Stok', 'Satuan', 'Hrg Beli', 'Hrg Jual']);
            foreach ($items as $item) fputcsv($file, [$item->id, $item->nama, $item->sku, $item->calculated_stock, $item->satuan, $item->harga_beli, $item->harga_jual]);
            fclose($file);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"inventory.csv\""]);
    }

    public function exportPdf() {
        $items = Item::with('folder')->get();
        return view('item.export_pdf', compact('items'));
    }

    /**
     * Folder & Item Deletion.
     */
    public function updateFolder(Request $request, Folder $folder) {
        $folder->update(['nama' => $request->nama]);
        return redirect()->back()->with('success', 'Nama folder diubah.');
    }

    public function destroy(Item $item) { $item->delete(); return redirect()->route('item.index')->with('success', 'Item dihapus.'); }

    public function destroyFolder(Folder $folder) {
        if ($folder->children()->exists() || $folder->items()->exists()) return redirect()->back()->with('error', 'Gagal: Folder tidak kosong.');
        $folder->delete();
        return redirect()->back()->with('success', 'Folder dihapus.');
    }

    public function move(Request $request) {
        $targetId = $request->folder_id;
        if ($request->target_type === 'folder') {
            $folder = Folder::findOrFail($request->id);
            if ($targetId && Folder::findOrFail($targetId)->isDescendantOf($folder->id)) return redirect()->back()->with('error', 'Silsilah melingkar.');
            $folder->update(['parent_id' => $targetId]);
            $folder->updatePath();
            $this->syncDescendantPaths($folder);
        } else {
            Item::where('id', $request->id)->update(['folder_id' => $targetId]);
        }
        return redirect()->back()->with('success', 'Lokasi dipindahkan.');
    }

    // --- LOGIKA INTERNAL (PRIVATE HELPERS) ---

    private function saveSingleItem($request, $name, $folderId, $materials)
    {
        $stokInput = (float) ($request->stok_saat_ini ?? 0);
        $item = Item::create([
            'nama' => $name,
            'sku' => ($materials) ? 'BOM-' . strtoupper(Str::random(6)) : $this->generateUniqueSku($name),
            'satuan' => $request->satuan ?? 'pcs',
            'stok_saat_ini' => ($materials) ? 0 : $stokInput,
            'stok_minimum' => $request->stok_minimum ?? 0,
            'harga_jual' => floor($request->harga_jual ?? 0),
            'harga_beli' => floor($request->harga_beli ?? 0), // HARGA BELI
            'folder_id' => $folderId,
            'materials' => $materials,
            'note' => $request->note,
            'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
        ]);
        if (!$materials && $stokInput > 0) $this->logActivity($item->id, $stokInput, 'Saldo awal item baru');
    }

    private function generateVariantsWithMaterials($request, $dimensions, $folderId, $materials)
    {
        $combinations = $this->generateCombinations($dimensions);
        foreach ($combinations as $combo) {
            $name = $request->nama . ' - ' . implode(', ', $combo);
            Item::create([
                'nama' => $name,
                'sku' => $this->generateUniqueSku($name),
                'satuan' => $request->satuan ?? 'pcs',
                'stok_saat_ini' => 0,
                'stok_minimum' => $request->stok_minimum ?? 0,
                'harga_jual' => floor($request->harga_jual ?? 0),
                'harga_beli' => floor($request->harga_beli ?? 0), // HARGA BELI
                'folder_id' => $folderId,
                'materials' => $materials, // Resep BOM diwariskan ke varian
                'note' => "Varian dari " . $request->nama,
                'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
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

    private function generateUniqueSku($name) {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
        return $prefix . '-' . strtoupper(Str::random(6));
    }

    private function syncDescendantPaths(Folder $folder) {
        foreach (Folder::where('path', 'LIKE', $folder->path . '%')->where('id', '!=', $folder->id)->get() as $desc) $desc->updatePath();
    }

    private function logActivity($itemId, $qty, $note) {
        Transaksi::create(['item_id' => $itemId, 'jumlah_produksi' => $qty, 'tanggal_produksi' => now(), 'catatan' => $note]);
    }
}
