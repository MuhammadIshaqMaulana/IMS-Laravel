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
     * [DITIMPA] Index: Default Name Asc & Dual Sort Logic
     */
    public function index(Request $request)
    {
        $folderId = $request->query('folder_id');
        $search = $request->query('q');
        $breadcrumbs = collect([]);

        // Default Sort: Name Asc
        $sortField = $request->query('sort', 'name');
        $sortOrder = $request->query('order', 'asc');

        $validSorts = [
            'name'      => 'nama',
            'created'   => 'created_at',
            'updated'   => 'updated_at',
            'stock'     => 'stok_saat_ini',
            'min_stock' => 'stok_minimum',
            'buy'       => 'harga_beli',
            'sell'      => 'harga_jual'
        ];

        $dbSortField = $validSorts[$sortField] ?? 'nama';
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

        $items = $query->orderBy($dbSortField, $sortOrder)->cursorPaginate(25)->appends($request->query());

        // Mapping Material
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

    /**
     * [DITIMPA] Store: Catat Aktivitas Buat Baru
     */
    public function store(Request $request)
    {
        // ... (validasi tetap sama) ...
        $request->validate(['nama' => 'required|string|max:100', 'type' => 'required|in:item,folder']);

        DB::beginTransaction();
        try {
            if ($request->type === 'folder') {
                $folder = Folder::create(['nama' => $request->nama, 'parent_id' => $request->folder_id]);
                $folder->updatePath();
                $this->logActivity(null, 0, "Dibuat Folder baru: '{$folder->nama}' di " . ($folder->parent ? $folder->parent->nama : 'Root'));
            } else {
                // Logika item... (saveSingleItem di bawah menghandle log)
                if (empty(json_decode($request->variant_dimensions, true))) {
                    $this->saveSingleItem($request, $request->nama, $request->folder_id, null);
                } else {
                    $this->generateVariantsWithMaterials($request, json_decode($request->variant_dimensions, true), $request->folder_id, null);
                }
            }
            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $request->folder_id])->with('success', 'Berhasil dibuat.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', $e->getMessage()); }
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

    /**
     * [DITIMPA] Update: Catat Perubahan Data
     */
    public function update(Request $request, Item $item)
    {
        $oldName = $item->nama;
        $item->update($request->all());
        $this->logActivity($item->id, 0, "Update data item: '{$oldName}' -> '{$item->nama}'");
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
     * [DITIMPA] Import CSV: Log Detail File & Folder
     */
    public function importCsv(Request $request)
    {
        ini_set('max_execution_time', 1200);
        ini_set('memory_limit', '2048M');
        $startTime = microtime(true);

        $request->validate(['file_csv' => 'required|mimes:csv,txt']);
        $filePath = $request->file('file_csv')->getRealPath();
        $originalFilename = $request->file('file_csv')->getClientOriginalName();

        // Siapkan staging di luar try agar variable tersedia
        $staging = 'temp_imp_' . Str::random(5);
        DB::statement("SET SESSION unique_checks=0;");
        DB::statement("SET SESSION foreign_key_checks=0;");

        DB::statement("CREATE TEMPORARY TABLE $staging (
            nomor INT, nama VARCHAR(255) COLLATE utf8mb4_unicode_ci, satuan VARCHAR(50) COLLATE utf8mb4_unicode_ci,
            stok_saat_ini VARCHAR(50), stok_minimum VARCHAR(50), harga_jual VARCHAR(50), harga_beli VARCHAR(50),
            note TEXT COLLATE utf8mb4_unicode_ci, materials TEXT COLLATE utf8mb4_unicode_ci, tags TEXT COLLATE utf8mb4_unicode_ci
        )");

        $pathForSql = str_replace('\\', '/', $filePath);
        DB::connection()->getpdo()->exec("LOAD DATA LOCAL INFILE '$pathForSql' INTO TABLE $staging FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\\r\\n' IGNORE 1 LINES");
        DB::statement("ALTER TABLE $staging ADD INDEX(nama), ADD INDEX(nomor)");

        DB::beginTransaction();
        try {
            $folderName = pathinfo($originalFilename, PATHINFO_FILENAME);
            $folder = Folder::create(['nama' => $folderName, 'parent_id' => $request->folder_id]);
            $folder->updatePath();

            $now = now();
            DB::statement("INSERT INTO items (nama, sku, satuan, stok_saat_ini, stok_minimum, harga_jual, harga_beli, note, folder_id, created_at, updated_at)
                SELECT TRIM(nama), CONCAT('SKU-', nomor, '-', UNIX_TIMESTAMP()), satuan,
                CASE WHEN (materials IS NOT NULL AND materials <> '') THEN 0 ELSE stok_saat_ini END,
                stok_minimum, harga_jual, harga_beli, note, {$folder->id}, '$now', '$now'
                FROM $staging WHERE nama <> ''");

            $this->finalizeImportBomOneShot($staging, $folder->id);

            $total = DB::table($staging)->count();
            DB::table('folders')->where('id', $folder->id)->update(['items_count' => $total]);

            // [LOG BARU]
            $this->logActivity(null, $total, "IMPORT SELESAI: File '{$originalFilename}' ({$total} baris) masuk ke folder '{$folderName}'");

            DB::commit();
            return redirect()->back()->with('success', 'Import Berhasil.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        } finally {
            DB::statement("SET SESSION unique_checks=1;");
            DB::statement("SET SESSION foreign_key_checks=1;");
        }
    }

    /**
     * [DITIMPA] Move: Catat Perpindahan Lokasi
     */
    public function move(Request $request) {
        $target = ($request->target_type === 'item') ? Item::findOrFail($request->id) : Folder::findOrFail($request->id);
        $oldLoc = $target->folder ? $target->folder->nama : 'Root';
        $newLoc = $request->folder_id ? Folder::find($request->folder_id)->nama : 'Root';

        DB::beginTransaction();
        try {
            // (Logika update folder_id dan counter tetap sama seperti chat sebelumnya)
            // ... (asumsi kode move sudah loe punya di controller)

            $this->logActivity($request->target_type === 'item' ? $target->id : null, 0, "PINDAH: {$request->target_type} '{$target->nama}' dari [{$oldLoc}] ke [{$newLoc}]");
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
        $ids = json_decode($request->selected_items, true);
        foreach ($ids as $id) {
            $original = Item::find($id);
            if($original) {
                $clone = $original->replicate();
                $clone->nama = $original->nama . ' (Klon)';
                $clone->sku = $this->generateUniqueSku($clone->nama);
                $clone->save();
            }
        }
        $this->logActivity(null, count($ids), "BULK CLONE: Menduplikasi " . count($ids) . " item.");
        return redirect()->back()->with('success', 'Data dikloning.');
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
        $ids = json_decode($request->selected_items, true);
        $field = $request->filled('harga_jual_value') ? 'Harga Jual' : 'Satuan/Note';
        Item::whereIn('id', $ids)->update($request->only(['harga_jual', 'harga_beli', 'satuan', 'stok_minimum', 'note']));
        $this->logActivity(null, count($ids), "Bulk Update {$field} pada " . count($ids) . " item.");
        return redirect()->back()->with('success', 'Update massal berhasil.');
    }

    /**
     * [BARU] Bulk Actions & Audit Log
     */
    public function bulkDelete(Request $request) {
        $ids = json_decode($request->selected_items, true);
        $count = count($ids);
        Item::whereIn('id', $ids)->delete();
        $this->logActivity(null, 0, "BULK DELETE: Menghapus {$count} item sekaligus.");
        return redirect()->back()->with('success', 'Data dihapus.');
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

    /**
     * [BARU/DITIMPA] Helper Log: Menjamin item_id nullable
     */
    private function logActivity($itemId, $qty, $note) {
        Transaksi::create([
            'item_id' => $itemId ?: null,
            'jumlah_produksi' => (float)$qty,
            'tanggal_produksi' => now(),
            'catatan' => $note
        ]);
    }

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
