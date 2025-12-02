<?php

namespace App\Http\Controllers;

use App\Models\BahanMentah;
use App\Models\DaftarBahan;
use App\Models\ProdukJadi;
use Illuminate\Http\Request;

class DaftarBahanController extends Controller
{
    /**
     * Menampilkan daftar semua resep. (READ - List)
     * Kita akan mengelompokkan Daftar Bahan berdasarkan Produk Jadi.
     */
    public function index()
    {
        // Ambil semua Produk Jadi yang memiliki relasi resep (Eager loading)
        $produkJadi = ProdukJadi::with('resep.bahanMentah')->orderBy('nama')->get();

        return view('daftar_bahan.index', compact('produkJadi'));
    }

    /**
     * Menampilkan formulir untuk menambahkan item bahan ke resep. (CREATE - Form)
     */
    public function create()
    {
        // Data yang dibutuhkan untuk dropdown di form:
        $produk = ProdukJadi::orderBy('nama')->get();
        $bahan = BahanMentah::orderBy('nama')->get();

        return view('daftar_bahan.create', compact('produk', 'bahan'));
    }

    /**
     * Menyimpan item bahan baru ke resep di database. (CREATE - Store)
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $validatedData = $request->validate([
            'produk_jadi_id' => 'required|exists:produk_jadi,id',
            'bahan_mentah_id' => 'required|exists:bahan_mentah,id',
            'jumlah_digunakan' => 'required|numeric|min:0.001', // Harus ada jumlah
        ]);

        // 2. Cek apakah kombinasi Produk dan Bahan sudah ada (UNIQUE index di migration)
        $exists = DaftarBahan::where('produk_jadi_id', $validatedData['produk_jadi_id'])
                             ->where('bahan_mentah_id', $validatedData['bahan_mentah_id'])
                             ->exists();

        if ($exists) {
            return redirect()->back()
                             ->withInput()
                             ->withErrors(['bahan_mentah_id' => 'Bahan ini sudah ada di dalam resep produk tersebut. Silakan gunakan fungsi edit.']);
        }

        // 3. Simpan ke Database
        DaftarBahan::create($validatedData);

        // 4. Redirect
        $produkJadi = ProdukJadi::find($validatedData['produk_jadi_id']);

        return redirect()->route('daftar-bahan.index')
                         ->with('success', 'Bahan "' . $produkJadi->nama . '" berhasil ditambahkan ke resep!');
    }

    // Metode SHOW, EDIT, dan UPDATE disederhanakan/dihapus untuk fokus pada INDEX, CREATE, dan DESTROY karena BOM biasanya diedit melalui form CREATE/DELETE item.
    // Jika perlu edit, kita bisa menggunakan form edit yang sederhana.

    /**
     * Menampilkan formulir untuk mengedit kuantitas bahan dalam resep. (UPDATE - Form)
     */
    public function edit(DaftarBahan $daftarBahan)
    {
        $produk = ProdukJadi::find($daftarBahan->produk_jadi_id);
        $bahan = BahanMentah::find($daftarBahan->bahan_mentah_id);

        return view('daftar_bahan.edit', compact('daftarBahan', 'produk', 'bahan'));
    }

    /**
     * Memperbarui item bahan dalam resep. (UPDATE - Store)
     */
    public function update(Request $request, DaftarBahan $daftarBahan)
    {
        // Hanya validasi jumlah yang digunakan
        $validatedData = $request->validate([
            'jumlah_digunakan' => 'required|numeric|min:0.001',
        ]);

        $daftarBahan->update($validatedData);

        return redirect()->route('daftar-bahan.index')
                         ->with('success', 'Jumlah bahan dalam resep berhasil diperbarui!');
    }

    /**
     * Menghapus item bahan dari resep. (DELETE)
     */
    public function destroy(DaftarBahan $daftarBahan)
    {
        $namaProduk = $daftarBahan->produkJadi->nama;
        $namaBahan = $daftarBahan->bahanMentah->nama;

        $daftarBahan->delete();

        return redirect()->route('daftar-bahan.index')
                         ->with('danger', "Bahan '{$namaBahan}' berhasil dihapus dari resep '{$namaProduk}'.");
    }
}
