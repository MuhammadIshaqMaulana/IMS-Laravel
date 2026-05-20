<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    /**
     * Menampilkan daftar item yang berada di bawah stok minimum.
     */
    public function stokMinimum()
    {
        // Logika baru: Item adalah 'Bahan Mentah' jika kolom 'materials' kosong (bukan BOM/Kit)
        // Kita hanya cek item yang bukan BOM dan stoknya di bawah batas.
        $bahanKritis = Item::whereNull('materials')
                                  ->whereColumn('stok_saat_ini', '<=', 'stok_minimum')
                                  ->orderBy('nama', 'asc')
                                  ->get();

        // Mengambil semua Item yang bukan BOM
        $totalBahan = Item::whereNull('materials')->count();

        // Catatan: Nama variabel $bahanKritis dan $totalBahan dipertahankan untuk Views.
        return view('laporan.stok_minimum', compact('bahanKritis', 'totalBahan'));
    }
}
