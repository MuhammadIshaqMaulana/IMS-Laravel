<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BahanMentahController;
use App\Http\Controllers\ProdukJadiController;
use App\Http\Controllers\DaftarBahanController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\ApiPythonController;
use App\Http\Controllers\GoogleAuthController; // Impor GoogleAuthController

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Rute Dasar (Halaman Awal - Welcome)
Route::get('/', function () {
    return view('welcome');
})->name('home');

// --- Rute Autentikasi (Akses Publik) ---
Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('login.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
Route::post('logout', [GoogleAuthController::class, 'logout'])->name('logout');


// --- Rute IMS Inti (Dilindungi Middleware 'auth') ---
Route::middleware(['auth'])->group(function () {
    Route::resource('bahan-mentah', BahanMentahController::class);
    Route::resource('produk-jadi', ProdukJadiController::class);
    Route::resource('daftar-bahan', DaftarBahanController::class);
    Route::resource('transaksi', TransaksiController::class);

    // Rute API Python (masuk ke dalam grup auth)
    Route::get('uji-api', [ApiPythonController::class, 'index'])->name('api-python.index');
    Route::post('uji-api/validasi-sku', [ApiPythonController::class, 'validasiSku'])->name('api-python.validasi-sku');
});
