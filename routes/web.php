<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BahanMentahController;
use App\Http\Controllers\ProdukJadiController;
use App\Http\Controllers\DaftarBahanController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\ApiPythonController; // Impor Controller API baru

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Rute Dasar (Halaman Awal - Welcome)
Route::get('/', function () {
    return view('welcome');
})->name('home');

// --- Modul Inventaris Inti ---
Route::resource('bahan-mentah', BahanMentahController::class);
Route::resource('produk-jadi', ProdukJadiController::class);
Route::resource('daftar-bahan', DaftarBahanController::class);
Route::resource('transaksi', TransaksiController::class);

// --- Modul Integrasi API Python ---
// Rute untuk menampilkan halaman uji API
Route::get('uji-api', [ApiPythonController::class, 'index'])->name('api-python.index');
// Rute untuk mengirimkan data validasi SKU
Route::post('uji-api/validasi-sku', [ApiPythonController::class, 'validasiSku'])->name('api-python.validasi-sku');
