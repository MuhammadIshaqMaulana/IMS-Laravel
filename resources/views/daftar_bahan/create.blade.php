@extends('layouts.app')

@section('title', 'Tambahkan Bahan ke Resep')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <h1 class="mb-4">Tambahkan Bahan ke Resep Produk</h1>
        <div class="card">
            <div class="card-header bg-primary text-white">Hubungkan Produk dan Bahan</div>

            <div class="card-body">
                <form action="{{ route('daftar-bahan.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="produk_jadi_id" class="form-label">Produk Jadi <span class="text-danger">*</span></label>
                        <select class="form-select @error('produk_jadi_id') is-invalid @enderror" id="produk_jadi_id" name="produk_jadi_id" required>
                            <option value="">-- Pilih Produk Jadi --</option>
                            @foreach ($produk as $item)
                                <option value="{{ $item->id }}" {{ old('produk_jadi_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->nama }} (SKU: {{ $item->sku }})
                                </option>
                            @endforeach
                        </select>
                        @error('produk_jadi_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="bahan_mentah_id" class="form-label">Bahan Mentah <span class="text-danger">*</span></label>
                        <select class="form-select @error('bahan_mentah_id') is-invalid @enderror" id="bahan_mentah_id" name="bahan_mentah_id" required>
                            <option value="">-- Pilih Bahan Mentah --</option>
                            @foreach ($bahan as $item)
                                <option value="{{ $item->id }}" {{ old('bahan_mentah_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->nama }} (Satuan: {{ $item->satuan }})
                                </option>
                            @endforeach
                        </select>
                        @error('bahan_mentah_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Anda tidak dapat menambahkan bahan yang sudah ada di resep ini.</small>
                    </div>

                    <div class="mb-3">
                        <label for="jumlah_digunakan" class="form-label">Jumlah Bahan Digunakan <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" class="form-control @error('jumlah_digunakan') is-invalid @enderror" id="jumlah_digunakan" name="jumlah_digunakan" value="{{ old('jumlah_digunakan') }}" required min="0.001" placeholder="Cth: 0.5 (untuk setengah kilogram)">
                        <small class="text-muted">Jumlah yang dibutuhkan untuk membuat 1 unit Produk Jadi.</small>
                        @error('jumlah_digunakan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <a href="{{ route('daftar-bahan.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-success float-end">Simpan Resep</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
