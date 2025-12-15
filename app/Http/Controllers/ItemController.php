<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    /**
     * Menampilkan daftar semua Item.
     */
    public function index()
    {
        $items = Item::orderBy('nama')->paginate(15);

        return view('item.index', compact('items'));
    }

    /**
     * Menampilkan formulir untuk membuat Item baru.
     */
    public function create()
    {
        return view('item.create');
    }

    /**
     * Menyimpan Item baru ke database (dengan Tags dan Custom Fields).
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nama' => 'required|string|max:100',
            'satuan' => 'required|string|max:20',
            'stok_saat_ini' => 'nullable|numeric|min:0',
            'stok_minimum' => 'nullable|numeric|min:0',
            'harga_jual' => 'nullable|numeric|min:0',
            'jenis_item' => ['required', Rule::in(['bahan_mentah', 'produk_jadi', 'asset'])],
            'pemasok' => 'nullable|string|max:100',
            'note' => 'nullable|string',

            // --- VALIDASI TAGS & CUSTOM FIELDS ---
            'tags_input' => 'nullable|string', // Input tags dipisahkan koma
            'is_custom_field_1_active' => 'nullable|boolean', // Contoh Custom Field Boolean 1
            'custom_field_1_name' => 'nullable|string|max:50',
            'variant_names' => 'nullable|string', // Input varian dipisahkan koma
        ]);

        // Pengolahan Data
        $validatedData['stok_saat_ini'] = $validatedData['stok_saat_ini'] ?? 0;
        $validatedData['stok_minimum'] = $validatedData['stok_minimum'] ?? 0;
        // Harga Jual dibulatkan ke bawah untuk menghindari desimal saat storage
        $validatedData['harga_jual'] = floor($validatedData['harga_jual'] ?? 0);

        // Proses Tags: Ubah string koma-separated menjadi array
        if (!empty($validatedData['tags_input'])) {
            $tagsArray = array_map('trim', explode(',', $validatedData['tags_input']));
            $validatedData['tags'] = array_filter($tagsArray);
        } else {
             $validatedData['tags'] = null;
        }

        // Proses Custom Fields (Boolean): Bentuk Array JSON
        $customFields = [];
        if (!empty($validatedData['custom_field_1_name']) && $validatedData['is_custom_field_1_active']) {
             $customFields[$validatedData['custom_field_1_name']] = true;
        }
        $validatedData['custom_fields'] = empty($customFields) ? null : $customFields;

        // --- 7.3 Implementasi Logika SKU Otomatis ---
        // (SKU hanya diisi jika tidak ada varian)
        if (empty($validatedData['variant_names'])) {
            $validatedData['sku'] = $this->generateUniqueSku($validatedData['nama']);
        }

        // Simpan Item Induk (Base Item)
        $baseItem = Item::create($validatedData);

        // --- 7.4 Logika Item Varian (jika ada) ---
        if (!empty($validatedData['variant_names'])) {
            $this->createItemVariants($baseItem, $validatedData['variant_names']);
            return redirect()->route('item.index')
                         ->with('success', 'Item Induk "' . $baseItem->nama . '" dan varian-varian terkait berhasil ditambahkan.');
        }

        return redirect()->route('item.index')
                         ->with('success', 'Item "' . $baseItem->nama . '" berhasil ditambahkan.');
    }

    /**
     * Menampilkan formulir edit Item.
     */
    public function edit(Item $item)
    {
        return view('item.edit', compact('item'));
    }

    /**
     * Memperbarui Item.
     */
    public function update(Request $request, Item $item)
    {
        $validatedData = $request->validate([
            'nama' => 'required|string|max:100',
            'satuan' => 'required|string|max:20',
            'stok_saat_ini' => 'nullable|numeric|min:0',
            'stok_minimum' => 'nullable|numeric|min:0',
            'harga_jual' => 'nullable|numeric|min:0',
            'jenis_item' => ['required', Rule::in(['bahan_mentah', 'produk_jadi', 'asset'])],
            'pemasok' => 'nullable|string|max:100',
            'note' => 'nullable|string',

            // --- VALIDASI TAGS & CUSTOM FIELDS ---
            'tags_input' => 'nullable|string',
            'is_custom_field_1_active' => 'nullable|boolean',
            'custom_field_1_name' => 'nullable|string|max:50',
            // Variasi tidak diizinkan diubah melalui form update standar
        ]);

        // Pengolahan Data
        $validatedData['stok_saat_ini'] = $validatedData['stok_saat_ini'] ?? 0;
        $validatedData['stok_minimum'] = $validatedData['stok_minimum'] ?? 0;
        $validatedData['harga_jual'] = floor($validatedData['harga_jual'] ?? 0);

        // Proses Tags
        if (!empty($validatedData['tags_input'])) {
            $tagsArray = array_map('trim', explode(',', $validatedData['tags_input']));
            $validatedData['tags'] = array_filter($tagsArray);
        } else {
             $validatedData['tags'] = null;
        }

        // Proses Custom Fields (dianggap Boolean)
        $customFields = [];
        if (!empty($validatedData['custom_field_1_name']) && $validatedData['is_custom_field_1_active']) {
             $customFields[$validatedData['custom_field_1_name']] = true;
        }
        $validatedData['custom_fields'] = empty($customFields) ? null : $customFields;

        // SKU tidak diupdate di sini

        $item->update($validatedData);

        return redirect()->route('item.index')
                         ->with('success', 'Item "' . $item->nama . '" berhasil diperbarui.');
    }

    // --- HELPER UNTUK FASILITAS SORTLY ---

    /**
     * Helper: Menghasilkan SKU unik berdasarkan nama item.
     * Format: 3 huruf awal + Timestamp unik.
     */
    private function generateUniqueSku(string $itemName): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $itemName), 0, 3));
        if (empty($prefix)) {
             $prefix = 'ITM'; // Default jika nama item tidak memiliki huruf
        }
        $uniquePart = time() . mt_rand(100, 999);
        $sku = $prefix . '-' . substr($uniquePart, -6);

        // Cek duplikasi (sangat kecil kemungkinannya)
        if (Item::where('sku', $sku)->exists()) {
            return $this->generateUniqueSku($itemName);
        }

        return $sku;
    }

    /**
     * Helper: Membuat Item Varian berdasarkan item induk.
     */
    private function createItemVariants(Item $baseItem, string $variantNames): void
    {
        $names = array_map('trim', explode(',', $variantNames));
        $names = array_filter($names);

        foreach ($names as $variantName) {
            $data = $baseItem->toArray();

            // Hapus ID agar menjadi entitas baru
            unset($data['id']);

            // Ganti nama dan SKU
            $data['nama'] = $baseItem->nama . ' - ' . $variantName;
            $data['sku'] = $this->generateUniqueSku($data['nama']);
            $data['stok_saat_ini'] = 0; // Stok awal varian direset ke 0

            // Item Varian tidak boleh memiliki varian lagi
            unset($data['variant_names']);

            Item::create($data);
        }

        // Opsional: Hapus SKU Item Induk jika varian dibuat
        $baseItem->update(['sku' => null]);
    }
}
