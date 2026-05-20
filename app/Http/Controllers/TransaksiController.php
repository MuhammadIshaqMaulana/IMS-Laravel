<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // Tambahkan ini


class TransaksiController extends Controller
{
    /**
     * [DITIMPA] Menampilkan daftar transaksi dengan Sortly Style sorting
     */
    public function index(Request $request)
    {
        $sortField = $request->query('sort', 'tanggal');
        $sortOrder = $request->query('order', 'desc');

        $validSorts = [
            'tanggal'     => 'created_at',
            'tipe'        => 'catatan', // Sort by string content
            'aksi'        => 'catatan',
            'user'        => 'catatan',
            'sku'         => 'item_id',
            'objek'       => 'item_id',
            'target'      => 'catatan',
            'perubahan'   => 'catatan',
            'asal'        => 'folder_id',
            'tujuan'      => 'catatan',
        ];

        $dbSortField = $validSorts[$sortField] ?? 'created_at';

        $transaksis = Transaksi::with(['itemProduksi.folder'])
            ->orderBy($dbSortField, $sortOrder)
            ->paginate(25)
            ->appends($request->query());

        return view('transaksi.index', compact('transaksis'));
    }

    /**
     * Menampilkan formulir untuk mencatat transaksi produksi baru.
     */
    public function create()
    {
        // Ambil hanya item yang DIANGGAP BOM (memiliki data di kolom materials)
        $bomItems = Item::whereNotNull('materials')
                        ->orderBy('nama')->get();

        if ($bomItems->isEmpty()) {
            return redirect()->route('item.index')
                             ->with('warning', 'Anda harus memiliki setidaknya satu Item (BOM/Kit) dengan material yang terdefinisi sebelum mencatat produksi/perakitan.');
        }

        return view('transaksi.create', compact('bomItems'));
    }

    /**
     * [DITIMPA] store: Menambahkan log perakitan ke audit trail (transaksis)
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'item_id' => 'required|exists:items,id',
            'jumlah_produksi' => 'required|integer|min:1',
            'tanggal_produksi' => 'required|date',
            'catatan' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $produk = Item::findOrFail($validatedData['item_id']);
        $jumlahProduksi = $validatedData['jumlah_produksi'];
        $resepMaterials = $produk->materials;

        if (empty($resepMaterials) || !is_array($resepMaterials)) {
             return redirect()->back()->withInput()->withErrors(['item_id' => 'Item bukan BOM.']);
        }

        DB::beginTransaction();
        try {
            // 1. Cek Ketersediaan
            foreach ($resepMaterials as $material) {
                $bahan = Item::findOrFail($material['item_id']);
                $dibutuhkan = $material['qty'] * $jumlahProduksi;
                if ($bahan->stok_saat_ini < $dibutuhkan) {
                    DB::rollBack();
                    return redirect()->back()->withInput()->withErrors(['jumlah_produksi' => "Stok '{$bahan->nama}' kurang."]);
                }
            }

            // 2. Kurangi Bahan
            foreach ($resepMaterials as $material) {
                $bahan = Item::findOrFail($material['item_id']);
                $bahan->decrement('stok_saat_ini', $material['qty'] * $jumlahProduksi);
            }

            // 3. Tambahkan Produk Jadi
            $produk->increment('stok_saat_ini', $jumlahProduksi);

            // 4. CATAT TRANSAKSI (AUDIT LOG)
            // Sesuai template: (User) update stok dari item `Nama` menjadi '+Qty' di folder [Folder]
            $execFName = $produk->folder ? $produk->folder->nama : 'ROOT';
            Transaksi::create([
                'user_id' => $user->id,
                'item_id' => $produk->id,
                'folder_id' => $produk->folder_id,
                'catatan' => "({$user->name}) memproduksi '+$jumlahProduksi' unit item `{$produk->nama}` di folder [{$execFName}]. Ket: " . ($validatedData['catatan'] ?? '-'),
            ]);

            DB::commit();
            return redirect()->route('transaksi.index')->with('success', "Produksi berhasil.");
        } catch (\Exception $e) { DB::rollBack(); return redirect()->back()->with('error', $e->getMessage()); }
    }

    public function show() { abort(404); }
    public function edit() { abort(404); }
    public function update() { abort(404); }
    public function destroy() { abort(404); }
}
