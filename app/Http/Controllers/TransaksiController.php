<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    /**
     * Menampilkan daftar semua transaksi produksi yang pernah dilakukan.
     */
    public function index()
    {
        $transaksis = Transaksi::with('itemProduksi')
                            ->orderBy('tanggal_produksi', 'desc')
                            ->paginate(15);

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

        return view('transaksi.create', compact('bomItems')); // Variabel diubah
    }

    /**
     * Menyimpan transaksi produksi dan mengupdate stok (Logika Inti).
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'produk_jadi_id' => 'required|exists:items,id',
            'jumlah_produksi' => 'required|integer|min:1',
            'tanggal_produksi' => 'required|date',
            'catatan' => 'nullable|string|max:500',
        ]);

        $itemId = $validatedData['produk_jadi_id'];
        $jumlahProduksi = $validatedData['jumlah_produksi'];

        // Mengambil Item (BOM/Kit)
        $produk = Item::findOrFail($itemId);

        // Validasi: Pastikan item ini memang BOM (memiliki materials)
        $resepMaterials = $produk->materials;
        if (empty($resepMaterials) || !is_array($resepMaterials)) {
             return redirect()->back()
                             ->withInput()
                             ->withErrors(['produk_jadi_id' => 'Item yang dipilih bukan BOM (tidak memiliki material).']);
        }

        DB::beginTransaction();

        try {
            // 1. Cek Ketersediaan Stok Bahan Mentah
            // $resepMaterials kini adalah array seperti: [{'item_id': 1, 'qty': 2.0}, ...]
            foreach ($resepMaterials as $material) {
                $materialItemId = $material['item_id'];
                $jumlahDibutuhkan = $material['qty'] * $jumlahProduksi;

                $bahan = Item::findOrFail($materialItemId);

                if ($bahan->stok_saat_ini < $jumlahDibutuhkan) {
                    DB::rollBack();
                    return redirect()->back()
                                     ->withInput()
                                     ->withErrors([
                                         'jumlah_produksi' => "Stok material '{$bahan->nama}' tidak mencukupi. Dibutuhkan: {$jumlahDibutuhkan} {$bahan->satuan}, Tersedia: {$bahan->stok_saat_ini} {$bahan->satuan}."
                                     ]);
                }
            }

            // 2. Kurangi Stok Bahan Mentah
            foreach ($resepMaterials as $material) {
                $bahan = Item::findOrFail($material['item_id']);
                $jumlahDibutuhkan = $material['qty'] * $jumlahProduksi;

                // Kurangi stok Item
                $bahan->stok_saat_ini -= $jumlahDibutuhkan;
                $bahan->save();
            }

            // 3. Tambahkan Stok Produk Jadi
            $produk->stok_saat_ini += $jumlahProduksi;
            $produk->save();

            // 4. Catat Transaksi
            Transaksi::create([
                'produk_jadi_id' => $itemId,
                'jumlah_produksi' => $jumlahProduksi,
                'tanggal_produksi' => $validatedData['tanggal_produksi'],
                'catatan' => $validatedData['catatan'],
            ]);

            DB::commit();

            return redirect()->route('transaksi.index')
                             ->with('success', "Perakitan/Produksi {$jumlahProduksi} unit '{$produk->nama}' berhasil dicatat. Stok material sudah disesuaikan.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                             ->with('error', 'Terjadi kesalahan saat memproses transaksi: ' . $e->getMessage());
        }
    }
    // Metode resource lainnya dinonaktifkan untuk Transaksi karena membatalkan histori tidak diizinkan.
    public function show() { abort(404); }
    public function edit() { abort(404); }
    public function update() { abort(404); }
    public function destroy() { abort(404); }
}
