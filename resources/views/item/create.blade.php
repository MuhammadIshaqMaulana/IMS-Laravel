@extends('layouts.app')
@section('title', 'Tambah Inventaris')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <form action="{{ route('item.store') }}" method="POST" id="mainForm">
            @csrf
            <h3 class="fw-bold mb-4">Tambah Entitas Baru</h3>

            <!-- RADIO PILIHAN TIPE UTAMA -->
            <div class="card border-0 shadow-sm mb-4 p-4">
                <label class="form-label fw-bold">Tipe Entitas</label>
                <div class="row g-2 text-center mb-4">
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="type" id="type_item" value="item" checked>
                        <label class="btn btn-outline-primary w-100 p-3 fw-bold" for="type_item"><i class="fas fa-box d-block mb-2"></i> Inventaris (Item / BOM)</label>
                    </div>
                    <div class="col-6">
                        <input type="radio" class="btn-check" name="type" id="type_folder" value="folder">
                        <label class="btn btn-outline-warning w-100 p-3 fw-bold" for="type_folder"><i class="fas fa-folder d-block mb-2"></i> Folder Penyimpanan</label>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control form-control-lg" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Lokasi Folder</label>
                        <select name="folder_id" class="form-select form-select-lg">
                            <option value="">(Root)</option>
                            @foreach($allFolders as $f)
                                <option value="{{ $f->id }}" {{ request('folder_id') == $f->id ? 'selected' : '' }}>{{ $f->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- FORM KHUSUS ITEM / BOM -->
                <div id="item_exclusive_fields">
                    <div class="form-check form-switch mb-4 p-3 bg-light border rounded">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="is_bom" id="is_bom">
                        <label class="form-check-label fw-bold text-primary" for="is_bom"><i class="fas fa-layer-group me-1"></i> Aktifkan Mode BOM (Item ini hasil produksi dari bahan lain)</label>
                    </div>

                    <!-- Bagian Input Harga di Create & Edit -->
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label small fw-bold">Satuan</label>
                            <input type="text" name="satuan" class="form-control" value="{{ $item->satuan ?? '' }}">
                        </div>
                        <div class="col-md-3 mb-3" id="qty_group">
                            <label class="form-label small fw-bold text-primary">Stok</label>
                            <input type="number" name="stok_saat_ini" class="form-control border-primary" value="{{ $item->stok_saat_ini ?? 0 }}">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label small fw-bold text-muted">Hrg Beli</label>
                            <input type="number" name="harga_beli" class="form-control border-secondary" value="{{ $item->harga_beli ?? 0 }}">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label small fw-bold">Hrg Jual</label>
                            <input type="number" name="harga_jual" class="form-control" value="{{ $item->harga_jual ?? 0 }}">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label small fw-bold">Min Stok</label>
                            <input type="number" name="stok_minimum" class="form-control" value="{{ $item->stok_minimum ?? 0 }}">
                        </div>
                    </div>

                    <div id="bom_section" style="display: none;" class="bg-primary-subtle p-3 rounded mb-3 border border-primary-subtle">
                        <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-list me-2"></i> Komponen Material (Resep BOM)</h6>
                        <div id="bom_rows"></div>
                        <button type="button" class="btn btn-sm btn-primary mt-2" id="add_material_btn"><i class="fas fa-plus"></i> Tambah Bahan Baku</button>
                        <input type="hidden" name="materials_data" id="materials_data">
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="has_variants">
                        <label class="form-check-label fw-bold">Item ini memiliki varian</label>
                    </div>
                    <div id="variant_section" style="display: none;" class="bg-light p-3 rounded mb-3 border">
                        <h6 class="fw-bold mb-3">Dimensi Varian</h6>
                        <div id="variant_rows"></div>
                        <button type="button" class="btn btn-sm btn-success mt-2" id="add_variant_btn"><i class="fas fa-plus"></i> Tambah Dimensi</button>
                        <input type="hidden" name="variant_dimensions" id="variant_dimensions">
                    </div>

                    <div class="mb-3"><label class="form-label small fw-bold">Tags</label><input type="text" name="tags_input" class="form-control" placeholder="roti, promo"></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Catatan</label><textarea name="note" class="form-control" rows="2"></textarea></div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 shadow-sm" id="btnSubmit">Simpan</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const types = document.querySelectorAll('input[name="type"]');
        const itemFields = document.getElementById('item_exclusive_fields');
        const isBom = document.getElementById('is_bom');
        const bomSection = document.getElementById('bom_section');
        const qtyGroup = document.getElementById('qty_group');
        const hasVariants = document.getElementById('has_variants');
        const variantSection = document.getElementById('variant_section');

        // Logic Tipe Utama (Item vs Folder)
        types.forEach(t => t.addEventListener('change', () => {
            itemFields.style.display = t.value === 'folder' ? 'none' : 'block';
        }));

        // Logic BOM
        isBom.addEventListener('change', () => {
            bomSection.style.display = isBom.checked ? 'block' : 'none';
            qtyGroup.style.display = isBom.checked ? 'none' : 'block';
            if(isBom.checked) document.querySelector('input[name="stok_saat_ini"]').value = 0;
        });

        // Logic Varian
        hasVariants.addEventListener('change', () => { variantSection.style.display = hasVariants.checked ? 'block' : 'none'; });

        // Add BOM Row
        const bomRows = document.getElementById('bom_rows');
        document.getElementById('add_material_btn').addEventListener('click', () => {
            const div = document.createElement('div'); div.className = 'row g-2 mb-2 material-row';
            div.innerHTML = `<div class="col-7"><select class="form-select mat-id shadow-sm">@foreach($allMaterials as $m)<option value="{{$m->id}}">{{$m->nama}}</option>@endforeach</select></div>
                <div class="col-3"><input type="number" step="0.01" class="form-control mat-qty shadow-sm" placeholder="Qty"></div>
                <div class="col-2"><button type="button" class="btn btn-danger w-100" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-times"></i></button></div>`;
            bomRows.appendChild(div);
        });

        // Add Variant Row
        const variantRows = document.getElementById('variant_rows');
        document.getElementById('add_variant_btn').addEventListener('click', () => {
            const div = document.createElement('div'); div.className = 'row g-2 mb-2 variant-row';
            div.innerHTML = `<div class="col-5"><input type="text" class="form-control var-name shadow-sm" placeholder="Ukuran"></div>
                <div class="col-5"><input type="text" class="form-control var-options shadow-sm" placeholder="S, M, L"></div>
                <div class="col-2"><button type="button" class="btn btn-danger w-100" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-times"></i></button></div>`;
            variantRows.appendChild(div);
        });

        // Final Submit
        document.getElementById('btnSubmit').addEventListener('click', () => {
            const mats = [];
            document.querySelectorAll('.material-row').forEach(r => mats.push({ item_id: r.querySelector('.mat-id').value, qty: r.querySelector('.mat-qty').value }));
            document.getElementById('materials_data').value = JSON.stringify(mats);

            const vars = [];
            if(hasVariants.checked) {
                document.querySelectorAll('.variant-row').forEach(r => vars.push({ name: r.querySelector('.var-name').value, options: r.querySelector('.var-options').value }));
            }
            document.getElementById('variant_dimensions').value = JSON.stringify(vars);
        });
    });
</script>
@endsection
