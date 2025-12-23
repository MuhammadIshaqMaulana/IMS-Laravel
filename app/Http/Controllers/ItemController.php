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
     * Menampilkan daftar gabungan Folder dan Item berdasarkan lokasi saat ini.
     */
    public function index(Request $request)
    {
        $folderId = $request->query('folder_id');
        $breadcrumbs = collect([]);

        if ($folderId) {
            $currentFolder = Folder::with('parent')->findOrFail($folderId);
            $breadcrumbs = $currentFolder->getBreadcrumbs();

            $subFolders = Folder::where('parent_id', $folderId)->orderBy('nama')->get();
            $items = Item::where('folder_id', $folderId)->orderBy('nama')->paginate(15);
        } else {
            $currentFolder = null;
            $subFolders = Folder::whereNull('parent_id')->orderBy('nama')->get();
            $items = Item::whereNull('folder_id')->orderBy('nama')->paginate(15);
        }

        // Ambil semua folder untuk Sidebar dan Modal
        $allFolders = Folder::orderBy('nama')->get();

        return view('item.index', compact('items', 'subFolders', 'currentFolder', 'allFolders', 'breadcrumbs'));
    }

    /**
     * Form Create Item/BOM/Folder.
     */
    public function create()
    {
        // Hanya item murni (bukan BOM) yang bisa jadi bahan baku BOM
        $allMaterials = Item::whereNull('materials')->orderBy('nama')->get();
        $allFolders = Folder::orderBy('nama')->get();
        return view('item.create', compact('allMaterials', 'allFolders'));
    }

    /**
     * Store Universal: Folder, Item, BOM, & Generator Varian Mandiri.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:100',
            'type' => 'required|in:item,bom,folder',
            'folder_id' => 'nullable|exists:folders,id',
            'stok_saat_ini' => 'nullable|numeric',
        ]);

        $folderId = $request->folder_id;

        // GUARD: Cek duplikasi nama di lokasi yang sama (Cek di kedua tabel agar tidak ada nama yang sama)
        $nameExistsInFolders = Folder::where('nama', $request->nama)->where('parent_id', $folderId)->exists();
        $nameExistsInItems = Item::where('nama', $request->nama)->where('folder_id', $folderId)->exists();

        if ($nameExistsInFolders || $nameExistsInItems) {
            return redirect()->back()->withInput()->with('error', "Gagal: Nama '{$request->nama}' sudah ada di lokasi ini.");
        }

        DB::beginTransaction();
        try {
            if ($request->type === 'folder') {
                // SIMPAN SEBAGAI FOLDER
                $folder = Folder::create([
                    'nama' => $request->nama,
                    'parent_id' => $folderId
                ]);
                $folder->updatePath();
            } else {
                // LOGIKA ITEM / BOM / VARIAN
                $dimensions = json_decode($request->variant_dimensions, true) ?: [];

                if (empty($dimensions)) {
                    // Item Tunggal atau BOM
                    $this->createNewItem($request, $request->nama, $folderId);
                } else {
                    // GENERATOR VARIAN MANDIRI (Sesuai request: Tidak terikat parent_id)
                    $this->generateIndependentVariants($request, $dimensions, $folderId);
                }
            }

            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $folderId])->with('success', 'Data berhasil dibuat!');
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
        return view('item.show', compact('item', 'history'));
    }

    /**
     * Form Edit Item.
     */
    public function edit(Item $item)
    {
        $allFolders = Folder::orderBy('nama')->get();
        return view('item.edit', compact('item', 'allFolders'));
    }

    /**
     * Update Item dengan History Logging & Duplicate Check.
     */
    public function update(Request $request, Item $item)
    {
        $request->validate([
            'nama' => 'required|string|max:100',
            'stok_saat_ini' => 'nullable|numeric',
        ]);

        // GUARD: Cek duplikasi nama di lokasi tujuan (abaikan diri sendiri)
        $nameExists = Item::where('nama', $request->nama)
            ->where('folder_id', $request->folder_id)
            ->where('id', '!=', $item->id)
            ->exists() ||
            Folder::where('nama', $request->nama)
            ->where('parent_id', $request->folder_id)
            ->exists();

        if ($nameExists) {
            return redirect()->back()->withInput()->with('error', "Gagal: Nama sudah digunakan di lokasi tersebut.");
        }

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

    /**
     * Move Item atau Folder dengan Ancestry Guard & Duplicate Check.
     */
    public function move(Request $request)
    {
        $request->validate([
            'target_type' => 'required|in:item,folder',
            'id' => 'required|numeric',
            'folder_id' => 'nullable|exists:folders,id'
        ]);

        $targetFolderId = $request->folder_id;
        $id = $request->id;

        if ($request->target_type === 'folder') {
            $folder = Folder::findOrFail($id);

            // PROTEKSI 1: Cek Circular Reference
            if ($targetFolderId == $folder->id) {
                return redirect()->back()->with('error', 'Gagal: Tidak bisa memindahkan folder ke dalam dirinya sendiri.');
            }
            if ($targetFolderId && Folder::findOrFail($targetFolderId)->isDescendantOf($folder->id)) {
                return redirect()->back()->with('error', 'Gagal: Folder tidak boleh dipindahkan ke dalam subfolder-nya sendiri.');
            }

            // PROTEKSI 2: Cek Nama Duplikat di tujuan
            if (Folder::where('nama', $folder->nama)->where('parent_id', $targetFolderId)->where('id', '!=', $id)->exists() ||
                Item::where('nama', $folder->nama)->where('folder_id', $targetFolderId)->exists()) {
                return redirect()->back()->with('error', "Gagal: Nama '{$folder->nama}' sudah ada di lokasi tujuan.");
            }

            $folder->update(['parent_id' => $targetFolderId]);
            $folder->updatePath();

            // SINKRONISASI PATH KETURUNAN
            $this->syncDescendantPaths($folder);
        } else {
            $item = Item::findOrFail($id);

            // PROTEKSI: Cek Nama Duplikat di tujuan
            if (Item::where('nama', $item->nama)->where('folder_id', $targetFolderId)->where('id', '!=', $id)->exists() ||
                Folder::where('nama', $item->nama)->where('parent_id', $targetFolderId)->exists()) {
                return redirect()->back()->with('error', "Gagal: Nama '{$item->nama}' sudah ada di lokasi tujuan.");
            }

            $item->update(['folder_id' => $targetFolderId]);
        }

        return redirect()->back()->with('success', 'Berhasil dipindahkan.');
    }

    /**
     * Update quantity cepat dari Card (+/-).
     */
    public function updateQuantity(Request $request, Item $item)
    {
        $request->validate(['qty' => 'required|numeric']);
        $item->increment('stok_saat_ini', $request->qty);
        $this->logActivity($item->id, $request->qty, 'Update cepat (+/-)');
        return redirect()->back()->with('success', 'Stok berhasil diupdate.');
    }

    /**
     * Hapus Item (Soft Delete).
     */
    public function destroy(Item $item)
    {
        $item->delete();
        return redirect()->route('item.index')->with('success', 'Item berhasil dihapus.');
    }

    /**
     * Hapus Folder (Hanya jika kosong).
     */
    public function destroyFolder(Folder $folder)
    {
        if ($folder->children()->exists() || $folder->items()->exists()) {
            return redirect()->back()->with('error', 'Gagal: Folder tidak bisa dihapus karena masih berisi data.');
        }
        $folder->delete();
        return redirect()->back()->with('success', 'Folder dihapus.');
    }

    // --- INTERNAL HELPERS ---

    private function createNewItem($request, $name, $folderId)
    {
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

        if ($request->stok_saat_ini > 0) {
            $this->logActivity($item->id, $request->stok_saat_ini, 'Saldo awal');
        }
    }

    private function generateIndependentVariants($request, $dimensions, $folderId)
    {
        $combinations = $this->generateCombinations($dimensions);
        foreach ($combinations as $combo) {
            $variantName = $request->nama . ' - ' . implode(', ', $combo);
            Item::create([
                'nama' => $variantName,
                'sku' => $this->generateUniqueSku($variantName),
                'satuan' => $request->satuan ?? 'pcs',
                'stok_saat_ini' => 0,
                'stok_minimum' => $request->stok_minimum ?? 0,
                'harga_jual' => floor($request->harga_jual ?? 0),
                'folder_id' => $folderId,
                'note' => "Varian mandiri dari " . $request->nama,
                'tags' => array_filter(array_map('trim', explode(',', $request->tags_input ?? ''))) ?: null,
            ]);
        }
    }

    private function generateCombinations($dims, $idx = 0, $curr = []) {
        if ($idx == count($dims)) return [$curr];
        $res = [];
        $options = array_map('trim', explode(',', $dims[$idx]['options']));
        foreach ($options as $opt) {
            $next = $curr; $next[] = $opt;
            $res = array_merge($res, $this->generateCombinations($dims, $idx + 1, $next));
        }
        return $res;
    }

    private function syncDescendantPaths(Folder $folder) {
        $descendants = Folder::where('path', 'LIKE', $folder->path . '%')->where('id', '!=', $folder->id)->get();
        foreach ($descendants as $desc) {
            $desc->updatePath();
        }
    }

    private function logActivity($itemId, $qty, $note) {
        Transaksi::create(['item_id' => $itemId, 'jumlah_produksi' => $qty, 'tanggal_produksi' => now(), 'catatan' => $note]);
    }

    private function generateUniqueSku($name) {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
        $sku = $prefix . '-' . strtoupper(Str::random(6));
        return Item::where('sku', $sku)->exists() ? $this->generateUniqueSku($name) : $sku;
    }

    public function exportCsv() {
        $items = Item::all();
        $fileName = 'inventory_' . date('Ymd_His') . '.csv';
        return new StreamedResponse(function() use ($items) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Nama', 'SKU', 'Stok', 'Satuan', 'Harga']);
            foreach ($items as $item) {
                fputcsv($file, [$item->id, $item->nama, $item->sku, $item->calculated_stock, $item->satuan, $item->harga_jual]);
            }
            fclose($file);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"$fileName\""]);
    }
}
