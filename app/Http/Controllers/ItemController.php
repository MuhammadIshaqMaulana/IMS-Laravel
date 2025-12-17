<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    /**
     * Menampilkan daftar semua Item. (Fokus tampilan Card/List ala Sortly)
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
        // Ambil semua item yang BUKAN BOM/KIT, karena BOM hanya bisa menggunakan Material/Asset
        $allMaterials = Item::whereNull('materials')->orderBy('nama')->get();
        return view('item.create', compact('allMaterials'));
    }

    /**
     * Menyimpan Item baru ke database (dengan Tags, Custom Fields, Materials, dan Varian).
     */
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

            // --- VALIDASI TAMBAHAN UNTUK BOM & VARIAN ---
            'is_bom' => 'nullable|boolean',
            'materials_data' => 'nullable|string', // JSON dari material
            'variant_dimensions' => 'nullable|string', // JSON dari dimensi varian
        ]);

        // 1. Pengolahan Data
        $validatedData['stok_saat_ini'] = $validatedData['stok_saat_ini'] ?? 0;
        $validatedData['stok_minimum'] = $validatedData['stok_minimum'] ?? 0;
        $validatedData['harga_jual'] = floor($validatedData['harga_jual'] ?? 0);

        // Proses Tags
        $tagsArray = array_map('trim', explode(',', $validatedData['tags_input'] ?? ''));
        $validatedData['tags'] = array_filter($tagsArray) ?: null;

        // Custom Fields (dikosongkan untuk fokus ke BOM/Varian)
        $validatedData['custom_fields'] = null;

        // 2. Logika Item Varian & BOM
        $variantDimensions = json_decode($request->input('variant_dimensions'), true) ?? [];
        $isBom = $request->boolean('is_bom');
        $materials = $isBom ? (json_decode($request->input('materials_data'), true) ?? []) : null;

        // VALIDASI KRITIS
        if ($isBom && !empty($variantDimensions)) {
             return redirect()->back()->withInput()->with('error', 'Item BOM/Kit tidak dapat memiliki Varian Multi-Dimensi. Silakan buat varian Item normal dulu, lalu buat Item BOM terpisah untuk setiap varian.');
        }
        if ($isBom && empty($materials) && $request->has('is_bom')) { // Cek jika dicentang tapi tidak ada material
            return redirect()->back()->withInput()->with('error', 'Item yang ditandai sebagai BOM wajib memiliki minimal satu material penyusun.');
        }

        // --- Atribut Item Induk ---
        $itemData = $validatedData;
        $itemData['materials'] = $materials;
        $itemData['parent_id'] = null;

        // 3. Logika SKU & Varian
        if (empty($variantDimensions)) {
            // Item Normal atau Item BOM (Non-Varian)
            $itemData['sku'] = $this->generateUniqueSku($itemData['nama']);
            $baseItem = Item::create($itemData);
            $message = 'Item "' . $baseItem->nama . '" berhasil ditambahkan.';
        } else {
            // Item Induk Varian
            $itemData['sku'] = null; // Item Induk tidak memiliki SKU
            $baseItem = Item::create($itemData);

            // Buat Varian Item
            $this->createItemVariants($baseItem, $variantDimensions);
            $message = 'Item Induk "' . $baseItem->nama . '" dan varian-varian terkait berhasil ditambahkan.';
        }

        return redirect()->route('item.index')->with('success', $message);
    }

    /**
     * Menampilkan Item spesifik.
     */
    public function show(Item $item)
    {
        return view('item.show', compact('item'));
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
            'pemasok' => 'nullable|string|max:100',
            'note' => 'nullable|string',

            // --- VALIDASI TAGS & CUSTOM FIELDS ---
            'tags_input' => 'nullable|string',
            'is_custom_field_1_active' => 'nullable|boolean',
            'custom_field_1_name' => 'nullable|string|max:50',
        ]);

        // Pengolahan Data
        $validatedData['stok_saat_ini'] = $validatedData['stok_saat_ini'] ?? 0;
        $validatedData['stok_minimum'] = $validatedData['stok_minimum'] ?? 0;
        $validatedData['harga_jual'] = floor($validatedData['harga_jual'] ?? 0);

        // Proses Tags
        $tagsArray = array_map('trim', explode(',', $validatedData['tags_input'] ?? ''));
        $validatedData['tags'] = array_filter($tagsArray) ?: null;

        // Proses Custom Fields (dikosongkan sementara)
        $validatedData['custom_fields'] = null;

        $item->update($validatedData);

        return redirect()->route('item.index')
                         ->with('success', 'Item "' . $item->nama . '" berhasil diperbarui.');
    }

    /**
     * Menghapus Item.
     */
    public function destroy(Item $item)
    {
        try {
            // Pengecekan relasi ke Resep (DaftarBahan) dan Transaksi
            // Note: Karena daftar_bahans sudah dihapus, kita hanya perlu cek transaksi dan apakah item tersebut adalah parent

            if ($item->transaksis()->exists()) {
                return redirect()->route('item.index')
                             ->with('error', 'Gagal menghapus item: Item ini masih memiliki histori Transaksi.');
            }
            if ($item->parent()->exists()) {
                return redirect()->route('item.index')
                             ->with('error', 'Gagal menghapus item: Item ini adalah Item Induk dari varian lain. Hapus semua varian terlebih dahulu.');
            }

            $item->delete();
            return redirect()->route('item.index')
                             ->with('danger', 'Item "' . $item->nama . '" dan datanya berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('item.index')
                             ->with('error', 'Terjadi kesalahan saat menghapus item: ' . $e->getMessage());
        }
    }


    /**
     * Menangani pembaruan massal (bulk update) item yang terpilih.
     */
    public function bulkUpdate(Request $request)
    {
        $validatedData = $request->validate([
            // Wajib ada: Daftar ID item yang dipilih
            'selected_items' => 'required|string',

            // --- Validasi untuk Nama & SKU ---
            'name_action' => ['nullable', Rule::in(['replace', 'prefix', 'suffix', 'seq_prefix', 'seq_suffix'])],
            'name_value' => 'nullable|string|max:100',

            // --- Validasi untuk Min. Level ---
            'min_level_action' => ['nullable', Rule::in(['replace', 'add'])],
            'min_level_value' => 'nullable|numeric|min:0',

            // --- Validasi untuk Harga ---
            'price_action' => ['nullable', Rule::in(['replace', 'increment', 'decrement', 'multiply', 'divide'])],
            'price_value' => 'nullable|numeric', // Tidak perlu min:0 karena decrement diizinkan

            // --- Validasi untuk Notes ---
            'note_action' => ['nullable', Rule::in(['replace', 'prefix', 'suffix', 'seq_prefix', 'seq_suffix'])],
            'note_value' => 'nullable|string',

            // --- Validasi untuk Tags ---
            'tags_action' => ['nullable', Rule::in(['add', 'remove'])],
            'tags_input' => 'nullable|string',

            // --- Validasi untuk Custom Fields (diabaikan sementara) ---
            'custom_fields_update' => 'nullable|array',
        ]);

        $itemIds = json_decode($validatedData['selected_items'], true);
        $items = Item::whereIn('id', $itemIds)->get();
        $count = $items->count();

        if ($count === 0) {
            return redirect()->route('item.index')->with('error', 'Tidak ada item yang terpilih untuk diupdate.');
        }

        DB::beginTransaction();
        $seqIndex = 1;

        try {
            // Proses Tags untuk Bulk
            $inputTagsArray = array_map('trim', explode(',', $validatedData['tags_input'] ?? ''));
            $inputTagsArray = array_filter($inputTagsArray);

            foreach ($items as $item) {
                // 1. Logika Bulk Edit NAMA & SKU
                if ($request->has('name_action') && !empty($validatedData['name_value'])) {
                    $item->nama = $this->applyNameAction($item->nama, $validatedData['name_action'], $validatedData['name_value'], $seqIndex);

                    // Update SKU jika perlu
                    if (empty($item->parent_id)) { // Hanya update SKU jika bukan varian (parent)
                        $item->sku = $this->generateUniqueSku($item->nama);
                    }
                }

                // 2. Logika Bulk Edit MIN. LEVEL
                if ($request->has('min_level_action') && is_numeric($validatedData['min_level_value'])) {
                    $value = $validatedData['min_level_value'];
                    if ($validatedData['min_level_action'] == 'replace') {
                        $item->stok_minimum = max(0, $value);
                    } elseif ($validatedData['min_level_action'] == 'add') {
                        $item->stok_minimum = max(0, $item->stok_minimum + $value);
                    }
                }

                // 3. Logika Bulk Edit HARGA
                if ($request->has('price_action') && is_numeric($validatedData['price_value'])) {
                    $value = $validatedData['price_value'];
                    $currentPrice = $item->harga_jual;
                    $newPrice = $currentPrice;

                    switch ($validatedData['price_action']) {
                        case 'replace':
                            $newPrice = $value;
                            break;
                        case 'increment':
                            $newPrice = $currentPrice + $value;
                            break;
                        case 'decrement':
                            $newPrice = $currentPrice - $value;
                            break;
                        case 'multiply':
                            $newPrice = $currentPrice * $value;
                            break;
                        case 'divide':
                            if ($value != 0) {
                                $newPrice = $currentPrice / $value;
                            }
                            break;
                    }

                    // Pembulatan ke integer terdekat (wajib, Sortly Style)
                    $item->harga_jual = floor(max(0, $newPrice));
                }

                // 4. Logika Bulk Edit NOTES
                if ($request->has('note_action') && !empty($validatedData['note_value'])) {
                    $item->note = $this->applyNoteAction($item->note, $validatedData['note_action'], $validatedData['note_value'], $seqIndex);
                }

                // 5. Logika Bulk Edit TAGS
                if ($request->has('tags_action') && !empty($inputTagsArray)) {
                    $currentTags = is_array($item->tags) ? $item->tags : [];

                    if ($validatedData['tags_action'] == 'add') {
                        $item->tags = array_unique(array_merge($currentTags, $inputTagsArray));
                    } elseif ($validatedData['tags_action'] == 'remove') {
                        $item->tags = array_values(array_diff($currentTags, $inputTagsArray));
                    }

                    if (empty($item->tags)) {
                        $item->tags = null;
                    }
                }

                // 6. Logika Custom Fields (diabaikan di sini, perlu struktur CF yang lebih dinamis)

                $item->save();
                $seqIndex++;
            }

            DB::commit();
            return redirect()->route('item.index')->with('success', "Berhasil memperbarui $count item melalui Bulk Action.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan saat Bulk Update: ' . $e->getMessage());
        }
    }





    // --- HIERARCHY FASE 11: FOLDER LOGIC ---

    /**
     * Menampilkan daftar folder (Item yang memiliki tag 'folder') dan item di root.
     */
    public function folderIndex(Request $request)
    {
        // Item yang dianggap Folder: memiliki tag 'folder' dan tidak memiliki parent_id
        $folders = Item::where('tags', 'like', '%"folder"%')
                       ->whereNull('parent_id')
                       ->orderBy('nama')
                       ->get();

        // Item di Root: Item yang tidak memiliki folder_id dan parent_id
        $rootItems = Item::whereNull('folder_id')
                         ->whereNull('parent_id')
                         ->where('tags', 'not like', '%"folder"%')
                         ->orderBy('nama')
                         ->get();

        return view('folder.index', compact('folders', 'rootItems'));
    }

    /**
     * Membuat Item baru yang ditandai sebagai Folder.
     */
    public function createFolder(Request $request)
    {
        $validated = $request->validate(['nama' => 'required|string|max:100']);

        Item::create([
            'nama' => $validated['nama'],
            'satuan' => 'pcs', // Default untuk folder
            'stok_saat_ini' => 0,
            'stok_minimum' => 0,
            'harga_jual' => 0,
            'tags' => ['folder'], // Tag penanda sebagai Folder
            'note' => 'Folder dibuat pada ' . now()->format('Y-m-d'),
        ]);

        return redirect()->route('folder.index')->with('success', 'Folder "' . $validated['nama'] . '" berhasil dibuat.');
    }

    /**
     * Memindahkan Item ke Folder.
     */
    public function moveItemToFolder(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'folder_id' => 'required|exists:items,id',
        ]);

        $item = Item::findOrFail($validated['item_id']);
        $folder = Item::findOrFail($validated['folder_id']);

        // Cek apakah target benar-benar folder
        if (!is_array($folder->tags) || !in_array('folder', $folder->tags)) {
             return redirect()->back()->with('error', 'Item target bukan folder yang valid.');
        }

        $item->update(['folder_id' => $folder->id]);

        return redirect()->route('folder.index')->with('success', 'Item "' . $item->nama . '" berhasil dipindahkan ke folder "' . $folder->nama . '".');
    }















    // --- HELPER UNTUK BULK ACTION ---

    /**
     * Helper: Menerapkan aksi Nama (replace, prefix, suffix, sequential).
     */
    private function applyNameAction(?string $currentName, string $action, string $value, int $index): string
    {
        $seq = str_pad($index, 3, '0', STR_PAD_LEFT);
        $currentName = $currentName ?? '';

        switch ($action) {
            case 'replace':
                return $value;
            case 'prefix':
                return $value . ' ' . $currentName;
            case 'suffix':
                return $currentName . ' ' . $value;
            case 'seq_prefix':
                return $seq . ' - ' . $value . ' ' . $currentName;
            case 'seq_suffix':
                return $currentName . ' ' . $value . ' - ' . $seq;
            default:
                return $currentName;
        }
    }

    /**
     * Helper: Menerapkan aksi Catatan (replace, prefix, suffix, sequential).
     */
     private function applyNoteAction(?string $currentNote, string $action, string $value, int $index): ?string
    {
        $currentNote = $currentNote ?? '';
        $seq = str_pad($index, 3, '0', STR_PAD_LEFT);

        switch ($action) {
            case 'replace':
                return $value;
            case 'prefix':
                return $value . "\n" . $currentNote;
            case 'suffix':
                return $currentNote . "\n" . $value;
            case 'seq_prefix':
                return "($seq) $value \n" . $currentNote;
            case 'seq_suffix':
                return $currentNote . "\n" . "$value ($seq)";
            default:
                return $currentNote;
        }
    }

    // --- HELPER UNTUK MULTI-DIMENSI ---

    /**
     * Helper: Menghasilkan SKU unik (Logika tetap sama)
     */
    private function generateUniqueSku(string $itemName): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $itemName), 0, 3));
        if (empty($prefix)) {
             $prefix = 'ITM';
        }
        $uniquePart = time() . mt_rand(100, 999);
        $sku = $prefix . '-' . substr($uniquePart, -6);

        if (Item::where('sku', $sku)->exists()) {
            return $this->generateUniqueSku($itemName);
        }

        return $sku;
    }

    /**
     * Helper: Menghasilkan semua kombinasi varian dari dimensi yang diberikan.
     */
    private function generateCombinations(array $dimensions, int $index = 0, array $currentCombination = []): array
    {
        if ($index === count($dimensions)) {
            return [$currentCombination];
        }

        $combinations = [];
        $dimensionName = $dimensions[$index]['name'];
        $options = array_map('trim', explode(',', $dimensions[$index]['options']));
        $options = array_filter($options);

        foreach ($options as $option) {
            $newCombination = $currentCombination;
            $newCombination[$dimensionName] = $option;

            $combinations = array_merge($combinations, $this->generateCombinations($dimensions, $index + 1, $newCombination));
        }

        return $combinations;
    }

    /**
     * Helper: Membuat Item Varian berdasarkan kombinasi dimensi.
     */
    private function createItemVariants(Item $baseItem, array $variantDimensions): void
    {
        $combinations = $this->generateCombinations($variantDimensions);

        foreach ($combinations as $combination) {
            $data = $baseItem->toArray();

            // Hapus ID, materials dari induk (varian tidak inherit BOM/Materials)
            unset($data['id'], $data['materials']);

            // Buat nama varian dan SKU unik
            $variantNamePart = implode(' / ', $combination);
            $data['nama'] = $baseItem->nama . ' - ' . $variantNamePart;
            $data['sku'] = $this->generateUniqueSku($data['nama']);
            $data['stok_saat_ini'] = 0; // Stok awal varian direset ke 0
            $data['parent_id'] = $baseItem->id; // Tautkan ke Item Induk

            // Simpan deskripsi varian di note
            $data['note'] = ($baseItem->note ?? '') . "\n--- Varian: " . $variantNamePart;

            Item::create($data);
        }
    }
}
