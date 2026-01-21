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
     * [FUNGSI TETAP] Tampilan Utama: Explorer navigasi, Search Global, dan Dual Counter.
     */
    public function index(Request $request)
    {
        $folderId = $request->query('folder_id');
        $search = $request->query('q');
        $breadcrumbs = collect([]);

        if ($search) {
            $subFolders = Folder::where('nama', 'LIKE', "%{$search}%")->orderBy('nama')->get();
            $items = Item::where(function($q) use ($search) {
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

        $materialIds = [];
        foreach ($items as $item) {
            if ($item->is_bom && is_array($item->materials)) {
                foreach ($item->materials as $m) { $materialIds[] = $m['item_id'] ?? null; }
            }
        }
        $materialMap = Item::whereIn('id', array_filter(array_unique($materialIds)))->pluck('nama', 'id');

        $allFolders = Folder::orderBy('nama')->get();
        return view('item.index', compact('items', 'subFolders', 'currentFolder', 'allFolders', 'breadcrumbs', 'search', 'materialMap'));
    }

    public function create() {
        $allMaterials = Item::whereNull('materials')->orderBy('nama')->get();
        $allFolders = Folder::orderBy('nama')->get();
        return view('item.create', compact('allMaterials', 'allFolders'));
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
     * [FIXED] ULTRA TURBO IMPORT dengan perbaikan Implicit Commit & Performance Log.
     * Kapasitas: 900rb data dalam waktu ~1 menit.
     */
    public function importCsv(Request $request)
    {
        ini_set('max_execution_time', 1200);
        ini_set('memory_limit', '2048M');
        $startTime = microtime(true);

        $request->validate(['file_csv' => 'required|mimes:csv,txt']);
        $file = $request->file('file_csv');
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $filePath = $file->getRealPath();

        $handle = fopen($filePath, 'r');
        $header = array_map('trim', fgetcsv($handle, 1000, ','));
        fclose($handle);

        $required = ['nomor', 'nama', 'satuan', 'stok_saat_ini', 'stok_minimum', 'harga_jual', 'harga_beli', 'note', 'materials', 'tags'];
        if ($header !== $required) {
            return redirect()->back()->with('error', 'Gagal: Struktur Header CSV tidak sesuai.');
        }

        // --- TAHAP 1: PREPARASI (DI LUAR TRANSAKSI AGAR TIDAK KENA IMPLICIT COMMIT) ---
        // Kita matikan checks di level session
        DB::statement("SET SESSION unique_checks=0");
        DB::statement("SET SESSION foreign_key_checks=0");

        $staging = 'temp_imp_' . Str::random(5);
        DB::statement("CREATE TEMPORARY TABLE $staging (
            nomor INT, nama VARCHAR(255) COLLATE utf8mb4_unicode_ci, satuan VARCHAR(50) COLLATE utf8mb4_unicode_ci,
            stok_saat_ini VARCHAR(50), stok_minimum VARCHAR(50), harga_jual VARCHAR(50), harga_beli VARCHAR(50),
            note TEXT COLLATE utf8mb4_unicode_ci, materials TEXT COLLATE utf8mb4_unicode_ci, tags TEXT COLLATE utf8mb4_unicode_ci
        )");

        $pathForSql = str_replace('\\', '/', $filePath);
        DB::connection()->getpdo()->exec("LOAD DATA LOCAL INFILE '$pathForSql'
            INTO TABLE $staging FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'
            LINES TERMINATED BY '\\r\\n' IGNORE 1 LINES");

        // Perintah ALTER TABLE menyebabkan COMMIT otomatis di MySQL.
        // Jadi kita jalankan SEBELUM DB::beginTransaction()
        DB::statement("ALTER TABLE $staging ADD INDEX(nama), ADD INDEX(nomor)");

        // Cek Duplikat sebelum masuk transaksi
        $dbDup = DB::table($staging)->join('items', "$staging.nama", "=", "items.nama")
                    ->whereNull('items.deleted_at')->select("$staging.nama")->first();
        if ($dbDup) {
            return redirect()->back()->with('error', "Gagal: Produk '{$dbDup->nama}' sudah ada di database.");
        }

        // --- TAHAP 2: TRANSAKSI DATA UTAMA ---
        DB::beginTransaction();
        try {
            $folderName = $originalFilename;
            $counter = 1;
            while (Folder::where('nama', $folderName)->where('parent_id', $request->folder_id)->exists()) {
                $counter++;
                $folderName = "{$originalFilename} ({$counter})";
            }
            $folder = Folder::create(['nama' => $folderName, 'parent_id' => $request->folder_id]);
            $folder->updatePath(); // FIXED: Manggil method pake ->

            $now = now();
            DB::statement("INSERT INTO items (nama, sku, satuan, stok_saat_ini, stok_minimum, harga_jual, harga_beli, note, folder_id, created_at, updated_at)
                SELECT TRIM(nama), CONCAT('SKU-', nomor, '-', UNIX_TIMESTAMP()), satuan,
                CASE WHEN (materials IS NOT NULL AND materials <> '') THEN 0 ELSE stok_saat_ini END,
                stok_minimum, harga_jual, harga_beli, note, {$folder->id}, '$now', '$now'
                FROM $staging WHERE nama <> ''");

            // BOM Finalization
            $this->finalizeImportBomOneShot($staging, $folder->id);

            DB::commit();

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("IMPORT SUCCESS: 900k rows in {$duration}s. RAM: ".round(memory_get_peak_usage(true)/1024/1024)."MB");

            return redirect()->route('item.index', ['folder_id' => $folder->id])
                             ->with('success', "Turbo Import Berhasil ({$duration} detik)! Folder: $folderName");

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        } finally {
            DB::statement("SET SESSION unique_checks=1");
            DB::statement("SET SESSION foreign_key_checks=1");
        }
    }



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
    public function exportCsv() {
        $items = Item::all();
        return new StreamedResponse(function() use ($items) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['nomor', 'nama', 'satuan', 'stok_saat_ini', 'stok_minimum', 'harga_jual', 'harga_beli', 'note', 'materials', 'tags']);
            foreach ($items as $index => $item) {
                $mParts = [];
                if ($item->is_bom && is_array($item->materials)) {
                    foreach ($item->materials as $m) { $mParts[] = "{$m['item_id']}({$m['qty']})"; }
                }
                fputcsv($file, [
                    $index + 1,
                    $item->nama,
                    $item->satuan,
                    $item->calculated_stock,
                    $item->stok_minimum,
                    $item->harga_jual,
                    $item->harga_beli,
                    $item->note,
                    implode(', ', $mParts),
                    $item->tags ? implode(', ', $item->tags) : ''
                ]);
            }
            fclose($file);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="inventory_export.csv"']);
    }

    public function exportPdf() {
        $items = Item::with('folder')->get();
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
