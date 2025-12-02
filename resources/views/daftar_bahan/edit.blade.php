@extends('layouts.app')

@section('title', 'Edit Resep')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <h1 class="mb-4">Edit Kuantitas Resep</h1>
        <div class="card">
            <div class="card-header bg-warning text-dark">
                Perbarui Bahan untuk: <strong>{{ $produk->nama }}</strong>
            </div>

            <div class="card-body">
                <div class="alert alert-info">
                    Anda sedang mengedit bahan: <strong>{{ $bahan->nama }}</strong> (Satuan: {{ $bahan->satuan }})
                </div>

                <form action="{{ route('daftar-bahan.update', $daftarBahan->id) }}" method="POST">
                    @csrf
                    @method('PUT') <!-- Digunakan untuk HTTP METHOD UPDATE -->

                    <div class="mb-3">
                        <label for="jumlah_digunakan" class="form-label">Jumlah Bahan Digunakan <span class="text-danger">*</span></label>
                        <input type="number" step="0.001" class="form-control @error('jumlah_digunakan') is-invalid @enderror" id="jumlah_digunakan" name="jumlah_digunakan" value="{{ old('jumlah_digunakan', $daftarBahan->jumlah_digunakan) }}" required min="0.001" placeholder="Jumlah untuk 1 unit produk jadi">
                        <small class="text-muted">Masukkan kuantitas yang dibutuhkan dalam satuan {{ $bahan->satuan }}.</small>
                        @error('jumlah_digunakan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <a href="{{ route('daftar-bahan.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-success float-end">Perbarui Resep</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
