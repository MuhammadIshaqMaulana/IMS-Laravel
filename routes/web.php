<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BahanMentahController;
use App\Http\Controllers\ProdukJadiController;
use App\Http\Controllers\DaftarBahanController;
use App\Http\Controllers\TransaksiController; // Diubah dari TransaksiProduksiController

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Di sinilah Anda dapat mendaftarkan rute web untuk aplikasi Anda.
| Rute-rute ini dimuat oleh RouteServiceProvider dalam grup yang
| berisi grup middleware "web". Sekarang buatlah sesuatu yang hebat!
|
*/

// Rute Dasar (Halaman Awal - Welcome)
// Opsi 1: Menampilkan welcome.blade.php (yang sudah dimodifikasi)
// Pengguna harus mengklik tombol di halaman ini untuk masuk ke IMS.
Route::get('/', function () {
    return view('welcome');
})->name('home'); // Berikan nama rute 'home' untuk referensi navigasi

// --- Modul 1: Bahan Mentah (Stok Bahan Baku) ---
Route::resource('bahan-mentah', BahanMentahController::class);

// --- Modul 2: Produk Jadi (Stok Produk Akhir) ---
Route::resource('produk-jadi', ProdukJadiController::class);

// --- Modul 3: Daftar Bahan / Resep (Bill of Materials - BOM) ---
// Kita akan buat controller dan views-nya di langkah selanjutnya
Route::resource('daftar-bahan', DaftarBahanController::class);

// --- Modul 4: Transaksi (Resource Diubah) ---
Route::resource('transaksi', TransaksiController::class); // Route resource dan Controller diubah menjadi 'transaksi'
