<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Folder;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ItemController extends Controller
{
    /**
     * [DITIMPA] Tampilan Utama: Menggunakan Cursor Pagination untuk Endless Scroll.
     */
    public function index(Request $request)
    {
        $folderId = $request->query('folder_id');
        $search = $request->query('q');
        $breadcrumbs = collect([]);
        $query = Item::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama', 'LIKE', "%{$search}%")->orWhere('sku', 'LIKE', "%{$search}%");
            });
            $currentFolder = null;
        } else {
            if ($folderId) {
                $currentFolder = Folder::findOrFail($folderId);
                $breadcrumbs = $currentFolder->getBreadcrumbs();
                $query->where('folder_id', $folderId);
            } else {
                $currentFolder = null;
                $query->whereNull('folder_id');
            }
        }

        // Pakai cursorPaginate agar loading halaman terakhir tetap < 1 detik
        $items = $query->orderBy('id', 'desc')->cursorPaginate(15)->appends(request()->query());

        $materialIds = [];
        foreach ($items as $item) {
            if ($item->is_bom && is_array($item->materials)) {
                foreach ($item->materials as $m) { $materialIds[] = $m['item_id'] ?? null; }
            }
        }
        $materialMap = Item::whereIn('id', array_filter(array_unique($materialIds)))->pluck('nama', 'id');

        if ($request->ajax()) {
            return view('item.partials.item_list', compact('items', 'materialMap'))->render();
        }

        $subFolders = $currentFolder
            ? Folder::where('parent_id', $folderId)->orderBy('nama')->get()
            : Folder::whereNull('parent_id')->orderBy('nama')->get();

        $allFolders = Folder::orderBy('nama')->select('id', 'nama')->get();

        return view('item.index', compact('items', 'subFolders', 'currentFolder', 'breadcrumbs', 'search', 'materialMap', 'allFolders'));
    }

    /**
     * [FIXED] Create: Jangan load 900rb data ke dropdown!
     */
    public function create()
    {
        // Hanya ambil folder (biasanya jumlahnya sedikit)
        $allFolders = Folder::orderBy('nama')->get();
        return view('item.create', compact('allFolders'));
    }

    public function store(Request $request)
    {
        $request->validate(['nama' => 'required|string|max:100', 'type' => 'required|in:item,folder']);
        $folderId = $request->folder_id;

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
                $isBomActive = $request->is_bom === 'on';
                $materials = $isBomActive ? json_decode($request->materials_data, true) : null;
                $dimensions = json_decode($request->variant_dimensions, true) ?: [];

                if (empty($dimensions)) {
                    $this->saveSingleItem($request, $request->nama, $folderId, $materials);
                } else {
                    $this->generateVariantsWithMaterials($request, $dimensions, $folderId, $materials);
                }
            }
            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $folderId])->with('success', 'Data berhasil dibuat!');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->withInput()->with('error', $e->getMessage()); }
    }

    public function show(Item $item) {
        $history = $item->transaksis()->orderBy('tanggal_produksi', 'desc')->get();
        $stats = [
            'total_in' => $item->transaksis()->where('jumlah_produksi', '>', 0)->sum('jumlah_produksi'),
            'total_out' => abs($item->transaksis()->where('jumlah_produksi', '<', 0)->sum('jumlah_produksi')),
        ];
        return view('item.show', compact('item', 'history', 'stats'));
    }

    public function edit(Item $item) {
        $allMaterials = Item::whereNull('materials')->where('id', '!=', $item->id)->orderBy('nama')->get();
        $allFolders = Folder::orderBy('nama')->get();
        return view('item.edit', compact('item', 'allMaterials', 'allFolders'));
    }

    public function update(Request $request, Item $item) {
        $request->validate(['nama' => 'required|string|max:100']);
        $isBomActive = $request->is_bom === 'on';
        $materials = $isBomActive ? json_decode($request->materials_data, true) : null;
        $oldStock = (float) $item->stok_saat_ini;
        $newStock = $isBomActive ? 0 : (float) ($request->stok_saat_ini ?? 0);

        if (!$isBomActive && $oldStock != $newStock) {
            $this->logActivity($item->id, $newStock - $oldStock, 'Penyesuaian stok via edit item');
        }

        $item->update([
            'nama' => $request->nama, 'satuan' => $request->satuan, 'stok_saat_ini' => $newStock,
            'stok_minimum' => $request->stok_minimum, 'harga_jual' => floor($request->harga_jual ?? 0),
            'harga_beli' => floor($request->harga_beli ?? 0), 'folder_id' => $request->folder_id,
            'note' => $request->note, 'materials' => $materials,
            'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
        ]);
        return redirect()->route('item.show', $item->id)->with('success', 'Data diperbarui.');
    }

    /**
     * [BARU] Ajax Search untuk BOM agar Create Page tidak HTTP 500
     */
    public function searchAjax(Request $request)
    {
        $search = $request->q;
        $items = Item::whereNull('materials') // Material gak boleh BOM lagi
            ->where('nama', 'LIKE', "%$search%")
            ->select('id', 'nama as text')
            ->limit(10) // Sangat cepat karena di-limit
            ->get();
        return response()->json($items);
    }

    /**
     * [DITIMPA] Turbo Import: Tambahkan sinkronisasi counter di akhir
     */
    public function importCsv(Request $request)
    {
        // ... (Logic Preparasi/Staging LOAD DATA tetap sama) ...

        DB::beginTransaction();
        try {
            $folderName = $originalFilename;
            $counter = 1;
            while (Folder::where('nama', $folderName)->where('parent_id', $request->folder_id)->exists()) {
                $counter++; $folderName = "{$originalFilename} ({$counter})";
            }
            $folder = Folder::create(['nama' => $folderName, 'parent_id' => $request->folder_id]);
            $folder->updatePath();

            // Insert Items... (Pake SQL massal loe yang lama)
            // ...

            $this->finalizeImportBomOneShot($staging, $folder->id);

            // [DIPERBAIKI] SINKRONISASI COUNTER:
            $totalImported = DB::table($staging)->count();
            DB::table('folders')->where('id', $folder->id)->update(['items_count' => $totalImported]);

            // Increment children_count di parent (sudah dihandle model Folder::created)

            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $folder->id])->with('success', 'Import Selesai.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', $e->getMessage()); }
    }

    public function move(Request $request)
    {
        $id = $request->id;
        $type = $request->target_type;
        $newFolderId = $request->folder_id ?: null;

        DB::beginTransaction();
        try {
            if ($type === 'item') {
                $item = Item::findOrFail($id);
                $oldFolderId = $item->folder_id;
                if ($oldFolderId != $newFolderId) {
                    $item->update(['folder_id' => $newFolderId]);
                    if ($oldFolderId) Folder::where('id', $oldFolderId)->where('items_count', '>', 0)->decrement('items_count');
                    if ($newFolderId) Folder::where('id', $newFolderId)->increment('items_count');
                }
            } else {
                $folder = Folder::findOrFail($id);
                $oldParentId = $folder->parent_id;
                if ($oldParentId != $newFolderId) {
                    $folder->update(['parent_id' => $newFolderId]);
                    $folder->updatePath();
                    if ($oldParentId) Folder::where('id', $oldParentId)->where('children_count', '>', 0)->decrement('children_count');
                    if ($newFolderId) Folder::where('id', $newFolderId)->increment('children_count');
                }
            }
            DB::commit();
            return redirect()->back()->with('success', 'Berhasil dipindahkan.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', 'Gagal.'); }
    }

    /**
     * [FUNGSI BARU] Optimasi Level Dewa untuk relasi BOM.
     */
    private function finalizeImportBomOneShot($staging, $folderId)
    {
        $mapping = DB::table('items')
            ->join($staging, 'items.nama', '=', "$staging.nama")
            ->where('items.folder_id', $folderId)
            ->pluck('items.id', "$staging.nomor")
            ->toArray();

        $bomRows = DB::table($staging)
            ->whereNotNull('materials')->where('materials', '<>', '')
            ->select('nomor', 'materials')->get();

        if ($bomRows->isEmpty()) return;

        $mapTable = 'temp_bom_map_' . Str::random(5);
        DB::statement("CREATE TEMPORARY TABLE $mapTable (item_id INT PRIMARY KEY, final_json JSON)");

        $batch = [];
        foreach ($bomRows as $row) {
            $mParts = array_map('trim', explode(',', $row->materials));
            $finalM = [];
            foreach ($mParts as $p) {
                preg_match('/^(\d+)(?:\(([\d.]+)\))?$/', $p, $matches);
                if ($matches) {
                    $mNum = $matches[1];
                    $mQty = isset($matches[2]) ? (float)$matches[2] : 1.0;
                    if (isset($mapping[$mNum])) $finalM[] = ['item_id' => $mapping[$mNum], 'qty' => $mQty];
                }
            }
            if ($finalM && isset($mapping[$row->nomor])) {
                $batch[] = ['item_id' => $mapping[$row->nomor], 'final_json' => json_encode($finalM)];
            }
            if (count($batch) >= 3000) {
                DB::table($mapTable)->insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) DB::table($mapTable)->insert($batch);

        DB::statement("UPDATE items JOIN $mapTable ON items.id = $mapTable.item_id SET items.materials = $mapTable.final_json");
    }

    /**
     * HARMONIZED EXPORT: Kolom selaras CSV & PDF.
     */
    public function exportCsv(Request $request) {
        $folderId = $request->folder_id;
        $isGlobal = $request->global == 'true';
        $ids = json_decode($request->ids, true);

        return new StreamedResponse(function() use ($isGlobal, $folderId, $ids) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['nomor', 'nama', 'satuan', 'stok_saat_ini', 'stok_minimum', 'harga_jual', 'harga_beli', 'note', 'materials', 'tags']);

            $query = Item::query();
            if ($isGlobal) $query->where('folder_id', $folderId);
            else $query->whereIn('id', $ids);

            $query->chunk(2000, function($items) use ($handle) {
                foreach ($items as $index => $item) {
                    $mParts = [];
                    if ($item->is_bom && is_array($item->materials)) {
                        foreach ($item->materials as $m) { $mParts[] = "{$m['item_id']}({$m['qty']})"; }
                    }
                    fputcsv($handle, [$index+1, $item->nama, $item->satuan, $item->calculated_stock, $item->stok_minimum, $item->harga_jual, $item->harga_beli, $item->note, implode(', ', $mParts), $item->tags ? implode(', ', $item->tags) : '']);
                }
            });
            fclose($handle);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="inventory_export.csv"']);
    }

    /**
     * [DITIMPA] Export PDF: Batasi 1000 data agar tidak HTTP 500
     */
    public function exportPdf(Request $request) {
        $isGlobal = $request->global == 'true';
        $ids = json_decode($request->ids, true);
        $folderId = $request->folder_id;

        $query = Item::whereIn('id', $ids)->with('folder');
        if ($isGlobal) {
            $query = Item::where('folder_id', $folderId)->with('folder')->limit(1000);
        }

        $items = $query->get();
        return view('item.export_pdf', compact('items'));
    }

    /**
     * BULK ACTIONS (Clone, Qty, Edit Field).
     */
    public function bulkClone(Request $request) {
        $itemIds = json_decode($request->selected_items, true);
        foreach ($itemIds as $id) {
            $original = Item::findOrFail($id); $clone = $original->replicate();
            $clone->nama = $original->nama . ' - copy'; $clone->sku = $this->generateUniqueSku($clone->nama); $clone->save();
        }
        return redirect()->back()->with('success', 'Berhasil kloning.');
    }

    public function bulkUpdateQuantity(Request $request) {
        $itemIds = json_decode($request->selected_items, true);
        $change = (float) $request->qty_adjustment;
        foreach (Item::whereIn('id', $itemIds)->get() as $item) {
            if (!$item->is_bom) { $item->increment('stok_saat_ini', $change); $this->logActivity($item->id, $change, 'Penyesuaian massal'); }
        }
        return redirect()->back()->with('success', 'Stok diperbarui.');
    }

    public function bulkUpdate(Request $request) {
        $itemIds = json_decode($request->selected_items, true);
        $updateData = [];
        if ($request->filled('min_level_value')) $updateData['stok_minimum'] = $request->min_level_value;
        if ($request->filled('harga_jual_value')) $updateData['harga_jual'] = floor($request->harga_jual_value);
        if ($request->filled('harga_beli_value')) $updateData['harga_beli'] = floor($request->harga_beli_value);
        if ($request->filled('satuan_value')) $updateData['satuan'] = $request->satuan_value;
        if ($request->filled('note_value')) $updateData['note'] = $request->note_value;
        if ($request->filled('folder_id_value')) $updateData['folder_id'] = $request->folder_id_value === 'NULL' ? null : $request->folder_id_value;
        Item::whereIn('id', $itemIds)->update($updateData);
        return redirect()->back()->with('success', 'Update massal berhasil.');
    }

    // --- HELPERS ---

    private function saveSingleItem($request, $name, $folderId, $materials) {
        $item = Item::create([
            'nama' => $name, 'sku' => ($materials) ? 'BOM-' . strtoupper(Str::random(6)) : $this->generateUniqueSku($name),
            'satuan' => $request->satuan, 'stok_saat_ini' => ($materials) ? 0 : ($request->stok_saat_ini ?? 0),
            'stok_minimum' => $request->stok_minimum ?? 0, 'harga_jual' => $request->harga_jual ?? 0, 'harga_beli' => $request->harga_beli ?? 0,
            'folder_id' => $folderId, 'materials' => $materials, 'note' => $request->note, 'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
        ]);
        if (!$materials && $item->stok_saat_ini > 0) $this->logActivity($item->id, $item->stok_saat_ini, 'Saldo awal');
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
                'harga_beli' => floor($request->harga_beli ?? 0),
                'folder_id' => $folderId,
                'materials' => $materials,
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

    private function generateUniqueSku($name) { return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3)) . '-' . strtoupper(Str::random(6)); }

    private function syncDescendantPaths(Folder $folder) { foreach (Folder::where('path', 'LIKE', $folder->path . '%')->where('id', '!=', $folder->id)->get() as $desc) $desc->updatePath(); }

    private function logActivity($itemId, $qty, $note) { Transaksi::create(['item_id' => $itemId, 'jumlah_produksi' => $qty, 'tanggal_produksi' => now(), 'catatan' => $note]); }

    public function updateQuantity(Request $request, Item $item) {
        $request->validate(['qty' => 'required|numeric']);
        $item->increment('stok_saat_ini', $request->qty);
        $this->logActivity($item->id, $request->qty, 'Update cepat');
        return redirect()->back();
    }

    public function updateFolder(Request $request, Folder $folder) {
        $folder->update(['nama' => $request->nama]);
        return redirect()->back();
    }

    public function destroy(Item $item) { $item->delete(); return redirect()->route('item.index'); }

    /**
     * DELETE FOLDER RECURSIVE: Menghapus folder, sub-folder, dan semua item di dalamnya.
     */
    public function destroyFolder(Folder $folder)
    {
        DB::beginTransaction();
        try {
            $descendantIds = Folder::where('path', 'LIKE', $folder->path . '%')->pluck('id');
            Item::whereIn('folder_id', $descendantIds)->delete();
            Folder::whereIn('id', $descendantIds)->delete();
            DB::commit();
            return redirect()->route('item.index')->with('success', "Folder dan seluruh isinya berhasil dihapus.");
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', 'Gagal menghapus folder.'); }
    }
}
