<?php

namespace App\Http\Controllers;

use App\Models\Item; // Diubah dari BahanMentah
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    /**
     * Menampilkan daftar item yang berada di bawah stok minimum.
     */
    public function stokMinimum()
    {
        // Query: Ambil Item di mana stok_saat_ini <= stok_minimum
        // Kita hanya fokus pada Item yang ditandai sebagai 'bahan_mentah' untuk tujuan restock.
        $bahanKritis = Item::where('jenis_item', 'bahan_mentah')
                                  ->whereColumn('stok_saat_ini', '<=', 'stok_minimum')
                                  ->orderBy('nama', 'asc')
                                  ->get();

        // Mengambil semua item Bahan Mentah untuk konteks
        $totalBahan = Item::where('jenis_item', 'bahan_mentah')->count();

        return view('laporan.stok_minimum', compact('bahanKritis', 'totalBahan'));
    }
}
