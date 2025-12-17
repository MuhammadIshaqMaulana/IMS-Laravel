@extends('layouts.app')

@section('title', 'Tambah Item Baru')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <h1 class="mb-4">Tambah Item Baru</h1>
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">Formulir Item Universal</div>

            <div class="card-body">
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form action="{{ route('item.store') }}" method="POST">
                    @csrf

                    <!-- 1. KLASIFIKASI & NAMA -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="nama" class="form-label">Nama Item <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('nama') is-invalid @enderror" id="nama" name="nama" value="{{ old('nama') }}" required maxlength="100">
                            @error('nama')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- 2. STOK, UNIT, & HARGA -->
                    <div class="row">
                         <div class="col-md-4 mb-3">
                            <label for="satuan" class="form-label">Unit of Measure <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('satuan') is-invalid @enderror" id="satuan" name="satuan" value="{{ old('satuan') }}" required maxlength="20" placeholder="Cth: kg, pcs, box">
                        </div>
                        <div class="col-md-4 mb-3" id="stok_input_container">
                            <label for="stok_saat_ini" class="form-label">Quantity (Stok Awal)</label>
                            <input type="number" step="0.01" class="form-control @error('stok_saat_ini') is-invalid @enderror" id="stok_saat_ini" name="stok_saat_ini" value="{{ old('stok_saat_ini', 0) }}" min="0">
                        </div>
                         <div class="col-md-4 mb-3">
                            <label for="harga_jual" class="form-label">Price (Nilai Item @)</label>
                            <input type="number" step="1" class="form-control @error('harga_jual') is-invalid @enderror" id="harga_jual" name="harga_jual" value="{{ old('harga_jual', 0) }}" min="0">
                            <small class="text-muted">Akan dibulatkan ke bawah (integer).</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="stok_minimum" class="form-label">Min. Level (Stok Minimum)</label>
                            <input type="number" step="0.01" class="form-control @error('stok_minimum') is-invalid @enderror" id="stok_minimum" name="stok_minimum" value="{{ old('stok_minimum', 0) }}" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="pemasok" class="form-label">Pemasok</label>
                            <input type="text" class="form-control @error('pemasok') is-invalid @enderror" id="pemasok" name="pemasok" value="{{ old('pemasok') }}" maxlength="100">
                        </div>
                    </div>

                    <hr>

                    <!-- 3. PILIHAN KLASIFIKASI LANJUTAN: ITEM NORMAL, BOM, atau VARIAN -->
                    <h5 class="mb-3">Klasifikasi Lanjutan</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_bom" name="is_bom" value="1" {{ old('is_bom') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_bom">**Item ini adalah BOM/Kit**</label>
                                <small class="d-block text-muted">Stok akan dihitung berdasarkan material penyusun.</small>
                            </div>
                        </div>
                         <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="has_variants_check" value="1" {{ old('variant_dimensions') ? 'checked' : '' }}>
                                <label class="form-check-label" for="has_variants_check">**Item ini memiliki Varian**</label>
                                <small class="d-block text-muted">Varian akan dibuat sebagai item terpisah.</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- AREA DINAMIS: BOM MATERIALS -->
                    <div id="materials_container" style="display: none; padding: 15px; border: 1px solid #ccc; border-radius: 5px;">
                        <h5 class="text-primary"><i class="fas fa-list"></i> Material Penyusun (BOM)</h5>
                        <p class="text-muted">Tambahkan material yang dibutuhkan untuk membuat 1 unit item ini.</p>

                        <!-- Template Row Material -->
                        <div id="material_rows">
                            <!-- Rows akan ditambahkan di sini oleh JS -->
                            @if(old('materials_data'))
                                <!-- Logika pemulihan old input harus dihandle oleh JS -->
                            @endif
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add_material_btn">
                            <i class="fas fa-plus"></i> Tambah Material
                        </button>
                         <input type="hidden" name="materials_data" id="materials_data">
                    </div>

                    <!-- AREA DINAMIS: VARIAN MULTI-DIMENSI -->
                    <div id="variants_container" style="display: none; margin-top: 15px; padding: 15px; border: 1px solid #ccc; border-radius: 5px;">
                        <h5 class="text-success"><i class="fas fa-cubes"></i> Dimensi Varian (Multi-Dimensi)</h5>
                        <p class="text-muted">Contoh: Dimensi 'Ukuran' dengan Opsi 'S, M, L'.</p>

                        <div id="variant_dimension_rows">
                             <!-- Rows Varian akan ditambahkan di sini oleh JS -->
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-success mt-2" id="add_dimension_btn">
                            <i class="fas fa-plus"></i> Tambah Dimensi Lain
                        </button>
                        <input type="hidden" name="variant_dimensions" id="variant_dimensions">
                    </div>

                    <hr>

                    <!-- 4. NOTES, TAGS, CUSTOM FIELDS -->
                    <!-- Tags -->
                    <div class="mb-3">
                        <label for="tags_input" class="form-label">Tags</label>
                        <input type="text" class="form-control" id="tags_input" name="tags_input" value="{{ old('tags_input') }}" placeholder="Cth: roti, manis, promo (pisahkan dengan koma)">
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="note" class="form-label">Notes (Catatan)</label>
                        <textarea class="form-control" id="note" name="note" rows="3">{{ old('note') }}</textarea>
                    </div>

                    <a href="{{ route('item.index') }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-success float-end" id="submit_form_btn">Simpan Item</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const isBomCheck = document.getElementById('is_bom');
    const hasVariantsCheck = document.getElementById('has_variants_check');
    const stokInputContainer = document.getElementById('stok_input_container');
    const materialsContainer = document.getElementById('materials_container');
    const variantsContainer = document.getElementById('variants_container');
    const addMaterialBtn = document.getElementById('add_material_btn');
    const materialRows = document.getElementById('material_rows');
    const addDimensionBtn = document.getElementById('add_dimension_btn');
    const dimensionRows = document.getElementById('variant_dimension_rows');
    const submitFormBtn = document.getElementById('submit_form_btn');
    const materialsDataInput = document.getElementById('materials_data');
    const variantDimensionsInput = document.getElementById('variant_dimensions');

    // Data Item yang tersedia untuk dropdown Material
    const availableMaterials = @json($allMaterials);

    // --- TEMPLATE & HELPER FUNCTIONS ---

    let materialIndex = 0;
    function getMaterialRowTemplate(index, selectedItemId = '', qty = '') {
        const options = availableMaterials.map(item =>
            `<option value="${item.id}" ${item.id == selectedItemId ? 'selected' : ''}>${item.nama} (${item.satuan}, Stok: ${item.stok_saat_ini})</option>`
        ).join('');

        return `
            <div class="row mb-2 material-row" data-index="${index}">
                <div class="col-md-6">
                    <select class="form-select material-item-select" name="material_item_${index}" required>
                        <option value="">-- Pilih Material/Item --</option>
                        ${options}
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="number" step="0.01" class="form-control material-qty-input" name="material_qty_${index}" placeholder="Qty Dibutuhkan (per 1 unit)" value="${qty}" required min="0.01">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm remove-material-btn" data-index="${index}"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `;
    }

    let dimensionIndex = 0;
    function getDimensionRowTemplate(index, name = '', options = '') {
        return `
            <div class="row mb-2 dimension-row" data-index="${index}">
                <div class="col-md-4">
                    <input type="text" class="form-control dimension-name-input" placeholder="Nama Dimensi (Cth: Ukuran)" value="${name}" required>
                </div>
                <div class="col-md-6">
                    <input type="text" class="form-control dimension-options-input" placeholder="Opsi Varian (Cth: S, M, L)" value="${options}" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm remove-dimension-btn" data-index="${index}"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        `;
    }

    // --- CORE LOGIC ---

    // 1. Manage Material (BOM) Inputs
    addMaterialBtn.addEventListener('click', () => {
        materialRows.insertAdjacentHTML('beforeend', getMaterialRowTemplate(materialIndex++));
    });

    materialRows.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-material-btn') || e.target.closest('.remove-material-btn')) {
            const row = e.target.closest('.material-row');
            if (row) row.remove();
        }
    });

    // 2. Manage Varian Inputs
    addDimensionBtn.addEventListener('click', () => {
        dimensionRows.insertAdjacentHTML('beforeend', getDimensionRowTemplate(dimensionIndex++));
    });

    dimensionRows.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-dimension-btn') || e.target.closest('.remove-dimension-btn')) {
            const row = e.target.closest('.dimension-row');
            if (row) row.remove();
        }
    });

    // 3. Manage Toggles (Item Normal vs BOM vs Varian)
    const toggleContainers = [materialsContainer, variantsContainer];

    function toggleFormSections() {
        const isBom = isBomCheck.checked;
        const hasVariants = hasVariantsCheck.checked;

        // Aturan Kritis: BOM dan Varian tidak bisa aktif bersamaan
        if (isBom && hasVariants) {
            alert('Perhatian: Item BOM/Kit tidak dapat memiliki Varian Multi-Dimensi. Silakan nonaktifkan salah satunya.');
            if (isBomCheck.checked) {
                hasVariantsCheck.checked = false;
            } else {
                isBomCheck.checked = false;
            }
            return toggleFormSections(); // Panggil ulang setelah perbaikan
        }

        // Stok input hanya muncul untuk Item Normal
        stokInputContainer.style.display = (isBom || hasVariants) ? 'none' : 'block';

        // Tampilkan/Sembunyikan Containers
        materialsContainer.style.display = isBom ? 'block' : 'none';
        variantsContainer.style.display = hasVariants ? 'block' : 'none';
    }

    isBomCheck.addEventListener('change', toggleFormSections);
    hasVariantsCheck.addEventListener('change', toggleFormSections);

    // --- FORM SUBMISSION & DATA PACKING ---
    submitFormBtn.addEventListener('click', (e) => {
        // e.preventDefault();

        // A. PACK MATERIAL DATA (Jika BOM aktif)
        if (isBomCheck.checked) {
            const materials = [];
            document.querySelectorAll('.material-row').forEach(row => {
                const itemId = row.querySelector('.material-item-select').value;
                const qty = row.querySelector('.material-qty-input').value;
                if (itemId && qty > 0) {
                    materials.push({
                        item_id: parseInt(itemId),
                        qty: parseFloat(qty)
                    });
                }
            });
            materialsDataInput.value = JSON.stringify(materials);
        } else {
             materialsDataInput.value = '';
        }

        // B. PACK VARIAN DATA (Jika Varian aktif)
        if (hasVariantsCheck.checked) {
            const dimensions = [];
            document.querySelectorAll('.dimension-row').forEach(row => {
                const name = row.querySelector('.dimension-name-input').value;
                const options = row.querySelector('.dimension-options-input').value;
                if (name && options) {
                    dimensions.push({ name: name, options: options });
                }
            });
            variantDimensionsInput.value = JSON.stringify(dimensions);
        } else {
             variantDimensionsInput.value = '';
        }

        // Lanjutkan submit form (e.preventDefault() dihapus agar form submit normal)
    });

    // --- PEMULIHAN OLD INPUT ---

    // 1. Pulihkan BOM input
    const oldMaterialsData = @json(old('materials_data'));
    if (oldMaterialsData) {
        try {
            const materials = JSON.parse(oldMaterialsData);
            isBomCheck.checked = true;
            materials.forEach(material => {
                materialRows.insertAdjacentHTML('beforeend', getMaterialRowTemplate(materialIndex++, material.item_id, material.qty));
            });
        } catch (e) {
            console.error("Failed to parse old materials data:", e);
        }
    }

    // 2. Pulihkan Varian input
    const oldVariantData = @json(old('variant_dimensions'));
    if (oldVariantData) {
        try {
            const dimensions = JSON.parse(oldVariantData);
            hasVariantsCheck.checked = true;
            dimensions.forEach(dim => {
                dimensionRows.insertAdjacentHTML('beforeend', getDimensionRowTemplate(dimensionIndex++, dim.name, dim.options));
            });
        } catch (e) {
            console.error("Failed to parse old variant data:", e);
        }
    }

    // Panggil sekali saat load untuk mengatur display awal
    toggleFormSections();
});
</script>
@endsection
