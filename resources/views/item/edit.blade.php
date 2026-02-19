@extends('layouts.app')
@section('title', 'Edit: ' . $item->nama)

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* Fix Select2 agar menyatu dengan Bootstrap 5 */
    .select2-container--bootstrap-5 .select2-selection {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        min-height: 2.5rem;
        display: flex;
        align-items: center;
    }

    /* Fix warna teks hitam di dropdown agar tidak silau/hilang */
    .select2-results__option { color: #333 !important; padding: 8px 12px !important; }
    .select2-results__option--highlighted { background-color: #895129 !important; color: #fff !important; }

    /* Fix overlap & z-index agar tidak tertutup tombol atau input lain */
    .select2-container { z-index: 1060 !important; }
    .select2-dropdown { border: 1px solid #dee2e6 !important; box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important; }

    .bg-primary-subtle { background-color: #e7f1ff !important; }
    .border-primary-subtle { border-color: #cfe2ff !important; }
    .material-row { transition: all 0.2s ease; border-radius: 8px; }
    .material-row:hover { background-color: rgba(0,0,0,0.02); }
</style>
@endsection

@section('content')
<div class="row justify-content-center pb-5">
    <div class="col-lg-10">
        <form action="{{ route('item.update', $item->id) }}" method="POST" id="mainForm">
            @csrf @method('PUT')

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold m-0 text-warning">
                    <i class="fas {{ $item->is_bom ? 'fa-layer-group' : 'fa-box' }} me-2"></i>
                    Edit {{ $item->is_bom ? 'Produk BOM' : 'Item Inventaris' }}
                </h2>
                <div class="d-flex gap-2">
                    <a href="{{ route('item.show', $item->id) }}" class="btn btn-light border fw-bold px-4">Batal</a>
                    <button type="submit" class="btn btn-warning btn-lg px-5 shadow fw-bold" id="btnSubmit">Update Data</button>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4 p-4">
                <!-- INFORMASI UTAMA -->
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Nama Item</label>
                        <input type="text" name="nama" class="form-control form-control-lg fw-bold" value="{{ $item->nama }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Lokasi Folder</label>
                        <select name="folder_id" class="form-select form-select-lg">
                            <option value="">(Root)</option>
                            @foreach($allFolders as $f)
                                <option value="{{ $f->id }}" {{ $item->folder_id == $f->id ? 'selected' : '' }}>{{ $f->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- SPEK & STOK -->
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label small fw-bold">Satuan</label>
                        <input type="text" name="satuan" class="form-control" value="{{ $item->satuan }}" placeholder="pcs, kg, dll">
                    </div>

                    @if(!$item->is_bom)
                    <!-- Hanya tampil untuk item biasa, BOM stoknya otomatis kalkulasi -->
                    <div class="col-md-3 mb-3" id="qty_group">
                        <label class="form-label small fw-bold text-primary">Stok Saat Ini</label>
                        <input type="number" name="stok_saat_ini" class="form-control border-primary bg-primary-subtle fw-bold" value="{{ $item->stok_saat_ini }}">
                    </div>
                    @endif

                    <div class="col-md-{{ $item->is_bom ? '3' : '2' }} mb-3">
                        <label class="form-label small fw-bold text-danger">Min Stok</label>
                        <input type="number" name="stok_minimum" class="form-control border-danger-subtle" value="{{ $item->stok_minimum }}">
                    </div>
                    <div class="col-md-{{ $item->is_bom ? '3' : '2' }} mb-3">
                        <label class="form-label small fw-bold text-muted">Hrg Beli</label>
                        <input type="number" name="harga_beli" class="form-control" value="{{ $item->harga_beli }}">
                    </div>
                    <div class="col-md-{{ $item->is_bom ? '3' : '2' }} mb-3">
                        <label class="form-label small fw-bold text-muted">Hrg Jual</label>
                        <input type="number" name="harga_jual" class="form-control" value="{{ $item->harga_jual }}">
                    </div>
                </div>

                <!-- LOGIKA BOM (BILL OF MATERIALS) -->
                @if($item->is_bom)
                    <div class="alert alert-primary mb-3 shadow-sm border-0 d-flex align-items-center">
                        <i class="fas fa-info-circle fa-2x me-3"></i>
                        <div>
                            <strong>Mode BOM Aktif.</strong> Anda dapat menambah atau mengurangi material bahan baku.
                            <br><small>Hapus semua baris material jika ingin mengubah item ini menjadi <b>Item Biasa</b> secara permanen.</small>
                        </div>
                    </div>

                    <div id="bom_section" class="bg-primary-subtle p-4 rounded mb-4 border border-primary-subtle shadow-inner">
                        <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-list-ul me-2"></i>Komponen Material (Resep BOM)</h6>

                        <div id="bom_rows">
                            @if(is_array($item->materials))
                                @foreach($item->materials as $mat)
                                <div class="row g-2 mb-2 material-row align-items-center">
                                    <div class="col-7">
                                        <select class="form-select mat-id shadow-sm" required>
                                            <option value="{{ $mat['item_id'] }}" selected>{{ $materialNames[$mat['item_id']] ?? 'Item #'.$mat['item_id'] }}</option>
                                        </select>
                                    </div>
                                    <div class="col-3">
                                        <div class="input-group shadow-sm">
                                            <input type="number" step="0.01" class="form-control mat-qty" value="{{ $mat['qty'] }}" required>
                                            <span class="input-group-text bg-white small">qty</span>
                                        </div>
                                    </div>
                                    <div class="col-2 text-end">
                                        <button type="button" class="btn btn-outline-danger w-100 border-0" onclick="this.parentElement.parentElement.remove()">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                                @endforeach
                            @endif
                        </div>

                        <button type="button" class="btn btn-primary mt-2 shadow-sm fw-bold px-3" id="add_material_btn">
                            <i class="fas fa-plus me-1"></i> Tambah Bahan Baku
                        </button>
                        <!-- Data dikirim sebagai JSON string melalui input hidden ini -->
                        <input type="hidden" name="materials_data" id="materials_data">
                    </div>
                @else
                    <div class="alert alert-secondary mb-3 border-0">
                        <i class="fas fa-lock me-2"></i> Item ini adalah <strong>Item Biasa</strong>.
                        <small class="d-block">Sesuai aturan, Item Biasa tidak dapat diubah menjadi BOM melalui menu Edit untuk menjaga integritas data.</small>
                    </div>
                @endif

                <!-- TAGS & NOTE -->
                <div class="row mt-2">
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Tags (Pisahkan dengan koma)</label>
                        <input type="text" name="tags_input" class="form-control" placeholder="contoh: roti, promo, bestseller" value="{{ is_array($item->tags) ? implode(', ', $item->tags) : '' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted text-uppercase">Catatan Internal</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Tambahkan instruksi khusus di sini...">{{ $item->note }}</textarea>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        // --- 1. Fungsi Inisialisasi Select2 AJAX ---
        function initMaterialSelect(el) {
            $(el).select2({
                theme: 'bootstrap-5',
                placeholder: 'Cari bahan baku...',
                minimumInputLength: 0,
                allowClear: true,
                width: '100%',
                ajax: {
                    url: "{{ route('item.search.ajax') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { q: params.term || '' };
                    },
                    processResults: function(data) {
                        return { results: data };
                    },
                    cache: true
                },
                // [FIX] Menentukan parent dropdown agar tidak overlap atau posisi ngaco
                dropdownParent: $(el).closest('.card')
            });
        }

        // Jalankan Select2 untuk baris yang sudah ada saat halaman dimuat
        $('.mat-id').each(function() {
            initMaterialSelect(this);
        });

        // --- 2. Event Klik Tambah Bahan Baku ---
        $('#add_material_btn').on('click', function() {
            const rowId = 'mat-' + Date.now();
            const html = `
                <div class="row g-2 mb-2 material-row align-items-center" id="${rowId}">
                    <div class="col-7">
                        <select class="form-select mat-id shadow-sm" required></select>
                    </div>
                    <div class="col-3">
                        <div class="input-group shadow-sm">
                            <input type="number" step="0.01" class="form-control mat-qty" placeholder="0.00" value="1" required>
                            <span class="input-group-text bg-white small">qty</span>
                        </div>
                    </div>
                    <div class="col-2">
                        <button type="button" class="btn btn-outline-danger w-100 border-0" onclick="$('#${rowId}').remove()">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>`;

            $('#bom_rows').append(html);
            initMaterialSelect($(`#${rowId} .mat-id`));

            // Langsung buka dropdown setelah baris baru dibuat (UX Speed)
            $(`#${rowId} .mat-id`).select2('open');
        });

        // --- 3. Pre-Processing sebelum Form dikirim ---
        $('#mainForm').on('submit', function(e) {
            // Kita hanya memproses data material jika item tersebut memang BOM
            @if($item->is_bom)
                const mats = [];
                let isValid = true;

                $('.material-row').each(function() {
                    const id = $(this).find('.mat-id').val();
                    const qty = $(this).find('.mat-qty').val();

                    if(id && qty) {
                        mats.push({
                            item_id: parseInt(id),
                            qty: parseFloat(qty)
                        });
                    } else {
                        isValid = false;
                    }
                });

                // Set value ke input hidden agar controller bisa baca JSON-nya
                $('#materials_data').val(JSON.stringify(mats));
            @endif
        });
    });
</script>
@endsection
