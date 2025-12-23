<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\ApiPythonController;
use App\Http\Controllers\GoogleAuthController;

/*
|--------------------------------------------------------------------------
| Web Routes - IMS Refactored (Sortly Style)
|--------------------------------------------------------------------------
*/

// --- Rute Dasar (Akses Publik) ---
Route::get('/', function () {
    return view('welcome');
})->name('home');

// FIX: Rute login fallback untuk Middleware Auth
Route::get('login', function () {
    return redirect()->route('home')->with('error', 'Silakan Login terlebih dahulu.');
})->name('login');

// --- Rute Autentikasi Google ---
Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('login.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
Route::post('logout', [GoogleAuthController::class, 'logout'])->name('logout');

// --- Rute IMS Inti (Dilindungi Middleware 'auth') ---
Route::middleware(['auth'])->group(function () {

    // 1. Dashboard Utama
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. Modul Item Universal (Inti Sortly)
    // Rute Ekspor & Bulk (Harus di atas resource agar tidak dianggap ID)
    Route::get('item/export/csv', [ItemController::class, 'exportCsv'])->name('item.export.csv');
    Route::get('item/export/pdf', [ItemController::class, 'exportPdf'])->name('item.export.pdf');
    Route::post('item/bulk-update', [ItemController::class, 'bulkUpdate'])->name('item.bulk-update');

    Route::post('/item/bulk-clone', [ItemController::class, 'bulkClone'])->name('item.bulk-clone');
    Route::post('/item/bulk-update-quantity', [ItemController::class, 'bulkUpdateQuantity'])->name('item.bulk-update-quantity');

    // Resource CRUD Item
    Route::resource('item', ItemController::class);

    // BARU: Aksi Cepat (Quick Actions) di Card
    Route::post('item/{item}/update-quantity', [ItemController::class, 'updateQuantity'])->name('item.update-quantity');
    Route::post('inventory/move', [ItemController::class, 'move'])->name('item.move');

    // 3. Modul Folder & Hierarki
    Route::put('/folder/{folder}/update', [ItemController::class, 'updateFolder'])->name('folder.update');
    Route::get('folders', [ItemController::class, 'folderIndex'])->name('folder.index');
    Route::post('folders/create', [ItemController::class, 'createFolder'])->name('folder.create');
    // Rute moveItemToFolder (Fase 11) dialihkan ke item.move yang lebih spesifik per item

    // 4. Modul Transaksi (Histori Aktivitas / Produksi BOM)
    Route::resource('transaksi', TransaksiController::class);

    // 5. Modul Laporan
    Route::get('laporan/stok-minimum', [LaporanController::class, 'stokMinimum'])->name('laporan.stok-minimum');

    // 6. Integrasi API Python
    Route::get('uji-api', [ApiPythonController::class, 'index'])->name('api-python.index');
    Route::post('uji-api/validasi-sku', [ApiPythonController::class, 'validasiSku'])->name('api-python.validasi-sku');
});
