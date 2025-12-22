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
    public function index(Request $request)
    {
        $folderId = $request->query('folder_id');
        $query = Item::orderBy('nama');

        if ($folderId) {
            $query->where('folder_id', $folderId);
            $currentFolder = Item::findOrFail($folderId);
        } else {
            $query->whereNull('folder_id')->whereNull('parent_id');
            $currentFolder = null;
        }

        $items = $query->paginate(15);
        $allFolders = Item::where('tags', 'like', '%"folder"%')->orderBy('nama')->get();

        return view('item.index', compact('items', 'currentFolder', 'allFolders'));
    }

    public function create()
    {
        $allMaterials = Item::whereNull('materials')->where('tags', 'not like', '%"folder"%')->orderBy('nama')->get();
        $allFolders = Item::where('tags', 'like', '%"folder"%')->orderBy('nama')->get();
        return view('item.create', compact('allMaterials', 'allFolders'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:100',
            'type' => 'required|in:item,bom,folder',
            'satuan' => 'nullable|string|max:20',
            'stok_saat_ini' => 'nullable|numeric',
            'stok_minimum' => 'nullable|numeric',
            'harga_jual' => 'nullable|numeric',
            'folder_id' => 'nullable|exists:items,id',
        ]);

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

                // Catat stok awal ke history jika bukan folder
                if ($request->type !== 'folder' && ($request->stok_saat_ini > 0)) {
                    Transaksi::create([
                        'item_id' => $item->id,
                        'jumlah_produksi' => $request->stok_saat_ini,
                        'tanggal_produksi' => now(),
                        'catatan' => 'Saldo awal item baru'
                    ]);
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
                $this->createItemVariants($parent, $dimensions);
            }

            DB::commit();
            return redirect()->route('item.index', ['folder_id' => $request->folder_id])->with('success', 'Berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    public function show(Item $item)
    {
        $history = $item->transaksis()->orderBy('tanggal_produksi', 'desc')->get();
        $allFolders = Item::where('tags', 'like', '%"folder"%')->where('id', '!=', $item->id)->get();
        return view('item.show', compact('item', 'history', 'allFolders'));
    }

    public function edit(Item $item)
    {
        $allFolders = Item::where('tags', 'like', '%"folder"%')->orderBy('nama')->get();
        return view('item.edit', compact('item', 'allFolders'));
    }

    public function update(Request $request, Item $item)
    {
        $request->validate([
            'nama' => 'required|string|max:100',
            'stok_saat_ini' => 'nullable|numeric',
        ]);

        $oldStock = (float) $item->stok_saat_ini;
        $newStock = (float) ($request->stok_saat_ini ?? 0);

        // Jika ada perubahan stok, catat di history aktivitas
        if ($oldStock != $newStock) {
            Transaksi::create([
                'item_id' => $item->id,
                'jumlah_produksi' => $newStock - $oldStock,
                'tanggal_produksi' => now(),
                'catatan' => 'Penyesuaian stok via edit item'
            ]);
        }

        $tagsArray = array_map('trim', explode(',', $request->tags_input ?? ''));
        if ($item->is_folder) $tagsArray[] = 'folder';

        $item->update([
            'nama' => $request->nama,
            'satuan' => $request->satuan,
            'stok_saat_ini' => $newStock, // FIXED: Sekarang stok ikut diupdate
            'stok_minimum' => $request->stok_minimum,
            'harga_jual' => floor($request->harga_jual),
            'folder_id' => $request->folder_id,
            'note' => $request->note,
            'tags' => array_values(array_filter(array_unique($tagsArray))),
        ]);

        return redirect()->route('item.show', $item->id)->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(Item $item)
    {
        if ($item->itemsInFolder()->exists()) return redirect()->back()->with('error', 'Folder tidak kosong.');
        $item->delete();
        return redirect()->route('item.index')->with('success', 'Dihapus.');
    }

    public function updateQuantity(Request $request, Item $item)
    {
        $request->validate(['qty' => 'required|numeric']);

        $item->increment('stok_saat_ini', $request->qty);

        Transaksi::create([
            'item_id' => $item->id,
            'jumlah_produksi' => $request->qty,
            'tanggal_produksi' => now(),
            'catatan' => 'Update stok cepat (+/-)'
        ]);

        return redirect()->back()->with('success', 'Stok berhasil diupdate.');
    }

    public function move(Request $request, Item $item)
    {
        $item->update(['folder_id' => $request->folder_id]);
        return redirect()->back()->with('success', 'Lokasi item berhasil dipindahkan.');
    }

    public function bulkUpdate(Request $request)
    {
        $itemIds = json_decode($request->selected_items, true);
        if (empty($itemIds)) return redirect()->back()->with('error', 'Pilih item dulu!');

        // Implementasi sederhana bulk action
        if ($request->filled('min_level_value')) {
            Item::whereIn('id', $itemIds)->update(['stok_minimum' => $request->min_level_value]);
        }

        return redirect()->back()->with('success', count($itemIds) . ' item berhasil diperbarui secara massal.');
    }

    public function exportCsv() {
        $items = Item::all();
        $fileName = 'inventory_export_' . date('Ymd_His') . '.csv';
        return new StreamedResponse(function() use ($items) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Nama', 'SKU', 'Stok', 'Satuan', 'Harga']);
            foreach ($items as $item) {
                fputcsv($file, [$item->id, $item->nama, $item->sku, $item->calculated_stock, $item->satuan, $item->harga_jual]);
            }
            fclose($file);
        }, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"$fileName\""]);
    }

    public function exportPdf() {
        $items = Item::all();
        return view('item.export_pdf', compact('items'));
    }

    private function generateUniqueSku($name) {
        $sku = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3)) . '-' . strtoupper(Str::random(6));
        return Item::where('sku', $sku)->exists() ? $this->generateUniqueSku($name) : $sku;
    }

    private function createItemVariants(Item $parent, array $dimensions) {
        $combinations = $this->generateCombinations($dimensions);
        foreach ($combinations as $combo) {
            $variantName = $parent->nama . ' (' . implode(', ', $combo) . ')';
            Item::create([
                'nama' => $variantName,
                'sku' => $this->generateUniqueSku($variantName),
                'satuan' => $parent->satuan,
                'stok_saat_ini' => 0,
                'harga_jual' => $parent->harga_jual,
                'folder_id' => $parent->folder_id,
                'parent_id' => $parent->id,
                'tags' => $parent->tags,
            ]);
        }
    }

    private function generateCombinations($dimensions, $index = 0, $current = []) {
        if ($index == count($dimensions)) return [$current];
        $res = [];
        $options = array_map('trim', explode(',', $dimensions[$index]['options']));
        foreach ($options as $opt) {
            $next = $current; $next[] = $opt;
            $res = array_merge($res, $this->generateCombinations($dimensions, $index + 1, $next));
        }
        return $res;
    }
}
