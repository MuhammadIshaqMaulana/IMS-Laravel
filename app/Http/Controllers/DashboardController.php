<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaksi;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Inventory Summary

        $totalItems = Item::count();
        // Total Kuantitas hanya dihitung dari Item non-BOM (Material/Aset)
        $totalQuantity = Item::whereNull('materials')->sum('stok_saat_ini');

        // Total value: Total Value dihitung dari semua Item (Harga jual x Stok saat ini)
        $totalValue = Item::sum(DB::raw('stok_saat_ini * harga_jual'));

        // 2. Items that need restocking (Stok Kritis)
        // Item yang bukan BOM (Material/Aset)
        $itemsKritis = Item::whereNull('materials')
                                  ->whereColumn('stok_saat_ini', '<=', 'stok_minimum')
                                  ->orderBy('nama', 'asc')
                                  ->take(5)
                                  ->get();

        // 3. Folder Total (sementara dihitung dari Item yang memiliki materials, sebagai proxy untuk BOM)
        // Nanti akan dihitung dari Item yang punya tag 'folder'
        $totalFolders = Item::whereNotNull('materials')->count(); // Estimasi

        // 4. Recent Activity
        $recentActivity = Transaksi::with('itemProduksi')
                                    ->orderBy('tanggal_produksi', 'desc')
                                    ->take(10)
                                    ->get();

        return view('dashboard.index', compact(
            'totalItems',
            'totalQuantity',
            'totalValue',
            'itemsKritis',
            'totalFolders',
            'recentActivity'
        ));
    }
}
