@extends('layouts.app')
@section('title', 'Tambah Baru')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <form action="{{ route('item.store') }}" method="POST" id="mainForm">
            @csrf
            <h3 class="fw-bold mb-4">Buat Entitas Baru</h3>

            <div class="card border-0 shadow-sm mb-4 p-4">
                <!-- Type Selection -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Pilih Tipe</label>
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="type" id="type_item" value="item" checked>
                            <label class="btn btn-outline-secondary w-100 p-3" for="type_item"><i class="fas fa-box d-block mb-2"></i> Item</label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="type" id="type_bom" value="bom">
                            <label class="btn btn-outline-primary w-100 p-3" for="type_bom"><i class="fas fa-layer-group d-block mb-2"></i> BOM</label>
                        </div>
                        <div class="col-4">
                            <input type="radio" class="btn-check" name="type" id="type_folder" value="folder">
                            <label class="btn btn-outline-warning w-100 p-3" for="type_folder"><i class="fas fa-folder d-block mb-2"></i> Folder</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control form-control-lg" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Add to Folder</label>
                        <select name="folder_id" class="form-select form-select-lg">
                            <option value="">(Root)</option>
                            @foreach($allFolders as $f)
                                <option value="{{ $f->id }}" {{ request('folder_id') == $f->id ? 'selected' : '' }}>{{ $f->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div id="standard_fields" class="row">
                    <div class="col-md-3 mb-3"><label class="form-label">Satuan</label><input type="text" name="satuan" class="form-control" placeholder="pcs, kg"></div>
                    <div class="col-md-3 mb-3" id="qty_group"><label class="form-label">Stok Awal</label><input type="number" name="stok_saat_ini" class="form-control" value="0"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Stok Minimum (Restored)</label><input type="number" name="stok_minimum" class="form-control" value="0"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Harga @</label><input type="number" name="harga_jual" class="form-control" value="0"></div>
                </div>

                <!-- Tags Input (Restored) -->
                <div class="mb-3">
                    <label class="form-label">Tags (Pisahkan dengan koma)</label>
                    <input type="text" name="tags_input" class="form-control" placeholder="cth: bahan_baku, impor, promo">
                </div>

                <!-- BOM SECTION (Restored JS) -->
                <div id="bom_section" style="display: none;" class="bg-light p-3 rounded mb-3 border">
                    <h6 class="fw-bold mb-3">Material Penyusun (BOM)</h6>
                    <div id="bom_rows"></div>
                    <button type="button" class="btn btn-sm btn-primary mt-2" id="add_material_btn"><i class="fas fa-plus"></i> Tambah Material</button>
                    <input type="hidden" name="materials_data" id="materials_data">
                </div>

                <!-- VARIANT SECTION (Restored JS) -->
                <div id="variant_toggle_container" class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="has_variants">
                        <label class="form-check-label">Buat Varian (Warna, Ukuran, dsb)</label>
                    </div>
                </div>
                <div id="variant_section" style="display: none;" class="bg-light p-3 rounded mb-3 border">
                    <h6 class="fw-bold mb-3">Dimensi Varian</h6>
                    <div id="variant_rows"></div>
                    <button type="button" class="btn btn-sm btn-success mt-2" id="add_variant_btn"><i class="fas fa-plus"></i> Tambah Dimensi</button>
                    <input type="hidden" name="variant_dimensions" id="variant_dimensions">
                </div>

                <div class="mt-3"><label class="form-label">Catatan</label><textarea name="note" class="form-control" rows="2"></textarea></div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100" id="btnSubmit">Simpan Sekarang</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const types = document.querySelectorAll('input[name="type"]');
        const updateUI = () => {
            const val = document.querySelector('input[name="type"]:checked').value;
            document.getElementById('standard_fields').style.display = val === 'folder' ? 'none' : 'flex';
            document.getElementById('bom_section').style.display = val === 'bom' ? 'block' : 'none';
            document.getElementById('qty_group').style.display = val === 'bom' ? 'none' : 'block';
            document.getElementById('variant_toggle_container').style.display = val === 'item' ? 'block' : 'none';
        };
        types.forEach(t => t.addEventListener('change', updateUI));

        // BOM Logic
        const bomRows = document.getElementById('bom_rows');
        document.getElementById('add_material_btn').addEventListener('click', () => {
            const div = document.createElement('div');
            div.className = 'row g-2 mb-2 material-row';
            div.innerHTML = `
                <div class="col-7"><select class="form-select mat-id">@foreach($allMaterials as $m)<option value="{{$m->id}}">{{$m->nama}}</option>@endforeach</select></div>
                <div class="col-3"><input type="number" class="form-control mat-qty" placeholder="Qty"></div>
                <div class="col-2"><button type="button" class="btn btn-danger w-100" onclick="this.parentElement.parentElement.remove()">&times;</button></div>`;
            bomRows.appendChild(div);
        });

        // Variant Logic
        const variantRows = document.getElementById('variant_rows');
        const hasVariants = document.getElementById('has_variants');
        hasVariants.addEventListener('change', () => {
            document.getElementById('variant_section').style.display = hasVariants.checked ? 'block' : 'none';
            document.getElementById('qty_group').style.display = hasVariants.checked ? 'none' : 'block';
        });
        document.getElementById('add_variant_btn').addEventListener('click', () => {
            const div = document.createElement('div');
            div.className = 'row g-2 mb-2 variant-row';
            div.innerHTML = `
                <div class="col-5"><input type="text" class="form-control var-name" placeholder="Cth: Ukuran"></div>
                <div class="col-5"><input type="text" class="form-control var-options" placeholder="S, M, L"></div>
                <div class="col-2"><button type="button" class="btn btn-danger w-100" onclick="this.parentElement.parentElement.remove()">&times;</button></div>`;
            variantRows.appendChild(div);
        });

        // Final Submit Packing
        document.getElementById('btnSubmit').addEventListener('click', (e) => {
            // Pack BOM
            const mats = [];
            document.querySelectorAll('.material-row').forEach(r => {
                mats.push({ item_id: r.querySelector('.mat-id').value, qty: r.querySelector('.mat-qty').value });
            });
            document.getElementById('materials_data').value = JSON.stringify(mats);

            // Pack Variants
            const vars = [];
            if(hasVariants.checked) {
                document.querySelectorAll('.variant-row').forEach(r => {
                    vars.push({ name: r.querySelector('.var-name').value, options: r.querySelector('.var-options').value });
                });
            }
            document.getElementById('variant_dimensions').value = JSON.stringify(vars);
        });

        updateUI();
    });
</script>
@endsection
