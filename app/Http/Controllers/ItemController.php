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
     * Tampilan Utama: Explorer navigasi, Search Global, dan Dual Counter.
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

        // Mapping nama material untuk preview di UI Card
        $materialIds = [];
        foreach ($items as $item) {
            if ($item->is_bom && is_array($item->materials)) {
                foreach ($item->materials as $m) {
                    $materialIds[] = $m['item_id'] ?? null;
                }
            }
        }
        $materialMap = Item::whereIn('id', array_filter(array_unique($materialIds)))->pluck('nama', 'id');

        $allFolders = Folder::orderBy('nama')->get();
        return view('item.index', compact('items', 'subFolders', 'currentFolder', 'allFolders', 'breadcrumbs', 'search', 'materialMap'));
    }

    public function create()
    {
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

    public function update(Request $request, Item $item)
    {
        $request->validate(['nama' => 'required|string|max:100']);
        $isBomActive = $request->is_bom === 'on';
        $materials = $isBomActive ? json_decode($request->materials_data, true) : null;
        $oldStock = (float) $item->stok_saat_ini;
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
            'harga_beli' => floor($request->harga_beli ?? 0),
            'folder_id' => $request->folder_id,
            'note' => $request->note,
            'materials' => $materials,
            'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
        ]);
        return redirect()->route('item.show', $item->id)->with('success', 'Data diperbarui.');
    }

    /**
     * BULK IMPORT CSV: Sesuai Aturan "Nomor" Sementara dan BOM Guard.
     */
    public function importCsv(Request $request)
    {
        $request->validate(['file_csv' => 'required|mimes:csv,txt']);
        $file = $request->file('file_csv');
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $handle = fopen($file->getRealPath(), 'r');
        $rawHeader = fgetcsv($handle, 1000, ',');
        $header = array_map('trim', $rawHeader);

        // 1. Validasi Header Tabel
        $requiredHeaders = ['nomor', 'nama', 'satuan', 'stok_saat_ini', 'stok_minimum', 'harga_jual', 'harga_beli', 'note', 'materials', 'tags'];
        if ($header !== $requiredHeaders) {
            fclose($handle);
            return redirect()->back()->with('error', 'Gagal: Struktur table header tidak sesuai. Pastikan urutan dan nama kolom tepat.');
        }

        $rows = [];
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if (count($header) == count($data)) {
                $rows[] = array_combine($header, $data);
            }
        }
        fclose($handle);

        // --- PRE-VALIDATION: Cek Duplikat ---
        $csvNames = []; $csvNomors = []; $bomNomors = [];
        foreach ($rows as $r) { if (!empty($r['materials'])) $bomNomors[] = trim($r['nomor']); }

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $nama = trim($row['nama']); $nomor = trim($row['nomor']);
            if (empty($nama)) return redirect()->back()->with('error', "Gagal: Nama kosong di baris $line.");
            if (in_array($nama, $csvNames)) return redirect()->back()->with('error', "Gagal: Nama '$nama' duplikat dalam file.");
            if (Item::where('nama', $nama)->exists()) return redirect()->back()->with('error', "Gagal: Nama '$nama' sudah ada di database.");
            if (in_array($nomor, $csvNomors)) return redirect()->back()->with('error', "Gagal: Nomor '$nomor' duplikat dalam file.");

            $csvNames[] = $nama; $csvNomors[] = $nomor;

            if (!empty($row['materials'])) {
                $mInput = array_map('trim', explode(',', $row['materials']));
                $seenInRow = [];
                foreach ($mInput as $part) {
                    preg_match('/^(\d+)(?:\(([\d.]+)\))?$/', $part, $matches);
                    if (!$matches) return redirect()->back()->with('error', "Gagal: Format material '$part' salah di baris $line.");
                    $mNum = $matches[1];
                    if ($mNum == $nomor) return redirect()->back()->with('error', "Gagal: Item '$nama' mereferensikan dirinya sendiri.");
                    if (in_array($mNum, $bomNomors)) return redirect()->back()->with('error', "Gagal: Material di baris $line merujuk ke BOM lain (#$mNum).");
                    if (in_array($mNum, $seenInRow)) return redirect()->back()->with('error', "Gagal: Material ganda #$mNum di baris $line.");
                    $seenInRow[] = $mNum;
                }
            }
        }

        // --- EXECUTION: Simpan Data ---
        DB::beginTransaction();
        try {
            // 2. Logika Nama Folder Unik (Auto-increment)
            $folderName = $originalFilename;
            $counter = 1;
            while (Folder::where('nama', $folderName)->where('parent_id', $request->folder_id)->exists()) {
                $counter++;
                $folderName = "{$originalFilename} ({$counter})";
            }

            $folder = Folder::create(['nama' => $folderName, 'parent_id' => $request->folder_id]);
            $folder->updatePath();

            $tempIdToRealId = []; $bomQueue = [];

            foreach ($rows as $row) {
                $isImportedAsBom = !empty($row['materials']);
                $item = Item::create([
                    'nama' => trim($row['nama']), 'sku' => $this->generateUniqueSku($row['nama']), 'satuan' => $row['satuan'] ?? 'pcs',
                    'stok_saat_ini' => $isImportedAsBom ? 0 : ($row['stok_saat_ini'] ?? 0), 'stok_minimum' => $row['stok_minimum'] ?? 0,
                    'harga_jual' => floor($row['harga_jual'] ?? 0), 'harga_beli' => floor($row['harga_beli'] ?? 0),
                    'note' => $row['note'] ?? null, 'tags' => !empty($row['tags']) ? array_map('trim', explode(',', $row['tags'])) : null,
                    'folder_id' => $folder->id
                ]);
                $tempIdToRealId[trim($row['nomor'])] = $item->id;
                if ($isImportedAsBom) { $bomQueue[] = ['item' => $item, 'raw' => $row['materials']]; }
                else if ($item->stok_saat_ini > 0) { $this->logActivity($item->id, $item->stok_saat_ini, "Import dari $originalFilename"); }
            }

            foreach ($bomQueue as $q) {
                $parts = array_map('trim', explode(',', $q['raw'])); $finalM = [];
                foreach ($parts as $p) {
                    preg_match('/^(\d+)(?:\(([\d.]+)\))?$/', $p, $matches);
                    $mNum = $matches[1]; $mQty = isset($matches[2]) ? (float)$matches[2] : 1.0;
                    if (isset($tempIdToRealId[$mNum])) { $finalM[] = ['item_id' => $tempIdToRealId[$mNum], 'qty' => $mQty]; }
                }
                $q['item']->update(['materials' => $finalM]);
            }

            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $folder->id])->with('success', "Import Berhasil ke folder: $folderName");
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', 'Gagal: ' . $e->getMessage()); }
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
                fputcsv($file, [$index + 1, $item->nama, $item->satuan, $item->calculated_stock, $item->stok_minimum, $item->harga_jual, $item->harga_beli, $item->note, implode(', ', $mParts), $item->tags ? implode(', ', $item->tags) : '']);
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
            'harga_beli' => floor($request->harga_beli ?? 0),
            'folder_id' => $folderId,
            'materials' => $materials,
            'note' => $request->note,
            'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
        ]);
        if (!$materials && $stokInput > 0) $this->logActivity($item->id, $stokInput, 'Saldo awal');
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
            // 1. Ambil semua ID folder keturunan menggunakan path (termasuk folder ini)
            $descendantFolders = Folder::where('path', 'LIKE', $folder->path . '%')->get();
            $folderIds = $descendantFolders->pluck('id');

            // 2. Soft delete semua Item yang ada di folder-folder tersebut
            // Ini akan mengisi kolom 'deleted_at' pada tabel items
            Item::whereIn('folder_id', $folderIds)->delete();

            // 3. Soft delete semua Folder tersebut
            // Ini akan mengisi kolom 'deleted_at' pada tabel folders
            Folder::whereIn('id', $folderIds)->delete();

            DB::commit();
            return redirect()->route('item.index')->with('success', "Folder '{$folder->nama}' dan seluruh isinya berhasil dipindahkan ke tempat sampah.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus folder: ' . $e->getMessage());
        }
    }
}
