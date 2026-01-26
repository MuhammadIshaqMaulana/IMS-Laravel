@extends('layouts.app')
@section('title', 'Tambah Inventaris')

<!-- [BARU] Load Library Select2 untuk pencarian kencang -->
@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .select2-container--bootstrap-5 .select2-selection { border-radius: 0.375rem; border: 1px solid #dee2e6; }
</style>
@endsection

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

<!-- [DITIMPA] Bagian Script di bawah halaman -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        const bomSection = $('#bom_section');
        const qtyGroup = $('#qty_group');

        // Toggle BOM vs Normal
        $('#is_bom').on('change', function() {
            if(this.checked) {
                bomSection.show();
                qtyGroup.hide();
                $('input[name="stok_saat_ini"]').val(0);
            } else {
                bomSection.hide();
                qtyGroup.show();
            }
        });

        // Toggle Folder vs Item
        $('input[name="type"]').on('change', function() {
            $('#item_exclusive_fields').toggle(this.value === 'item');
        });

        // Toggle Varian
        $('#has_variants').on('change', function() {
            $('#variant_section').toggle(this.checked);
        });

        // FUNGSI TAMBAH MATERIAL (SELECT2 AJAX)
        $('#add_material_btn').on('click', function() {
            const rowId = 'mat-' + Date.now();
            const html = `
                <div class="row g-2 mb-2 material-row" id="${rowId}">
                    <div class="col-7">
                        <select class="form-select mat-id shadow-sm" required></select>
                    </div>
                    <div class="col-3">
                        <input type="number" step="0.01" class="form-control mat-qty shadow-sm" placeholder="Qty" required>
                    </div>
                    <div class="col-2">
                        <button type="button" class="btn btn-danger w-100" onclick="$('#${rowId}').remove()"><i class="fas fa-times"></i></button>
                    </div>
                </div>`;

            $('#bom_rows').append(html);

            // Inisialisasi Select2 pada baris yang baru dibuat
            $(`#${rowId} .mat-id`).select2({
                theme: 'bootstrap-5',
                placeholder: 'Cari bahan baku...',
                minimumInputLength: 2,
                ajax: {
                    url: "{{ route('item.search.ajax') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) { return { q: params.term }; },
                    processResults: function(data) { return data; },
                    cache: true
                }
            });
        });

        // Tambah Varian Row
        $('#add_variant_btn').on('click', function() {
            const html = `
                <div class="row g-2 mb-2 variant-row">
                    <div class="col-5"><input type="text" class="form-control var-name shadow-sm" placeholder="Contoh: Ukuran"></div>
                    <div class="col-5"><input type="text" class="form-control var-options shadow-sm" placeholder="S,M,L"></div>
                    <div class="col-2"><button type="button" class="btn btn-danger w-100" onclick="$(this).parent().parent().remove()"><i class="fas fa-times"></i></button></div>
                </div>`;
            $('#variant_rows').append(html);
        });

        // PRE-SUBMIT LOGIC (Kumpulkan data JSON)
        $('#mainForm').on('submit', function() {
            const mats = [];
            $('.material-row').each(function() {
                const id = $(this).find('.mat-id').val();
                const qty = $(this).find('.mat-qty').val();
                if(id && qty) mats.push({ item_id: id, qty: qty });
            });
            $('#materials_data').val(JSON.stringify(mats));

            if($('#has_variants').is(':checked')) {
                const vars = [];
                $('.variant-row').each(function() {
                    vars.push({ name: $(this).find('.var-name').val(), options: $(this).find('.var-options').val() });
                });
                $('#variant_dimensions').val(JSON.stringify(vars));
            }
        });
    });
</script>
@endsection
