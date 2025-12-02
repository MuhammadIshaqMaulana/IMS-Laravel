@extends('layouts.app')

@section('title', 'Edit Produk Jadi: ' . $produkJadi->nama)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <h1 class="mb-4">Edit Produk Jadi: <span class="text-primary">{{ $produkJadi->nama }}</span></h1>
        <div class="card">
            <div class="card-header bg-warning text-dark">Perbarui Data Produk</div>

            <div class="card-body">
                <form action="{{ route('produk-jadi.update', $produkJadi->id) }}" method="POST">
                    @csrf
                    @method('PUT') <!-- Digunakan untuk HTTP METHOD UPDATE di Laravel -->

                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Produk <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('nama') is-invalid @enderror" id="nama" name="nama" value="{{ old('nama', $produkJadi->nama) }}" required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="sku" class="form-label">SKU (Stock Keeping Unit) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('sku') is-invalid @enderror" id="sku" name="sku" value="{{ old('sku', $produkJadi->sku) }}" required>
                        @error('sku')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="harga_jual" class="form-label">Harga Jual <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control @error('harga_jual') is-invalid @enderror" id="harga_jual" name="harga_jual" value="{{ old('harga_jual', $produkJadi->harga_jual) }}" required min="0">
                            @error('harga_jual')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="stok_di_tangan" class="form-label">Stok di Tangan <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('stok_di_tangan') is-invalid @enderror" id="stok_di_tangan" name="stok_di_tangan" value="{{ old('stok_di_tangan', $produkJadi->stok_di_tangan) }}" required min="0">
                            <small class="text-muted">Hanya ubah jika diperlukan; seharusnya diubah via transaksi penjualan/produksi.</small>
                            @error('stok_di_tangan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="aktif" name="aktif" value="1" {{ old('aktif', $produkJadi->aktif) ? 'checked' : '' }}>
                        <label class="form-check-label" for="aktif">Aktifkan Produk</label>
                    </div>

                    <a href="{{ route('produk-jadi.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-success float-end">Perbarui Data</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
