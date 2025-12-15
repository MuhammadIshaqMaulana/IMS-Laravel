<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController; // Baru
use App\Http\Controllers\ItemController; // Baru, Menggantikan Bahan Mentah/Produk Jadi
use App\Http\Controllers\DaftarBahanController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\LaporanController; // Baru
use App\Http\Controllers\ApiPythonController;
use App\Http\Controllers\GoogleAuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Ini adalah file routing inti Anda, dengan middleware 'auth' melindungi semua
| fungsi IMS utama, dan rute 'item' menggantikan CRUD lama.
*/

// Rute Dasar (Akses Publik)
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Rute 'Dashboard' yang akan menjadi halaman landing setelah login
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// FIX: Tambahkan rute 'login' yang disyaratkan oleh middleware 'auth' untuk menghindari Fatal Error
Route::get('login', function () {
    return redirect()->route('home')->with('error', 'Silakan Login untuk mengakses halaman ini.');
})->name('login');


// --- Rute Autentikasi Google (Akses Publik) ---
Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('login.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
Route::post('logout', [GoogleAuthController::class, 'logout'])->name('logout');


// --- Rute IMS Inti (Dilindungi Middleware 'auth') ---
// Semua rute di dalam grup ini hanya dapat diakses oleh Admin yang sudah login.
Route::middleware(['auth'])->group(function () {

    // [LAMA] Route::resource('bahan-mentah', ...); // DINONAKTIFKAN
    // [LAMA] Route::resource('produk-jadi', ...); // DINONAKTIFKAN

    // 1. Modul Item Universal (Menggantikan CRUD lama)
    Route::resource('item', ItemController::class);

    // 2. Modul Resep (Daftar Bahan)
    // Catatan: Controller ini harus diupdate di Fase 6.4 untuk menggunakan Model Item
    Route::resource('daftar-bahan', DaftarBahanController::class);

    // 3. Modul Transaksi
    // Catatan: Controller ini harus diupdate di Fase 6.4 untuk menggunakan Model Item
    Route::resource('transaksi', TransaksiController::class);

    // 4. Modul Laporan
    Route::get('laporan/stok-minimum', [LaporanController::class, 'stokMinimum'])->name('laporan.stok-minimum');

    // 5. Integrasi API Python
    Route::get('uji-api', [ApiPythonController::class, 'index'])->name('api-python.index');
    Route::post('uji-api/validasi-sku', [ApiPythonController::class, 'validasiSku'])->name('api-python.validasi-sku');
});
