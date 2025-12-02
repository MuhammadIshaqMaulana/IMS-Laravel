<?php

namespace App\Http\Controllers;

use App\Models\ProdukJadi;
use App\Models\Transaksi; // DIUBAH: Menggunakan Model Transaksi yang baru
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    /**
     * Menampilkan daftar semua transaksi produksi yang pernah dilakukan. (READ - Index)
     */
    public function index()
    {
        // Panggilan Model diubah ke Transaksi::
        $transaksis = Transaksi::with('produkJadi')
                            ->orderBy('tanggal_produksi', 'desc')
                            ->paginate(15);

        // Mengarahkan ke views/transaksi/index.blade.php
        return view('transaksi.index', compact('transaksis'));
    }

    /**
     * Menampilkan formulir untuk mencatat transaksi produksi baru. (CREATE - Form)
     */
    public function create()
    {
        // Ambil hanya produk yang memiliki resep (daftar bahan) yang terdefinisi
        $produkJadi = ProdukJadi::whereHas('resep')->orderBy('nama')->get();

        if ($produkJadi->isEmpty()) {
            return redirect()->route('produk-jadi.index')
                             ->with('warning', 'Anda harus memiliki setidaknya satu Produk Jadi dengan Resep (Daftar Bahan) yang terdefinisi sebelum mencatat produksi.');
        }

        // Mengarahkan ke views/transaksi/create.blade.php
        return view('transaksi.create', compact('produkJadi'));
    }

    /**
     * Menyimpan transaksi produksi dan mengupdate stok. (CREATE - Store)
     * Ini adalah LOGIKA INTI MANUFAKTUR, dilakukan dalam transaksi DB.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'produk_jadi_id' => 'required|exists:produk_jadi,id',
            'jumlah_produksi' => 'required|integer|min:1',
            'tanggal_produksi' => 'required|date',
            'catatan' => 'nullable|string|max:500',
        ]);

        $produkId = $validatedData['produk_jadi_id'];
        $jumlahProduksi = $validatedData['jumlah_produksi'];

        // Ambil produk dan resep (dengan bahan mentahnya)
        $produk = ProdukJadi::with('resep.bahanMentah')->findOrFail($produkId);
        $resep = $produk->resep;

        if ($resep->isEmpty()) {
            return redirect()->back()
                             ->withInput()
                             ->withErrors(['produk_jadi_id' => 'Produk ini tidak memiliki resep yang terdefinisi.']);
        }

        // --- MULAI TRANSAKSI DATABASE (ATOMICITY) ---
        DB::beginTransaction();

        try {
            // 1. Cek Ketersediaan Stok Bahan Mentah (Pemeriksaan Awal)
            foreach ($resep as $itemResep) {
                $bahan = $itemResep->bahanMentah;
                $jumlahDibutuhkan = $itemResep->jumlah_digunakan * $jumlahProduksi;

                // Cek stok
                if ($bahan->stok_saat_ini < $jumlahDibutuhkan) {
                    DB::rollBack();
                    return redirect()->back()
                                     ->withInput()
                                     ->withErrors([
                                         'jumlah_produksi' => "Stok bahan mentah '{$bahan->nama}' tidak mencukupi. Dibutuhkan: {$jumlahDibutuhkan} {$bahan->satuan}, Tersedia: {$bahan->stok_saat_ini} {$bahan->satuan}."
                                     ]);
                }
            }

            // 2. Kurangi Stok Bahan Mentah
            foreach ($resep as $itemResep) {
                $bahan = $itemResep->bahanMentah;
                $jumlahDibutuhkan = $itemResep->jumlah_digunakan * $jumlahProduksi;

                $bahan->stok_saat_ini -= $jumlahDibutuhkan;
                $bahan->save();
            }

            // 3. Tambahkan Stok Produk Jadi
            $produk->stok_di_tangan += $jumlahProduksi;
            $produk->save();

            // 4. Catat Transaksi Produksi
            // Panggilan Model diubah ke Transaksi::
            Transaksi::create($validatedData);

            // Jika semua berhasil, commit transaksi
            DB::commit();

            return redirect()->route('transaksi.index')
                             ->with('success', "Produksi {$jumlahProduksi} unit '{$produk->nama}' berhasil dicatat. Stok bahan baku sudah disesuaikan.");

        } catch (\Exception $e) {
            // Jika terjadi kesalahan, batalkan semua perubahan
            DB::rollBack();
            return redirect()->back()
                             ->with('error', 'Terjadi kesalahan saat memproses transaksi produksi. Mohon coba lagi. Error: ' . $e->getMessage());
        }
    }

    // Metode resource lainnya dinonaktifkan untuk Transaksi karena membatalkan histori tidak diizinkan.
    public function show() { abort(404); }
    public function edit() { abort(404); }
    public function update() { abort(404); }
    public function destroy() { abort(404); }
}
