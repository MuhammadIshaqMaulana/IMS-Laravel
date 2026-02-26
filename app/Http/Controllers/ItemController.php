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
use Illuminate\Support\Facades\Auth; // Tambahkan ini


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
     * [DITIMPA] Create: Cukup load Folder
     */
    public function create()
    {
        $allFolders = Folder::orderBy('nama')->get();
        return view('item.create', compact('allFolders'));
    }

    /**
     * [DITIMPA] Store: Mengolah materials_data menjadi array asli atau NULL
     */
    public function store(Request $request)
    {
        $request->validate(['nama' => 'required|string|max:100', 'type' => 'required|in:item,folder']);
        $userId = Auth::id();
        $execFolderId = $request->folder_id ?: 0;

        DB::beginTransaction();
        try {
            if ($request->type === 'folder') {
                if (Folder::where('nama', $request->nama)->exists()) return redirect()->back()->with('error', "Folder '{$request->nama}' sudah ada.");

                $folder = Folder::create(['nama' => $request->nama, 'parent_id' => $request->folder_id]);
                $folder->updatePath();

                // [LOG BARU] Create Folder
                $this->logActivity(null, $execFolderId, "({$userId}) membuat folder {{$folder->id}} di folder [{$execFolderId}]");

            } else {
                $decodedMaterials = json_decode($request->materials_data, true);
                $finalMaterials = (!empty($decodedMaterials)) ? $decodedMaterials : null;
                $variants = json_decode($request->variant_dimensions, true);

                if (empty($variants)) {
                    // Create Single
                    $item = Item::create([
                        'nama' => $request->nama,
                        'sku' => ($finalMaterials) ? 'BOM-' . strtoupper(Str::random(6)) : $this->generateUniqueSku($request->nama),
                        'satuan' => $request->satuan,
                        'stok_saat_ini' => ($finalMaterials) ? 0 : ($request->stok_saat_ini ?? 0),
                        'stok_minimum' => $request->stok_minimum ?? 0,
                        'harga_jual' => $request->harga_jual ?? 0,
                        'harga_beli' => $request->harga_beli ?? 0,
                        'folder_id' => $request->folder_id,
                        'materials' => $finalMaterials,
                        'note' => $request->note,
                        'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
                    ]);

                    // [LOG BARU] Create Single Item/BOM
                    $this->logActivity($item->id, $execFolderId, "({$userId}) membuat item/BOM <{$item->id}> di folder [{$execFolderId}]");

                } else {
                    // Create Multi Varian
                    $combinations = $this->generateCombinations($variants);
                    foreach ($combinations as $combo) {
                        $vName = $request->nama . ' - ' . implode(', ', $combo);
                        Item::create([
                            'nama' => $vName,
                            'sku' => $this->generateUniqueSku($vName),
                            'satuan' => $request->satuan ?? 'pcs',
                            'stok_saat_ini' => 0,
                            'folder_id' => $request->folder_id,
                            'materials' => $finalMaterials,
                        ]);
                    }
                    // [LOG BARU] Create Multi Varian
                    $countV = count($combinations);
                    $this->logActivity(null, $execFolderId, "({$userId}) membuat item/BOM dengan \"{$countV}\" varian di folder [{$execFolderId}]");
                }
            }
            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $request->folder_id])->with('success', 'Berhasil dibuat.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', $e->getMessage()); }
    }

    public function show(Item $item) {
        $history = $item->transaksis()->orderBy('created_at', 'desc')->get();
        // $stats = [
        //     'total_in' => $item->transaksis()->where('jumlah_produksi', '>', 0)->sum('jumlah_produksi'),
        //     'total_out' => abs($item->transaksis()->where('jumlah_produksi', '<', 0)->sum('jumlah_produksi')),
        // ];
        return view('item.show', compact('item', 'history'));
    }

    /**
     * [DITIMPA] Edit: Load Folder + Mapping Nama Material untuk Select2
     */
    public function edit(Item $item) {
        $allFolders = Folder::orderBy('nama')->get();

        // Ambil nama material yang sudah ada agar Select2 tidak tampil ID angka doang
        $materialNames = [];
        if ($item->is_bom && is_array($item->materials)) {
            $mIds = array_column($item->materials, 'item_id');
            $materialNames = Item::whereIn('id', $mIds)->pluck('nama', 'id')->toArray();
        }

        return view('item.edit', compact('item', 'allFolders', 'materialNames'));
    }

    /**
     * [DITIMPA] Update: Logika pencatatan yang presisi (Nama Lama, Stok vs Material).
     */
    public function update(Request $request, Item $item)
    {
        // 1. Proteksi Duplikasi Nama
        if (Item::where('nama', $request->nama)->where('id', '!=', $item->id)->exists()) {
            return redirect()->back()->with('error', "Nama '{$request->nama}' sudah digunakan item lain.");
        }

        $userId = Auth::id();
        $oldFolderId = $item->folder_id ?: 0;
        $newFolderId = $request->folder_id !== null ? (int)$request->folder_id : 0;

        DB::beginTransaction();
        try {
            // A. LOGIKA PINDAH FOLDER (Simbol -- untuk tujuan)
            if ($oldFolderId !== $newFolderId) {
                if ($item->folder_id) Folder::where('id', $item->folder_id)->decrement('items_count');
                if ($request->folder_id) Folder::where('id', $request->folder_id)->increment('items_count');

                $this->logActivity($item->id, $oldFolderId, "({$userId}) memindahkan item/BOM ||{$item->id}|| dari folder [{$oldFolderId}] ke folder -{$newFolderId}-");
            }

            // B. LOGIKA UPDATE NAMA (Simbol ` untuk coret nama lama)
            if ($request->nama !== $item->nama) {
                $this->logActivity($item->id, $newFolderId, "({$userId}) update nama dari item/BOM `{$item->nama}` menjadi ~{$request->nama}~ di folder [{$newFolderId}]");
            }

            // C. LOGIKA UPDATE MATERIAL (BOM ONLY) - Dicek hanya jika item asal adalah BOM
            if ($item->is_bom && $request->has('materials_data')) {
                $newMatsRaw = json_decode($request->materials_data, true);
                $finalNewMats = (!empty($newMatsRaw)) ? $newMatsRaw : null;

                if (json_encode($item->materials) !== json_encode($finalNewMats)) {
                    $this->logActivity($item->id, $newFolderId, "({$userId}) update material dari BOM ||{$item->id}|| di folder [{$newFolderId}]");
                }
            }

            // D. LOGIKA UPDATE STOK (NON-BOM ONLY atau BOM yang berubah jadi Non-BOM)
            // Hanya mencatat jika stok_saat_ini berubah dan item asalnya bukan BOM aktif
            if (!$item->is_bom && $request->has('stok_saat_ini')) {
                if ((float)$request->stok_saat_ini !== (float)$item->stok_saat_ini) {
                    $diff = (float)$request->stok_saat_ini - (float)$item->stok_saat_ini;
                    $prefix = $diff >= 0 ? "+$diff" : "$diff";
                    $this->logActivity($item->id, $newFolderId, "({$userId}) update stok dari item ||{$item->id}|| menjadi '{$prefix}' di folder [{$newFolderId}]");
                }
            }

            // E. LOGIKA EDITABLE FIELDS LAINNYA
            $fields = [
                'satuan' => 'satuan',
                'stok_minimum' => 'stok minimum',
                'harga_jual' => 'harga jual',
                'harga_beli' => 'harga beli',
                'note' => 'note'
            ];
            foreach ($fields as $dbCol => $label) {
                if ($request->has($dbCol) && $request->$dbCol != $item->$dbCol) {
                    $this->logActivity($item->id, $newFolderId, "({$userId}) update {$label} dari item/BOM ||{$item->id}|| menjadi ~{$request->$dbCol}~ di folder [{$newFolderId}]");
                }
            }

            // F. EKSEKUSI DATA
            $data = $request->only(['nama', 'folder_id', 'satuan', 'stok_minimum', 'harga_jual', 'harga_beli', 'note']);

            if ($item->is_bom) {
                $mats = json_decode($request->materials_data, true);
                $data['materials'] = (!empty($mats)) ? $mats : null;
                // Jika dikosongkan, dia jadi item biasa & boleh punya stok awal
                if (empty($mats)) { $data['stok_saat_ini'] = $request->stok_saat_ini ?? 0; }
            } else {
                $data['stok_saat_ini'] = $request->stok_saat_ini;
                $data['materials'] = null; // Item biasa gak boleh punya material
            }

            if ($request->has('tags_input')) {
                $data['tags'] = array_filter(array_map('trim', explode(',', $request->tags_input))) ?: null;
            }

            $item->update($data);
            DB::commit();
            return redirect()->route('item.show', $item->id)->with('success', 'Data berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal update: ' . $e->getMessage());
        }
    }


    /**
     * [DITIMPA] searchAjax: Filter item yang murni BUKAN BOM untuk jadi bahan baku
     */
    public function searchAjax(Request $request) {
        $q = $request->q;
        return Item::where('nama', 'LIKE', "%$q%")
            ->where(function($query) {
                $query->whereNull('materials')
                      ->orWhere('materials', '[]'); // Pastikan benar-benar bukan BOM
            })
            ->limit(15)
            ->get(['id', 'nama as text']);
    }

    /**
     * [DITIMPA] MEGA OPTIMIZED IMPORT: Timer, Validasi Lengkap (C1-C3) & Fix Counter
     */
    public function importCsv(Request $request)
    {
        ini_set('max_execution_time', 1800);
        ini_set('memory_limit', '2048M');
        $startTime = microtime(true);

        $request->validate(['file_csv' => 'required|mimes:csv,txt']);
        $filePath = $request->file('file_csv')->getRealPath();
        $originalFilename = $request->file('file_csv')->getClientOriginalName();
        $filenameOnly = pathinfo($originalFilename, PATHINFO_FILENAME);

        // Cari nama folder unik (seperti Windows Explorer)
        $folderName = $filenameOnly;
        $counter = 1;
        while (Folder::where('nama', $folderName)->exists()) {
            $counter++;
            $folderName = "{$filenameOnly}({$counter})";
        }

        $staging = 'temp_imp_' . Str::random(5);
        $pathForSql = str_replace('\\', '/', $filePath);

        // Buat tabel staging temporary
        DB::statement("CREATE TEMPORARY TABLE $staging (
            nomor INT PRIMARY KEY,
            nama VARCHAR(255) COLLATE utf8mb4_unicode_ci,
            satuan VARCHAR(50),
            stok_saat_ini VARCHAR(50),
            stok_minimum VARCHAR(50),
            harga_jual VARCHAR(50),
            harga_beli VARCHAR(50),
            note TEXT,
            materials TEXT,
            tags TEXT,
            INDEX idx_nama (nama)
        ) ENGINE=InnoDB");

        try {
            DB::connection()->getpdo()->exec("LOAD DATA LOCAL INFILE '$pathForSql' INTO TABLE $staging FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' LINES TERMINATED BY '\\r\\n' IGNORE 1 LINES");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Format CSV salah: " . $e->getMessage());
        }

        // --- VALIDASI SUPER CEPAT (Fail-Fast) ---
        // A. Cek Nama Duplikat (CSV vs CSV)
        $dupNameCsv = DB::table($staging)->select('nama')->groupBy('nama')->havingRaw('COUNT(*) > 1')->value('nama');
        if ($dupNameCsv) {
            $elapsed = round(microtime(true) - $startTime, 2);
            return redirect()->back()->with('error', "Import Batal: Nama '$dupNameCsv' duplikat di dalam file CSV. [Pengecekan: {$elapsed} detik]");
        }

        // B. Cek Nama Duplikat (CSV vs Database)
        $existsInDb = DB::table($staging)
            ->join('items', "$staging.nama", '=', 'items.nama')
            ->whereNull('items.deleted_at')
            ->select("$staging.nama")
            ->first();
        if ($existsInDb) {
            $elapsed = round(microtime(true) - $startTime, 2);
            return redirect()->back()->with('error', "Import Batal: Nama '{$existsInDb->nama}' sudah ada di database. [Pengecekan: {$elapsed} detik]");
        }

        // C. Validasi BOM Rule (Recursive CTE)
        $mapTable = 'temp_bom_check_' . Str::random(5);
        DB::statement("CREATE TEMPORARY TABLE $mapTable (parent INT, child INT, INDEX (parent, child)) ENGINE=InnoDB");

        DB::statement("INSERT INTO $mapTable (parent, child)
            WITH RECURSIVE split_m AS (
                SELECT nomor as p, SUBSTRING_INDEX(materials, ',', 1) as part,
                       IF(LOCATE(',', materials) > 0, SUBSTRING(materials, LOCATE(',', materials) + 1), NULL) as rest
                FROM $staging WHERE materials IS NOT NULL AND materials <> ''
                UNION ALL
                SELECT p, SUBSTRING_INDEX(rest, ',', 1),
                       IF(LOCATE(',', rest) > 0, SUBSTRING(rest, LOCATE(',', rest) + 1), NULL)
                FROM split_m WHERE rest IS NOT NULL
            )
            SELECT DISTINCT p, CAST(SUBSTRING_INDEX(TRIM(SUBSTRING_INDEX(part, '(', 1)), '(', 1) AS UNSIGNED) FROM split_m");

        // C1. Rule: Self-referencing
        $selfBom = DB::table($mapTable)->whereRaw("parent = child")->first();
        if ($selfBom) {
            $elapsed = round(microtime(true) - $startTime, 2);
            return redirect()->back()->with('error', "Import Batal: Item nomor {$selfBom->parent} mereferensikan dirinya sendiri sebagai material. [Pengecekan: {$elapsed}s]");
        }

        // C2. Rule: Material tidak boleh merupakan BOM (di file yang sama)
        $bomAsMaterial = DB::table($mapTable)
            ->join($staging, "$mapTable.child", '=', "$staging.nomor")
            ->whereRaw("$staging.materials IS NOT NULL AND $staging.materials <> ''")
            ->select("$mapTable.parent", "$mapTable.child")
            ->first();
        if ($bomAsMaterial) {
            $elapsed = round(microtime(true) - $startTime, 2);
            return redirect()->back()->with('error', "Import Batal: Item #{$bomAsMaterial->parent} mengandung material #{$bomAsMaterial->child} yang juga merupakan BOM. [Pengecekan: {$elapsed}s]");
        }

        // C3. Rule: Material berulang (Validasi duplikat material asli)
        $dupMat = DB::select("
            SELECT parent, child FROM (
                SELECT nomor as parent,
                       CAST(SUBSTRING_INDEX(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(materials, ',', n), ',', -1)), '(', 1) AS UNSIGNED) as child
                FROM $staging
                CROSS JOIN (
                    SELECT a.N + b.N * 10 + 1 as n
                    FROM (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
                    CROSS JOIN (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
                ) numbers
                WHERE materials IS NOT NULL
                  AND materials <> ''
                  AND n <= (LENGTH(materials) - LENGTH(REPLACE(materials, ',', '')) + 1)
            ) AS sub
            GROUP BY parent, child
            HAVING COUNT(*) > 1
            LIMIT 1
        ");
        if (!empty($dupMat)) {
            $elapsed = round(microtime(true) - $startTime, 2);
            return redirect()->back()->with('error', "Import Batal: Item #{$dupMat[0]->parent} memiliki material #{$dupMat[0]->child} yang dimasukkan berulang kali. [Pengecekan: {$elapsed}s]");
        }

        // --- PROSES INSERT MASSAL ---
        DB::beginTransaction();
        try {
            // 1. Buat Foldernya dulu
            $folder = Folder::create(['nama' => $folderName, 'parent_id' => $request->folder_id]);
            $folder->updatePath();

            $now = now();
            DB::statement("SET SESSION unique_checks=0");
            DB::statement("SET SESSION foreign_key_checks=0");

            // 2. Insert Items via Raw SQL
            DB::statement("INSERT INTO items (nama, sku, satuan, stok_saat_ini, stok_minimum, harga_jual, harga_beli, note, folder_id, created_at, updated_at)
                SELECT TRIM(nama), CONCAT('SKU-', nomor, '-', UNIX_TIMESTAMP()), satuan,
                CASE WHEN (materials IS NOT NULL AND materials <> '') THEN 0 ELSE IFNULL(stok_saat_ini, 0) END,
                IFNULL(stok_minimum, 0), IFNULL(harga_jual, 0), IFNULL(harga_beli, 0), note, {$folder->id}, '$now', '$now'
                FROM $staging WHERE nama <> ''");

            $this->finalizeImportBomOneShot($staging, $folder->id);

            // 3. Hitung jumlah data yang masuk dari staging
            $totalIn = DB::table($staging)->where('nama', '<>', '')->count();

            // 4. Update items_count secara manual di tabel folders (PENTING)
            // Kita pakai update langsung ke DB agar performa tetap kencang
            DB::table('folders')->where('id', $folder->id)->update(['items_count' => $totalIn]);

            // 5. Urus BOM (Relasi)
            $this->finalizeImportBomOneShot($staging, $folder->id);

            DB::commit();

            // Log Aktivitas
            $userId = Auth::id();
            $msg = "({$userId}) mengimport {$totalIn} data baru ke folder [{$folder->id}]";
            $this->logActivity(null, $folder->id, $msg);

            DB::statement("SET SESSION unique_checks=1");
            DB::statement("SET SESSION foreign_key_checks=1");

            $elapsed = round(microtime(true) - $startTime, 2);
            return redirect()->back()->with('success', "Import $totalIn data Berhasil ke folder '$folderName' dalam waktu $elapsed detik.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Import Error: " . $e->getMessage());
            return redirect()->back()->with('error', "Gagal: " . $e->getMessage());
        } finally {
            DB::statement("DROP TEMPORARY TABLE IF EXISTS $staging");
            DB::statement("DROP TEMPORARY TABLE IF EXISTS $mapTable");
        }
    }

    /**
     * [DITIMPA] Move Item/Folder: Logic update folder_id dan counter items_count
     */
    public function move(Request $request) {
        $userId = Auth::id();
        $target = ($request->target_type === 'item') ? Item::findOrFail($request->id) : Folder::findOrFail($request->id);
        $oldFolderId = $target->folder_id ?: 0;
        $newFolderId = $request->folder_id ?: 0;

        if ($oldFolderId == $newFolderId) return redirect()->back()->with('info', 'Lokasi sama.');

        DB::beginTransaction();
        try {
            if ($request->target_type === 'item') {
                $target->update(['folder_id' => $request->folder_id ?: null]);
                if ($oldFolderId) Folder::where('id', $oldFolderId)->decrement('items_count');
                if ($newFolderId) Folder::where('id', $newFolderId)->increment('items_count');

                // [LOG BARU] Move Single Item
                $this->logActivity($target->id, $oldFolderId, "({$userId}) memindahkan item/BOM <{$target->id}> dari folder [{$oldFolderId}] ke folder -{$newFolderId}-");
            } else {
                $target->update(['parent_id' => $request->folder_id ?: null]);
                $target->updatePath();
                $this->syncDescendantPaths($target);

                // [LOG BARU] Move Folder
                $this->logActivity(null, $oldFolderId, "({$userId}) memindahkan folder {{$target->id}} dari folder [{$oldFolderId}] ke folder -{$newFolderId}-");
            }
            DB::commit(); return redirect()->back()->with('success', 'Berhasil dipindahkan.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', $e->getMessage()); }
    }

    /**
     * [DITIMPA/LENGKAP] finalizeImportBomOneShot: Pemrosesan JSON BOM Massal
     */
    private function finalizeImportBomOneShot($staging, $folderId)
    {
        // Ambil mapping Nama ke ID yang baru diinsert
        $mapping = DB::table('items')
            ->join($staging, 'items.nama', '=', "$staging.nama")
            ->where('items.folder_id', $folderId)
            ->pluck('items.id', "$staging.nomor")
            ->toArray();

        $bomRows = DB::table($staging)
            ->whereNotNull('materials')->where('materials', '<>', '')
            ->select('nomor', 'materials')->get();

        if ($bomRows->isEmpty()) return;

        $bomNomors = $bomRows->pluck('nomor')->toArray();
        $mapTable = 'temp_bom_map_' . Str::random(5);
        DB::statement("CREATE TEMPORARY TABLE $mapTable (item_id INT PRIMARY KEY, final_json JSON)");

        $batch = [];
        foreach ($bomRows as $row) {
            $mParts = array_map('trim', explode(',', $row->materials));
            $finalM = [];
            foreach ($mParts as $p) {
                preg_match('/^(\d+)(?:\(([\d.]+)\))?$/', $p, $matches);
                if ($matches) {
                    $mNum = (int)$matches[1];
                    $mQty = isset($matches[2]) ? (float)$matches[2] : 1.0;

                    if (in_array($mNum, $bomNomors)) {
                        throw new \Exception("Aturan BOM Dilanggar: Item nomor {$row->nomor} menggunakan material nomor $mNum yang merupakan sesama BOM.");
                    }

                    if (isset($mapping[$mNum])) {
                        $finalM[] = ['item_id' => $mapping[$mNum], 'qty' => $mQty];
                    }
                }
            }

            if ($finalM && isset($mapping[$row->nomor])) {
                $batch[] = ['item_id' => $mapping[$row->nomor], 'final_json' => json_encode($finalM)];
            }

            if (count($batch) >= 2000) {
                DB::table($mapTable)->insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) DB::table($mapTable)->insert($batch);

        DB::statement("UPDATE items JOIN $mapTable ON items.id = $mapTable.item_id SET items.materials = $mapTable.final_json");
        DB::statement("DROP TEMPORARY TABLE IF EXISTS $mapTable");
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
        $userId = Auth::id();
        $ids = json_decode($request->selected_items, true);
        $firstItem = Item::find($ids[0]);
        $execFolderId = $firstItem ? $firstItem->folder_id : 0;

        foreach ($ids as $id) {
            $original = Item::find($id);
            if($original) {
                $clone = $original->replicate();
                $clone->nama = $original->nama . ' (Klon)';
                $clone->sku = $this->generateUniqueSku($clone->nama);
                $clone->save(); // Trigger created observer for items_count
            }
        }
        // [LOG BARU] Bulk Clone
        $count = count($ids);
        $this->logActivity(null, $execFolderId, "({$userId}) clone \"{$count}\" item di folder [{$execFolderId}]");
        return redirect()->back()->with('success', 'Data dikloning.');
    }

    /**
     * [DITIMPA] Bulk Update Quantity: Format catatan sesuai request
     */
    public function bulkUpdateQuantity(Request $request) {
        $userId = Auth::id();
        $ids = json_decode($request->selected_items, true);
        $change = (float) $request->qty_adjustment;
        $prefix = $change >= 0 ? "+$change" : "$change";

        $firstItem = Item::find($ids[0]);
        $execFolderId = $firstItem ? $firstItem->folder_id : 0;

        Item::whereIn('id', $ids)->whereNull('materials')->increment('stok_saat_ini', $change);

        // [LOG BARU] Bulk Update Stok
        $count = count($ids);
        $this->logActivity(null, $execFolderId, "({$userId}) update stok dari \"{$count}\" item menjadi '{$prefix}' di folder [{$execFolderId}]");

        return redirect()->back()->with('success', 'Stok massal diperbarui.');
    }

    /**
     * [DITIMPA] Bulk Update: Memperbaiki mapping field (_value) dan update folder massal.
     */
    public function bulkUpdate(Request $request) {
        $userId = Auth::id();
        $ids = json_decode($request->selected_items, true);
        $firstItem = Item::find($ids[0]);
        $execFolderId = $firstItem ? $firstItem->folder_id : 0;
        $count = count($ids);

        DB::beginTransaction();
        try {
            // Handle Pindah Folder Massal
            if ($request->filled('folder_id_value')) {
                $newF = $request->folder_id_value === 'NULL' ? null : $request->folder_id_value;
                $newFId = $newF ?: 0;
                foreach(Item::whereIn('id', $ids)->get() as $item) {
                    if($item->folder_id != $newF) {
                        if($item->folder_id) Folder::where('id', $item->folder_id)->decrement('items_count');
                        if($newF) Folder::where('id', $newF)->increment('items_count');
                        $item->update(['folder_id' => $newF]);
                    }
                }
                $this->logActivity(null, $execFolderId, "({$userId}) memindahkan \"{$count}\" item/BOM dari folder [{$execFolderId}] ke folder -{$newFId}-");
            }

            // Handle Tags Massal
            if ($request->filled('tags_input_value')) {
                $tagVal = $request->tags_input_value;
                $newTagsArr = array_filter(array_map('trim', explode(',', $tagVal)));
                foreach(Item::whereIn('id', $ids)->get() as $item) {
                    $oldTags = is_array($item->tags) ? $item->tags : [];
                    $item->update(['tags' => array_unique(array_merge($oldTags, $newTagsArr))]);
                }
                $this->logActivity(null, $execFolderId, "({$userId}) menambahkan tag dari \"{$count}\" item yaitu ~{$tagVal}~ di folder [{$execFolderId}]");
            }

            // Handle Field Lainnya
            $map = ['harga_beli_value' => 'harga beli', 'harga_jual_value' => 'harga jual', 'min_level_value' => 'stok minimum', 'satuan_value' => 'satuan', 'note_value' => 'note'];
            foreach($map as $key => $label) {
                if($request->filled($key)) {
                    $dbField = ($key == 'min_level_value') ? 'stok_minimum' : str_replace('_value', '', $key);
                    Item::whereIn('id', $ids)->update([$dbField => $request->$key]);
                    $this->logActivity(null, $execFolderId, "({$userId}) update {$label} dari \"{$count}\" item/BOM menjadi ~{$request->$key}~ di folder [{$execFolderId}]");
                }
            }

            DB::commit(); return redirect()->back()->with('success', 'Bulk update berhasil.');
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', $e->getMessage()); }
    }

    /**
     * [BARU] Bulk Actions & Audit Log
     */
    public function bulkDelete(Request $request) {
        $userId = Auth::id();
        $ids = json_decode($request->selected_items, true);
        $firstItem = Item::find($ids[0]);
        $execFolderId = $firstItem ? $firstItem->folder_id : 0;
        $count = count($ids);

        Item::whereIn('id', $ids)->delete();

        // [LOG BARU] Bulk Delete
        $this->logActivity(null, $execFolderId, "({$userId}) menghapus \"{$count}\" item/BOM di folder [{$execFolderId}]");
        return redirect()->back()->with('success', 'Data dihapus.');
    }

    // --- HELPERS ---

    /**
     * [DITIMPA] Helper Save Single: Menerima $materials yang sudah jadi array/null
     */
    private function saveSingleItem($request, $name, $folderId, $materials) {
        $item = Item::create([
            'nama' => $name,
            'sku' => ($materials) ? 'BOM-' . strtoupper(Str::random(6)) : $this->generateUniqueSku($name),
            'satuan' => $request->satuan,
            'stok_saat_ini' => ($materials) ? 0 : ($request->stok_saat_ini ?? 0),
            'stok_minimum' => $request->stok_minimum ?? 0,
            'harga_jual' => $request->harga_jual ?? 0,
            'harga_beli' => $request->harga_beli ?? 0,
            'folder_id' => $folderId,
            'materials' => $materials, // Ini sekarang murni Array atau NULL
            'note' => $request->note,
            'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
        ]);
        if (!$materials && $item->stok_saat_ini > 0) $this->logActivity($item->id, $item->stok_saat_ini, 'Saldo awal');
    }


    /**
     * [DITIMPA] Helper Variants: Menerima $materials yang sudah jadi array/null
     */
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
                'materials' => $materials, // Array atau NULL
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
    private function logActivity($itemId, $folderId, $note) {
        \App\Models\Transaksi::create([
            'item_id'   => $itemId ?: null,
            'folder_id' => $folderId ?: null, // Folder ID asal/eksekusi
            'catatan'   => $note,
        ]);
    }

    public function updateQuantity(Request $request, Item $item) {
        $userId = Auth::id();
        $execFolderId = $item->folder_id ?: 0;
        $qty = $request->qty;
        $prefix = $qty >= 0 ? "+$qty" : "$qty";

        $item->increment('stok_saat_ini', $qty);

        // [LOG BARU] Update Stok Single
        $this->logActivity($item->id, $execFolderId, "({$userId}) update stok dari item <{$item->id}> menjadi '{$prefix}' di folder [{$execFolderId}]");
        return redirect()->back()->with('success', 'Stok diperbarui.');
    }

    /**
     * Update Folder: Validasi Nama Duplikat
     */
    public function updateFolder(Request $request, Folder $folder) {
        $userId = Auth::id();
        $execFolderId = $folder->parent_id ?: 0;
        if (Folder::where('nama', $request->nama)->where('id', '!=', $folder->id)->exists()) return redirect()->back()->with('error', "Nama folder sudah ada.");

        $oldName = $folder->nama;
        $folder->update(['nama' => $request->nama]);

        // [LOG BARU] Update Nama Folder
        $this->logActivity(null, $execFolderId, "({$userId}) update nama folder {{$folder->id}} menjadi ~{$request->nama}~ di folder [{$execFolderId}]");
        return redirect()->back()->with('success', 'Folder diperbarui.');
    }

    public function destroy(Item $item) {
        $userId = Auth::id();
        $execFolderId = $item->folder_id ?: 0;
        $itemId = $item->id;

        $item->delete(); // Trigger deleted observer

        // [LOG BARU] Delete Single Item
        $this->logActivity(null, $execFolderId, "({$userId}) menghapus item/BOM <{$itemId}> di folder [{$execFolderId}]");
        return redirect()->route('item.index');
    }

    /**
     * DELETE FOLDER RECURSIVE: Menghapus folder, sub-folder, dan semua item di dalamnya.
     */
    public function destroyFolder(Folder $folder)
    {
        $userId = Auth::id();
        $execFolderId = $folder->parent_id ?: 0;
        $fId = $folder->id;

        DB::beginTransaction();
        try {
            $descendantIds = Folder::where('path', 'LIKE', $folder->path . '%')->pluck('id');
            Item::whereIn('folder_id', $descendantIds)->delete();
            Folder::whereIn('id', $descendantIds)->delete();

            // [LOG BARU] Delete Folder
            $this->logActivity(null, $execFolderId, "({$userId}) menghapus folder {{$fId}} beserta semua isinya di folder [{$execFolderId}]");

            DB::commit();
            return redirect()->route('item.index')->with('success', "Folder dihapus.");
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', 'Gagal.'); }
    }
}
