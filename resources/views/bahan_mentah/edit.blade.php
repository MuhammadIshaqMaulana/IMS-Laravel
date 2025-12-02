@extends('layouts.app')

@section('title', 'Edit Bahan Mentah: ' . $bahanMentah->nama)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <h1 class="mb-4">Edit Bahan Mentah: <span class="text-primary">{{ $bahanMentah->nama }}</span></h1>
        <div class="card">
            <div class="card-header bg-warning text-dark">Perbarui Data Bahan</div>

            <div class="card-body">
                <form action="{{ route('bahan-mentah.update', $bahanMentah->id) }}" method="POST">
                    @csrf
                    @method('PUT') <!-- Digunakan untuk HTTP METHOD UPDATE di Laravel -->

                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Bahan <span class="text-danger">*</span></label>
                        <!-- Ditambahkan maxlength=100 -->
                        <input type="text" class="form-control @error('nama') is-invalid @enderror" id="nama" name="nama" value="{{ old('nama', $bahanMentah->nama) }}" required maxlength="100">
                        <small class="text-muted">Maksimal 100 karakter.</small>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="satuan" class="form-label">Satuan (Unit) <span class="text-danger">*</span></label>
                            <!-- Ditambahkan maxlength=20 -->
                            <input type="text" class="form-control @error('satuan') is-invalid @enderror" id="satuan" name="satuan" value="{{ old('satuan', $bahanMentah->satuan) }}" required maxlength="20" placeholder="Contoh: kg, gram, butir">
                            <small class="text-muted">Maksimal 20 karakter.</small>
                            @error('satuan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="pemasok" class="form-label">Pemasok</label>
                            <!-- Ditambahkan maxlength=100 -->
                            <input type="text" class="form-control @error('pemasok') is-invalid @enderror" id="pemasok" name="pemasok" value="{{ old('pemasok', $bahanMentah->pemasok) }}" maxlength="100">
                            <small class="text-muted">Maksimal 100 karakter.</small>
                            @error('pemasok')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="stok_saat_ini" class="form-label">Stok Saat Ini <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control @error('stok_saat_ini') is-invalid @enderror" id="stok_saat_ini" name="stok_saat_ini" value="{{ old('stok_saat_ini', $bahanMentah->stok_saat_ini) }}" required min="0">
                            <small class="text-muted">Perhatian: Mengubah stok di sini harusnya dilakukan melalui transaksi (tahap selanjutnya), namun untuk CRUD sederhana ini diizinkan.</small>
                            @error('stok_saat_ini')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="stok_minimum" class="form-label">Stok Minimum <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control @error('stok_minimum') is-invalid @enderror" id="stok_minimum" name="stok_minimum" value="{{ old('stok_minimum', $bahanMentah->stok_minimum) }}" required min="0">
                            @error('stok_minimum')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <a href="{{ route('bahan-mentah.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-success float-end">Perbarui Data</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
