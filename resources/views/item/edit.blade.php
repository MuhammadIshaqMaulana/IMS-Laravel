@extends('layouts.app')

@section('title', 'Edit Item: ' . $item->nama)

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <h1 class="mb-4">Edit Item: <span class="text-primary">{{ $item->nama }}</span></h1>
        <div class="card shadow-sm">
            <div class="card-header bg-warning text-dark">Informasi Item Universal</div>

            <div class="card-body">
                <form action="{{ route('item.update', $item->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- 1. KLASIFIKASI & NAMA -->
                    <div class="row">
                         <!-- Jenis Item -->
                        <div class="col-md-4 mb-3">
                            <label for="jenis_item" class="form-label">Klasifikasi Item <span class="text-danger">*</span></label>
                            <select class="form-select @error('jenis_item') is-invalid @enderror" id="jenis_item" name="jenis_item" required>
                                <option value="bahan_mentah" {{ old('jenis_item', $item->jenis_item) == 'bahan_mentah' ? 'selected' : '' }}>Bahan Mentah</option>
                                <option value="produk_jadi" {{ old('jenis_item', $item->jenis_item) == 'produk_jadi' ? 'selected' : '' }}>Produk Jadi</option>
                                <option value="asset" {{ old('jenis_item', $item->jenis_item) == 'asset' ? 'selected' : '' }}>Aset/Perlengkapan</option>
                            </select>
                            @error('jenis_item')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                         <!-- Nama Item -->
                        <div class="col-md-8 mb-3">
                            <label for="nama" class="form-label">Nama Item <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('nama') is-invalid @enderror" id="nama" name="nama" value="{{ old('nama', $item->nama) }}" required maxlength="100">
                             <small class="text-muted">SKU: {{ $item->sku ?? 'Otomatis dibuat jika tidak ada varian' }}</small>
                            @error('nama')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- 2. STOK, UNIT, & HARGA -->
                    <div class="row">
                         <div class="col-md-4 mb-3">
                            <label for="satuan" class="form-label">Unit of Measure <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('satuan') is-invalid @enderror" id="satuan" name="satuan" value="{{ old('satuan', $item->satuan) }}" required maxlength="20" placeholder="Cth: kg, pcs, box">
                            @error('satuan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="stok_saat_ini" class="form-label">Quantity (Stok Saat Ini)</label>
                            <input type="number" step="0.01" class="form-control @error('stok_saat_ini') is-invalid @enderror" id="stok_saat_ini" name="stok_saat_ini" value="{{ old('stok_saat_ini', $item->stok_saat_ini) }}" min="0">
                            <small class="text-muted">Untuk penyesuaian stok, sebaiknya gunakan Transaksi.</small>
                            @error('stok_saat_ini')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="harga_jual" class="form-label">Price (Nilai Item @)</label>
                            <input type="number" step="1" class="form-control @error('harga_jual') is-invalid @enderror" id="harga_jual" name="harga_jual" value="{{ old('harga_jual', $item->harga_jual) }}" min="0">
                            <small class="text-muted">Akan dibulatkan ke bawah (integer).</small>
                            @error('harga_jual')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="stok_minimum" class="form-label">Min. Level (Stok Minimum)</label>
                            <input type="number" step="0.01" class="form-control @error('stok_minimum') is-invalid @enderror" id="stok_minimum" name="stok_minimum" value="{{ old('stok_minimum', $item->stok_minimum) }}" min="0">
                            @error('stok_minimum')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="pemasok" class="form-label">Pemasok</label>
                            <input type="text" class="form-control @error('pemasok') is-invalid @enderror" id="pemasok" name="pemasok" value="{{ old('pemasok', $item->pemasok) }}" maxlength="100">
                            @error('pemasok')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr>

                    @php
                        // Memproses Tags lama menjadi string koma-separated
                        $tagsString = is_array($item->tags) ? implode(', ', $item->tags) : '';

                        // Memproses Custom Field lama (Contoh: hanya CF1)
                        $cfName = 'Perlu Pendingin'; // Default name
                        $cfActive = false;
                        if (is_array($item->custom_fields) && !empty($item->custom_fields)) {
                             $cfName = array_keys($item->custom_fields)[0] ?? 'Perlu Pendingin';
                             $cfActive = true;
                        }
                    @endphp

                    <!-- 3. NOTES, TAGS, CUSTOM FIELDS -->
                    <div class="row">
                        <!-- Tags -->
                        <div class="col-md-6 mb-3">
                            <label for="tags_input" class="form-label">Tags</label>
                            <input type="text" class="form-control @error('tags_input') is-invalid @enderror" id="tags_input" name="tags_input" value="{{ old('tags_input', $tagsString) }}" placeholder="Cth: roti, manis, promo (pisahkan dengan koma)">
                             @error('tags_input')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Custom Field 1 (Boolean) -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label d-block">Custom Fields (Boolean)</label>
                            <div class="input-group">
                                <div class="input-group-text">
                                    <input class="form-check-input mt-0" type="checkbox" name="is_custom_field_1_active" id="is_custom_field_1_active" value="1" {{ old('is_custom_field_1_active', $cfActive) ? 'checked' : '' }}>
                                </div>
                                <input type="text" class="form-control @error('custom_field_1_name') is-invalid @enderror" name="custom_field_1_name" placeholder="Nama Custom Field 1" value="{{ old('custom_field_1_name', $cfName) }}" maxlength="50">
                                 @error('custom_field_1_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="note" class="form-label">Notes (Catatan)</label>
                        <textarea class="form-control @error('note') is-invalid @enderror" id="note" name="note" rows="3">{{ old('note', $item->note) }}</textarea>
                        @error('note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <a href="{{ route('item.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-warning float-end">Perbarui Item</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
