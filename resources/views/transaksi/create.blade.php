@extends('layouts.app')

@section('title', 'Catat Perakitan/Produksi Baru')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <h1 class="mb-4">Catat Perakitan/Produksi Baru (BOM/Kit)</h1>
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">Formulir Produksi</div>

            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        Mohon periksa kesalahan berikut:
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('transaksi.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="produk_jadi_id" class="form-label">Item BOM/Kit yang Diproduksi <span class="text-danger">*</span></label>
                        <select class="form-select @error('produk_jadi_id') is-invalid @enderror" id="produk_jadi_id" name="produk_jadi_id" required>
                            <option value="">-- Pilih Item BOM/Kit --</option>
                            <!-- DIUBAH: variabel dari produkJadiItems menjadi bomItems -->
                            @foreach ($bomItems as $item)
                                <option value="{{ $item->id }}" {{ old('produk_jadi_id') == $item->id ? 'selected' : '' }}>
                                    {{ $item->nama }} (Stok saat ini: {{ number_format($item->stok_saat_ini, 0) }} {{ $item->satuan ?? 'Unit' }})
                                </option>
                            @endforeach
                        </select>
                        @error('produk_jadi_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jumlah_produksi" class="form-label">Jumlah Unit Produksi/Perakitan <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('jumlah_produksi') is-invalid @enderror" id="jumlah_produksi" name="jumlah_produksi" value="{{ old('jumlah_produksi') }}" required min="1">
                            <small class="text-muted">Masukkan berapa banyak unit item BOM ini yang berhasil dirakit.</small>
                            @error('jumlah_produksi')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tanggal_produksi" class="form-label">Tanggal Produksi <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control @error('tanggal_produksi') is-invalid @enderror" id="tanggal_produksi" name="tanggal_produksi" value="{{ old('tanggal_produksi', now()->format('Y-m-d\TH:i')) }}" required>
                            @error('tanggal_produksi')
                                <div class="invalid-feedback">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="catatan" class="form-label">Catatan (Opsional)</label>
                        <textarea class="form-control @error('catatan') is-invalid @enderror" id="catatan" name="catatan" rows="3" maxlength="500">{{ old('catatan') }}</textarea>
                        @error('catatan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <a href="{{ route('transaksi.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-success float-end">Proses & Simpan Transaksi</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
