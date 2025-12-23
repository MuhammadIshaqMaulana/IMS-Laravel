@extends('layouts.app')

@section('title', 'Edit Item: ' . $item->nama)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="d-flex align-items-center mb-4">
            <a href="{{ route('item.show', $item->id) }}" class="btn btn-outline-secondary btn-sm me-3"><i class="fas fa-arrow-left"></i></a>
            <h2 class="fw-bold m-0">Edit Item: <span class="text-primary">{{ $item->nama }}</span></h2>
        </div>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-warning py-3 text-dark fw-bold">Perbarui Detail Produk / Aset</div>

            <div class="card-body p-4">
                <form action="{{ route('item.update', $item->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- 1. INFORMASI UTAMA -->
                    <div class="row">
                        <!-- Nama Item -->
                        <div class="col-md-8 mb-3">
                            <label for="nama" class="form-label fw-bold small">Nama Item / Produk <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg @error('nama') is-invalid @enderror" id="nama" name="nama" value="{{ old('nama', $item->nama) }}" required maxlength="100">
                             <small class="text-muted">SKU Sistem: <code>{{ $item->sku ?? 'Otomatis' }}</code></small>
                            @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <!-- Lokasi Folder -->
                        <div class="col-md-4 mb-3">
                            <label for="folder_id" class="form-label fw-bold small">Lokasi Folder</label>
                            <select class="form-select form-select-lg @error('folder_id') is-invalid @enderror" name="folder_id">
                                <option value="">(Root / Utama)</option>
                                @foreach($allFolders as $f)
                                    <option value="{{ $f->id }}" {{ old('folder_id', $item->folder_id) == $f->id ? 'selected' : '' }}>{{ $f->nama }}</option>
                                @endforeach
                            </select>
                            @error('folder_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <!-- 2. STOK, UNIT, & HARGA -->
                    <div class="row mt-2">
                         <div class="col-md-3 mb-3">
                            <label for="satuan" class="form-label fw-bold small">Unit (Satuan)</label>
                            <input type="text" class="form-control @error('satuan') is-invalid @enderror" id="satuan" name="satuan" value="{{ old('satuan', $item->satuan) }}" placeholder="kg, pcs, dsb">
                            @error('satuan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="stok_saat_ini" class="form-label fw-bold small text-primary">Quantity (Stok Saat Ini)</label>
                            <input type="number" step="0.01" class="form-control border-primary bg-primary-subtle @error('stok_saat_ini') is-invalid @enderror" id="stok_saat_ini" name="stok_saat_ini" value="{{ old('stok_saat_ini', $item->stok_saat_ini) }}">
                            <small class="text-muted" style="font-size: 0.6rem;">*Perubahan manual di sini akan dicatat sebagai aktivitas penyesuaian.</small>
                            @error('stok_saat_ini') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="stok_minimum" class="form-label fw-bold small">Stok Minimum (Alert)</label>
                            <input type="number" step="0.01" class="form-control @error('stok_minimum') is-invalid @enderror" id="stok_minimum" name="stok_minimum" value="{{ old('stok_minimum', $item->stok_minimum) }}">
                            @error('stok_minimum') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="harga_jual" class="form-label fw-bold small">Harga Jual (Rp)</label>
                            <input type="number" class="form-control @error('harga_jual') is-invalid @enderror" id="harga_jual" name="harga_jual" value="{{ old('harga_jual', $item->harga_jual) }}">
                            @error('harga_jual') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="pemasok" class="form-label fw-bold small">Pemasok Utama</label>
                            <input type="text" class="form-control @error('pemasok') is-invalid @enderror" id="pemasok" name="pemasok" value="{{ old('pemasok', $item->pemasok) }}" maxlength="100" placeholder="Nama supplier">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tags_input" class="form-label fw-bold small">Label / Tags (Pisahkan dengan koma)</label>
                            <input type="text" class="form-control @error('tags_input') is-invalid @enderror" id="tags_input" name="tags_input" value="{{ old('tags_input', is_array($item->tags) ? implode(', ', $item->tags) : '') }}" placeholder="cth: promo, bahan_baku, impor">
                        </div>
                    </div>

                    <!-- 3. NOTES -->
                    <div class="mb-4">
                        <label for="note" class="form-label fw-bold small">Catatan Internal (Notes)</label>
                        <textarea class="form-control @error('note') is-invalid @enderror" id="note" name="note" rows="4" placeholder="Ketik informasi tambahan di sini...">{{ old('note', $item->note) }}</textarea>
                        @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-4">
                        <a href="{{ route('item.show', $item->id) }}" class="btn btn-light px-4">Batal</a>
                        <button type="submit" class="btn btn-warning px-5 fw-bold shadow-sm">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
