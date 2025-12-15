<?php

namespace App\Http\Controllers;

use App\Models\Item; // Diubah dari BahanMentah/ProdukJadi
use App\Models\Transaksi;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Inventory Summary

        $totalItems = Item::count();
        $totalQuantity = Item::sum('stok_saat_ini');

        // Total value: Stok Produk Jadi * Harga Jual (Harga Jual kini ada di Model Item)
        $totalValue = Item::where('jenis_item', 'produk_jadi')
                          ->sum(DB::raw('stok_saat_ini * harga_jual'));

        // 2. Items that need restocking (Stok Kritis)
        $itemsKritis = Item::where('jenis_item', 'bahan_mentah')
                                  ->whereColumn('stok_saat_ini', '<=', 'stok_minimum')
                                  ->orderBy('nama', 'asc')
                                  ->take(5) // Ambil 5 item teratas
                                  ->get();

        // 3. Recent Activity (Ambil 10 transaksi terakhir)
        // Transaksi Model belum diubah relasinya, jadi kita biarkan dulu.
        $recentActivity = Transaksi::with('produkJadi') // Catatan: Relasi ini perlu diubah di Model Transaksi di fase selanjutnya
                                    ->orderBy('tanggal_produksi', 'desc')
                                    ->take(10)
                                    ->get();

        return view('dashboard.index', compact(
            'totalItems',
            'totalQuantity',
            'totalValue',
            'itemsKritis',
            'recentActivity'
        ));
    }
}
