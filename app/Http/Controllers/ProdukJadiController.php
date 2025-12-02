<?php

namespace App\Http\Controllers;

use App\Models\ProdukJadi;
use Illuminate\Http\Request;

class ProdukJadiController extends Controller
{
    /**
     * Menampilkan daftar semua produk jadi. (READ - List)
     */
    public function index()
    {
        // Ambil semua data produk jadi, urutkan berdasarkan nama
        $produkJadi = ProdukJadi::orderBy('nama')->get();

        // Kirim data ke view 'produk_jadi.index'
        return view('produk_jadi.index', compact('produkJadi'));
    }

    /**
     * Menampilkan formulir untuk membuat item produk jadi baru. (CREATE - Form)
     */
    public function create()
    {
        // Di tahap ini kita hanya fokus ke Produk Jadi, belum ke Daftar Bahan
        return view('produk_jadi.create');
    }

    /**
     * Menyimpan item produk jadi baru ke database. (CREATE - Store)
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $validatedData = $request->validate([
            'nama' => 'required|string|max:100|unique:produk_jadi,nama',
            'sku' => 'required|string|max:50|unique:produk_jadi,sku',
            'harga_jual' => 'required|numeric|min:0',
            'stok_di_tangan' => 'required|integer|min:0',
            'aktif' => 'boolean',
        ]);

        // 2. Simpan ke Database
        ProdukJadi::create($validatedData);

        // 3. Redirect dengan pesan sukses
        return redirect()->route('produk-jadi.index')
                         ->with('success', 'Produk Jadi baru berhasil ditambahkan!');
    }

    /**
     * Menampilkan detail satu item produk jadi (Opsional). (READ - Single)
     */
    public function show(ProdukJadi $produkJadi)
    {
        return view('produk_jadi.show', compact('produkJadi'));
    }

    /**
     * Menampilkan formulir untuk mengedit item produk jadi. (UPDATE - Form)
     */
    public function edit(ProdukJadi $produkJadi)
    {
        return view('produk_jadi.edit', compact('produkJadi'));
    }

    /**
     * Memperbarui item produk jadi di database. (UPDATE - Store)
     */
    public function update(Request $request, ProdukJadi $produkJadi)
    {
        // Validasi, pastikan nama dan SKU unik kecuali ID itu sendiri
        $validatedData = $request->validate([
            'nama' => 'required|string|max:100|unique:produk_jadi,nama,' . $produkJadi->id,
            'sku' => 'required|string|max:50|unique:produk_jadi,sku,' . $produkJadi->id,
            'harga_jual' => 'required|numeric|min:0',
            'stok_di_tangan' => 'required|integer|min:0',
            'aktif' => 'boolean',
        ]);

        $produkJadi->update($validatedData);

        return redirect()->route('produk-jadi.index')
                         ->with('success', 'Produk Jadi berhasil diperbarui!');
    }

    /**
     * Menghapus item produk jadi dari database. (DELETE)
     */
    public function destroy(ProdukJadi $produkJadi)
    {
        $produkJadi->delete();

        return redirect()->route('produk-jadi.index')
                         ->with('success', 'Produk Jadi berhasil dihapus!');
    }
}
