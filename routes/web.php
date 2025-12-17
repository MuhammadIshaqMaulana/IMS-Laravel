<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\DaftarBahanController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\ApiPythonController;
use App\Http\Controllers\GoogleAuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Rute Dasar (Akses Publik)
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Rute 'Dashboard' yang akan menjadi halaman landing setelah login
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// FIX: Tambahkan rute 'login' yang disyaratkan oleh middleware 'auth'
Route::get('login', function () {
    return redirect()->route('home')->with('error', 'Silakan Login untuk mengakses halaman ini.');
})->name('login');


// --- Rute Autentikasi Google (Akses Publik) ---
Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('login.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
Route::post('logout', [GoogleAuthController::class, 'logout'])->name('logout');


// --- Rute IMS Inti (Dilindungi Middleware 'auth') ---
Route::middleware(['auth'])->group(function () {

    // 1. Modul Item Universal
    Route::resource('item', ItemController::class);
    Route::post('item/bulk-update', [ItemController::class, 'bulkUpdate'])->name('item.bulk-update');

    // Rute Ekspor (FIXED)
    Route::get('item/export/csv', [ItemController::class, 'exportCsv'])->name('item.export.csv');
    Route::get('item/export/pdf', [ItemController::class, 'exportPdf'])->name('item.export.pdf');

    // 2. Modul Resep (Daftar Bahan) - HANYA UNTUK DEPRECATION WARNING/REDIRECT
    Route::resource('daftar-bahan', DaftarBahanController::class); // Dipertahankan sementara

    // 3. Modul Transaksi
    Route::resource('transaksi', TransaksiController::class);

    // 4. Modul Laporan
    Route::get('laporan/stok-minimum', [LaporanController::class, 'stokMinimum'])->name('laporan.stok-minimum');

    // 5. Integrasi API Python
    Route::get('uji-api', [ApiPythonController::class, 'index'])->name('api-python.index');
    Route::post('uji-api/validasi-sku', [ApiPythonController::class, 'validasiSku'])->name('api-python.validasi-sku');

    // 6. Modul Folder Hierarchy
    Route::get('folders', [ItemController::class, 'folderIndex'])->name('folder.index');
    Route::post('folders/create', [ItemController::class, 'createFolder'])->name('folder.create');
    Route::post('folders/move', [ItemController::class, 'moveItemToFolder'])->name('folder.move');
});
