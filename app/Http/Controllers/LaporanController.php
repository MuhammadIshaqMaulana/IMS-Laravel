<?php

namespace App\Http\Controllers;

use App\Models\BahanMentah;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    /**
     * Menampilkan daftar bahan mentah yang berada di bawah stok minimum (Reorder Point).
     */
    public function stokMinimum()
    {
        // Query: Ambil semua BahanMentah di mana stok_saat_ini <= stok_minimum
        $bahanKritis = BahanMentah::whereColumn('stok_saat_ini', '<=', 'stok_minimum')
                                  ->orderBy('nama', 'asc')
                                  ->get();

        // Mengambil semua bahan mentah untuk menampilkan konteks umum (jika diperlukan)
        $totalBahan = BahanMentah::count();

        return view('laporan.stok_minimum', compact('bahanKritis', 'totalBahan'));
    }

    // Metode laporan lain (misalnya, laporan inventaris penuh) dapat ditambahkan di sini
}
