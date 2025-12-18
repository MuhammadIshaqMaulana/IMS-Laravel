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
     * Menampilkan daftar item, mendukung navigasi folder.
     */
    public function index(Request $request)
    {
        $folderId = $request->query('folder_id');
        $query = Item::orderBy('nama');

        if ($folderId) {
            $query->where('folder_id', $folderId);
            $currentFolder = Item::findOrFail($folderId);
        } else {
            // Root view: hanya tampilkan item/folder yang tidak punya parent (induk)
            $query->whereNull('folder_id')->whereNull('parent_id');
            $currentFolder = null;
        }

        $items = $query->paginate(15);
        $allFolders = Item::where('tags', 'like', '%"folder"%')->orderBy('nama')->get();

        return view('item.index', compact('items', 'currentFolder', 'allFolders'));
    }

    public function create()
    {
        $allMaterials = Item::whereNull('materials')
            ->where('tags', 'not like', '%"folder"%')
            ->orderBy('nama')->get();
        $allFolders = Item::where('tags', 'like', '%"folder"%')->orderBy('nama')->get();
        return view('item.create', compact('allMaterials', 'allFolders'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nama' => 'required|string|max:100',
            'satuan' => 'required|string|max:20',
            'stok_saat_ini' => 'nullable|numeric|min:0',
            'stok_minimum' => 'nullable|numeric|min:0',
            'harga_jual' => 'nullable|numeric|min:0',
            'pemasok' => 'nullable|string|max:100',
            'note' => 'nullable|string',
            'tags_input' => 'nullable|string',
            'folder_id' => 'nullable|exists:items,id',
            'type' => 'required|in:item,bom,folder',
            'materials_data' => 'nullable|string',
            'variant_dimensions' => 'nullable|string',
        ]);

        $validatedData['stok_saat_ini'] = $validatedData['stok_saat_ini'] ?? 0;
        $validatedData['harga_jual'] = floor($validatedData['harga_jual'] ?? 0);

        // Gabungkan Tags
        $tagsArray = array_map('trim', explode(',', $validatedData['tags_input'] ?? ''));
        if ($validatedData['type'] === 'folder') $tagsArray[] = 'folder';
        $validatedData['tags'] = array_filter(array_unique($tagsArray)) ?: null;

        // Siapkan Data Materials jika BOM
        $materials = null;
        if ($validatedData['type'] === 'bom') {
            $materials = json_decode($request->input('materials_data'), true);
        }

        $variantDimensions = json_decode($request->input('variant_dimensions'), true) ?? [];

        DB::beginTransaction();
        try {
            if (empty($variantDimensions)) {
                // Item Tunggal / BOM / Folder
                $validatedData['sku'] = ($validatedData['type'] === 'folder') ? null : $this->generateUniqueSku($validatedData['nama']);
                $validatedData['materials'] = $materials;
                $item = Item::create($validatedData);
            } else {
                // Item Induk (Varian)
                $validatedData['sku'] = null;
                $validatedData['materials'] = null; // Induk varian tidak boleh BOM
                $parent = Item::create($validatedData);
                $this->createItemVariants($parent, $variantDimensions);
            }
            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $validatedData['folder_id']])
                             ->with('success', 'Data berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    public function show(Item $item)
    {
        $history = $item->transaksis()->orderBy('created_at', 'desc')->get();
        return view('item.show', compact('item', 'history'));
    }

    public function edit(Item $item)
    {
        $allFolders = Item::where('tags', 'like', '%"folder"%')->orderBy('nama')->get();
        return view('item.edit', compact('item', 'allFolders'));
    }

    public function update(Request $request, Item $item)
    {
        $validatedData = $request->validate([
            'nama' => 'required|string|max:100',
            'satuan' => 'required|string|max:20',
            'stok_saat_ini' => 'nullable|numeric|min:0',
            'stok_minimum' => 'nullable|numeric|min:0',
            'harga_jual' => 'nullable|numeric|min:0',
            'pemasok' => 'nullable|string|max:100',
            'note' => 'nullable|string',
            'tags_input' => 'nullable|string',
            'folder_id' => 'nullable|exists:items,id',
        ]);

        $tagsArray = array_map('trim', explode(',', $validatedData['tags_input'] ?? ''));
        if ($item->is_folder) $tagsArray[] = 'folder';
        $validatedData['tags'] = array_filter(array_unique($tagsArray)) ?: null;

        $item->update($validatedData);
        return redirect()->route('item.show', $item->id)->with('success', 'Item diperbarui.');
    }

    public function updateQuantity(Request $request, Item $item)
    {
        $request->validate(['qty' => 'required|numeric']);
        $item->increment('stok_saat_ini', $request->qty);

        Transaksi::create([
            'item_id' => $item->id,
            'jumlah_produksi' => $request->qty,
            'tanggal_produksi' => now(),
            'catatan' => 'Penyesuaian stok manual'
        ]);

        return redirect()->back()->with('success', 'Stok berhasil diperbarui.');
    }

    public function move(Request $request, Item $item)
    {
        $request->validate(['folder_id' => 'nullable|exists:items,id']);
        $item->update(['folder_id' => $request->folder_id]);
        return redirect()->back()->with('success', 'Lokasi item berhasil dipindahkan.');
    }

    public function destroy(Item $item)
    {
        if ($item->itemsInFolder()->exists()) {
            return redirect()->back()->with('error', 'Folder tidak bisa dihapus karena masih berisi item.');
        }
        $item->delete();
        return redirect()->route('item.index')->with('success', 'Item berhasil dihapus.');
    }

    // --- EXPORT LOGIC ---
    public function exportCsv() { /* ... kode export loe udah bener ... */ }
    public function exportPdf() { /* ... kode export loe udah bener ... */ }

    // --- HELPERS ---
    private function generateUniqueSku($name) {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3)) ?: 'ITM';
        $sku = $prefix . '-' . substr(time(), -6);
        return Item::where('sku', $sku)->exists() ? $this->generateUniqueSku($name) : $sku;
    }

    private function createItemVariants($parent, $dimensions) {
        $combinations = $this->generateCombinations($dimensions);
        foreach ($combinations as $combo) {
            $name = $parent->nama . ' (' . implode(', ', $combo) . ')';
            Item::create([
                'nama' => $name,
                'sku' => $this->generateUniqueSku($name),
                'satuan' => $parent->satuan,
                'stok_saat_ini' => 0,
                'harga_jual' => $parent->harga_jual,
                'folder_id' => $parent->folder_id, // Varian otomatis masuk folder yang sama
                'parent_id' => $parent->id,
                'tags' => $parent->tags,
            ]);
        }
    }

    private function generateCombinations($dimensions, $index = 0, $current = []) {
        if ($index == count($dimensions)) return [$current];
        $res = [];
        $options = explode(',', $dimensions[$index]['options']);
        foreach ($options as $opt) {
            $next = $current; $next[] = trim($opt);
            $res = array_merge($res, $this->generateCombinations($dimensions, $index + 1, $next));
        }
        return $res;
    }
}
