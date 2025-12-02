<?php

namespace App\Http\Controllers;

use App\Models\BahanMentah;
use Illuminate\Http\Request;

class BahanMentahController extends Controller
{
    /**
     * Menampilkan daftar semua bahan mentah. (READ - List)
     */
    public function index()
    {
        // Ambil semua data bahan mentah dari database, urutkan berdasarkan nama
        $bahanMentah = BahanMentah::orderBy('nama')->get();

        // Kirim data ke view 'bahan_mentah.index'
        return view('bahan_mentah.index', compact('bahanMentah'));
    }

    /**
     * Menampilkan formulir untuk membuat item bahan mentah baru. (CREATE - Form)
     */
    public function create()
    {
        return view('bahan_mentah.create');
    }

    /**
     * Menyimpan item bahan mentah baru ke database. (CREATE - Store)
     */
    public function store(Request $request)
    {
        // 1. Validasi Input
        $validatedData = $request->validate([
            'nama' => 'required|string|max:100|unique:bahan_mentah,nama',
            'satuan' => 'required|string|max:20',
            'stok_saat_ini' => 'required|numeric|min:0',
            'stok_minimum' => 'required|numeric|min:0',
            'pemasok' => 'nullable|string|max:100',
        ]);

        // 2. Simpan ke Database
        BahanMentah::create($validatedData);

        // 3. Redirect dengan pesan sukses
        return redirect()->route('bahan-mentah.index')
                         ->with('success', 'Bahan Mentah baru berhasil ditambahkan!');
    }

    /**
     * Menampilkan detail satu item bahan mentah (Opsional untuk CRUD sederhana). (READ - Single)
     */
    public function show(BahanMentah $bahanMentah)
    {
        return view('bahan_mentah.show', compact('bahanMentah'));
    }

    /**
     * Menampilkan formulir untuk mengedit item bahan mentah. (UPDATE - Form)
     */
    public function edit(BahanMentah $bahanMentah)
    {
        return view('bahan_mentah.edit', compact('bahanMentah'));
    }

    /**
     * Memperbarui item bahan mentah di database. (UPDATE - Store)
     */
    public function update(Request $request, BahanMentah $bahanMentah)
    {
        // Validasi, pastikan nama produk unik kecuali nama itu sendiri
        $validatedData = $request->validate([
            'nama' => 'required|string|max:100|unique:bahan_mentah,nama,' . $bahanMentah->id,
            'satuan' => 'required|string|max:20',
            'stok_saat_ini' => 'required|numeric|min:0',
            'stok_minimum' => 'required|numeric|min:0',
            'pemasok' => 'nullable|string|max:100',
        ]);

        $bahanMentah->update($validatedData);

        return redirect()->route('bahan-mentah.index')
                         ->with('success', 'Bahan Mentah berhasil diperbarui!');
    }

    /**
     * Menghapus item bahan mentah dari database. (DELETE)
     */
    public function destroy(BahanMentah $bahanMentah)
    {
        $bahanMentah->delete();

        return redirect()->route('bahan-mentah.index')
                         ->with('success', 'Bahan Mentah berhasil dihapus!');
    }
}
