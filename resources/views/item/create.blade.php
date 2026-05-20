@extends('layouts.app')
@section('title', 'Tambah Inventaris Baru')

@section('styles')
<style>
    .search-results-wrapper { position: relative; }
    .search-results-list {
        position: absolute; top: 100%; left: 0; right: 0;
        z-index: 1000; background: white; border: 1px solid #ddd;
        max-height: 200px; overflow-y: auto; display: none;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .search-results-list .list-group-item { cursor: pointer; border: none; padding: 8px 12px; }
    .search-results-list .list-group-item:hover { background: #f8f9fa; color: #895129; }
</style>
@endsection

@section('content')
<div class="row justify-content-center pb-5">
    <div class="col-lg-10">
        <form action="{{ route('item.store') }}" method="POST" id="mainForm">
            @csrf
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h2 class="fw-bold m-0">Tambah Entitas Baru</h2>
                    <p class="text-muted">Buat item tunggal, folder, atau varian produk massal.</p>
                </div>
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow" id="btnSubmit">Simpan Data</button>
            </div>

            <!-- PILIHAN TIPE -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <label class="form-label fw-bold">Tipe Entitas</label>
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="type" id="type_item" value="item" checked>
                            <label class="btn btn-outline-primary w-100 p-3 fw-bold" for="type_item"><i class="fas fa-box d-block mb-2 fa-lg"></i> Item Inventaris</label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="type" id="type_folder" value="folder">
                            <label class="btn btn-outline-warning w-100 p-3 fw-bold" for="type_folder"><i class="fas fa-folder d-block mb-2 fa-lg"></i> Folder Baru</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INFORMASI UTAMA -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold">Nama Item / Folder <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control form-control-lg" placeholder="Contoh: Baut Baja" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Lokasi Folder</label>
                            <select name="folder_id" class="form-select form-select-lg">
                                <option value="">(Root / Utama)</option>
                                @foreach($allFolders as $f)
                                    <option value="{{ $f->id }}" {{ request('folder_id') == $f->id ? 'selected' : '' }}>{{ $f->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="item_fields">
                        <hr class="my-4">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label small fw-bold">Satuan</label>
                                <input type="text" name="satuan" class="form-control" placeholder="pcs, kg, box">
                            </div>
                            <div class="col-md-3 mb-3" id="qty_group">
                                <label class="form-label small fw-bold text-primary">Stok Saat Ini</label>
                                <input type="number" name="stok_saat_ini" class="form-control border-primary bg-primary-subtle" value="0">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label small fw-bold text-danger">Stok Minimum</label>
                                <input type="number" name="stok_minimum" class="form-control border-danger-subtle" value="0">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label small fw-bold">Harga Beli</label>
                                <input type="number" name="harga_beli" class="form-control" value="0">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label small fw-bold">Harga Jual</label>
                                <input type="number" name="harga_jual" class="form-control" value="0">
                            </div>
                        </div>

                        <!-- BOM TOGGLE -->
                        <div class="form-check form-switch p-3 bg-light border rounded mb-3 mt-2">
                            <input class="form-check-input ms-0 me-2" type="checkbox" id="is_bom">
                            <label class="form-check-label fw-bold text-primary" for="is_bom"><i class="fas fa-layer-group me-1"></i> Aktifkan Mode Perakit (BOM)</label>
                            <div class="small text-muted ms-4">Gunakan ini jika item dibuat dari bahan-bahan lain. Stok manual akan dinonaktifkan.</div>
                        </div>

                        <!-- (Section BOM di dalam form) -->
                        <div id="bom_section" style="display: none;" class="bg-primary-subtle p-3 rounded mb-4 border border-primary-subtle">
                            <h6 class="fw-bold mb-3"><i class="fas fa-list me-2"></i> Komponen Material (Resep)</h6>
                            <div id="bom_rows">
                                <!-- Row material bakal muncul di sini -->
                            </div>
                            <button type="button" class="btn btn-sm btn-primary mt-2" id="add_material_btn"><i class="fas fa-plus me-1"></i> Tambah Bahan Baku</button>
                            <input type="hidden" name="materials_data" id="materials_data">
                        </div>

                        <!-- VARIAN TOGGLE -->
                        <div class="form-check form-switch p-3 bg-light border rounded mb-3">
                            <input class="form-check-input ms-0 me-2" type="checkbox" id="has_variants">
                            <label class="form-check-label fw-bold text-success" for="has_variants"><i class="fas fa-tags me-1"></i> Item memiliki Varian</label>
                            <div class="small text-muted ms-4">Contoh: Ukuran (S, M, L) & Warna (Merah, Biru). Sistem akan membuat banyak item sekaligus.</div>
                        </div>

                        <div id="variant_section" style="display: none;" class="bg-success-subtle p-3 rounded mb-4 border border-success-subtle">
                            <h6 class="fw-bold mb-3">Dimensi Varian</h6>
                            <div id="variant_rows"></div>
                            <button type="button" class="btn btn-sm btn-success mt-2" id="add_variant_btn"><i class="fas fa-plus me-1"></i> Tambah Dimensi</button>
                            <input type="hidden" name="variant_dimensions" id="variant_dimensions">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Tags</label>
                                <input type="text" name="tags_input" class="form-control" placeholder="Pisahkan dengan koma (contoh: promo, fast-moving)">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small">Catatan Internal</label>
                                <textarea name="note" class="form-control" rows="1" placeholder="Info tambahan..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- (Script Section di paling bawah) -->
<script>
    $(document).ready(function() {
        // --- 1. Toggle Item vs Folder ---
        $('input[name="type"]').on('change', function() {
            $('#item_fields').toggle(this.value === 'item');
        });

        // --- 2. BOM Logic ---
        $('#is_bom').on('change', function() {
            $('#bom_section').toggle(this.checked);
            $('#qty_group').toggle(!this.checked);
            if(this.checked) $('input[name="stok_saat_ini"]').val(0);
        });

        // --- 3. [BARU] Custom Search Logic (Gantiin Select2) ---
        $('#add_material_btn').on('click', function() {
            const rowId = 'mat-' + Date.now();
            const html = `
                <div class="row g-2 mb-2 material-row" id="${rowId}">
                    <div class="col-7 search-results-wrapper">
                        <input type="text" class="form-control mat-search-input" placeholder="Ketik nama bahan baku..." autocomplete="off">
                        <input type="hidden" class="mat-id">
                        <div class="search-results-list list-group shadow-sm"></div>
                    </div>
                    <div class="col-3">
                        <input type="number" step="0.01" class="form-control mat-qty" placeholder="Qty" value="1">
                    </div>
                    <div class="col-2">
                        <button type="button" class="btn btn-danger w-100" onclick="$('#${rowId}').remove()"><i class="fas fa-times"></i></button>
                    </div>
                </div>`;
            $('#bom_rows').append(html);
        });

        // Event delegation buat search input
        $(document).on('keyup', '.mat-search-input', function() {
            let query = $(this).val();
            let wrapper = $(this).closest('.search-results-wrapper');
            let resultList = wrapper.find('.search-results-list');

            if (query.length < 2) {
                resultList.hide();
                return;
            }

            $.ajax({
                url: "{{ route('item.search.ajax') }}",
                data: { q: query },
                success: function(data) {
                    resultList.empty();
                    if (data.length > 0) {
                        data.forEach(item => {
                            resultList.append(`<div class="list-group-item" data-id="${item.id}" data-name="${item.text}">${item.text}</div>`);
                        });
                        resultList.show();
                    } else {
                        resultList.append(`<div class="list-group-item disabled">Tidak ditemukan</div>`);
                        resultList.show();
                    }
                }
            });
        });

        // Pilih item dari hasil pencarian
        $(document).on('click', '.list-group-item[data-id]', function() {
            let id = $(this).data('id');
            let name = $(this).data('name');
            let wrapper = $(this).closest('.search-results-wrapper');

            wrapper.find('.mat-search-input').val(name);
            wrapper.find('.mat-id').val(id);
            wrapper.find('.search-results-list').hide();
        });

        // Klik di luar buat nutup dropdown
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-results-wrapper').length) {
                $('.search-results-list').hide();
            }
        });

        // --- 3. Varian Logic ---
        $('#has_variants').on('change', function() {
            $('#variant_section').toggle(this.checked);
        });

        $('#add_variant_btn').on('click', function() {
            const html = `
                <div class="row g-2 mb-2 variant-row">
                    <div class="col-5"><input type="text" class="form-control var-name" placeholder="Nama (e.g. Ukuran)"></div>
                    <div class="col-5"><input type="text" class="form-control var-options" placeholder="Opsi (e.g. S, M, L)"></div>
                    <div class="col-2"><button type="button" class="btn btn-danger w-100" onclick="$(this).parent().parent().remove()"><i class="fas fa-times"></i></button></div>
                </div>`;
            $('#variant_rows').append(html);
        });

        // --- 4. Submit Pre-Processing ---
        $('#mainForm').on('submit', function() {
            const mats = [];
            if($('#is_bom').is(':checked')) {
                $('.material-row').each(function() {
                    const id = $(this).find('.mat-id').val();
                    const qty = $(this).find('.mat-qty').val();
                    if(id && qty) mats.push({ item_id: id, qty: qty });
                });
                $('#materials_data').val(JSON.stringify(mats));
            } else { $('#materials_data').val(''); }

            // (Variant Data tetap seperti kode lama loe)
            if($('#has_variants').is(':checked')) {
                const vars = [];
                $('.variant-row').each(function() {
                    const n = $(this).find('.var-name').val();
                    const o = $(this).find('.var-options').val();
                    if(n && o) vars.push({ name: n, options: o });
                });
                $('#variant_dimensions').val(JSON.stringify(vars));
            } else { $('#variant_dimensions').val(''); }
        });
    });
</script>
@endsection
