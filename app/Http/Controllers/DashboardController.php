<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Folder;
use App\Models\Transaksi;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Total Semua Entitas (Item + BOM)
        $totalItems = Item::count();

        // 2. Total Kuantitas: Hanya Item Fisik (Bukan BOM)
        $totalQuantity = Item::whereNull('materials')->sum('stok_saat_ini');

        // 3. Total Value: Harga Jual x Stok (Hanya Item Fisik)
        $totalValue = Item::whereNull('materials')->sum(DB::raw('stok_saat_ini * harga_jual'));

        // 4. Item BOM Total (Item yang punya material)
        $totalBoms = Item::whereNotNull('materials')->count();

        // 5. Items Stok Kritis (Bukan BOM)
        $itemsKritis = Item::whereNull('materials')
                            ->whereColumn('stok_saat_ini', '<=', 'stok_minimum')
                            ->orderBy('nama', 'asc')
                            ->take(5)
                            ->get();

        // 6. Aktivitas Terbaru (Sudah pakai parsed_catatan di View)
        $recentActivity = Transaksi::orderBy('created_at', 'desc')
                                     ->take(10)
                                     ->get();

        return view('dashboard.index', compact(
            'totalItems',
            'totalQuantity',
            'totalValue',
            'itemsKritis',
            'totalBoms', // Kita ganti totalFolders jadi totalBoms biar lebih relevan
            'recentActivity'
        ));
    }
}
