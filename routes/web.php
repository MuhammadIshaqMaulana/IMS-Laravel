<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\ApiPythonController;
use App\Http\Controllers\GoogleAuthController;

Route::get('/', function () { return view('welcome'); })->name('home');

Route::get('login', function () {
    return redirect()->route('home')->with('error', 'Silakan Login terlebih dahulu.');
})->name('login');

Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('login.google');
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
Route::post('logout', [GoogleAuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/item/search-ajax', [ItemController::class, 'searchAjax'])->name('item.search.ajax');

    // Item & Inventory Routes
    Route::get('item/export/csv', [ItemController::class, 'exportCsv'])->name('item.export.csv');
    Route::get('item/export/pdf', [ItemController::class, 'exportPdf'])->name('item.export.pdf');
    Route::post('item/import', [ItemController::class, 'importCsv'])->name('item.import'); // RUTE BARU
    Route::post('item/bulk-update', [ItemController::class, 'bulkUpdate'])->name('item.bulk-update');
    Route::post('item/bulk-clone', [ItemController::class, 'bulkClone'])->name('item.bulk-clone');
    Route::post('item/bulk-update-quantity', [ItemController::class, 'bulkUpdateQuantity'])->name('item.bulk-update-quantity');

    Route::resource('item', ItemController::class);
    Route::post('item/{item}/update-quantity', [ItemController::class, 'updateQuantity'])->name('item.update-quantity');
    Route::post('inventory/move', [ItemController::class, 'move'])->name('item.move');
    Route::put('/folder/{folder}/update', [ItemController::class, 'updateFolder'])->name('folder.update');
    Route::delete('/folder/{folder}/delete', [ItemController::class, 'destroyFolder'])->name('folder.destroy');

    Route::resource('transaksi', TransaksiController::class);
    Route::get('laporan/stok-minimum', [LaporanController::class, 'stokMinimum'])->name('laporan.stok-minimum');
});
