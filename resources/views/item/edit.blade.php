@extends('layouts.app')
@section('title', 'Edit: ' . $item->nama)

@section('styles')
<style>
    .search-results-wrapper { position: relative; }
    .search-results-list {
        position: absolute; top: 100%; left: 0; right: 0;
        z-index: 1000; background: white; border: 1px solid #ddd;
        max-height: 200px; overflow-y: auto; display: none;
    }
    .search-results-list .list-group-item { cursor: pointer; border: none; padding: 8px 12px; }
    .search-results-list .list-group-item:hover { background: #f8f9fa; color: #895129; }
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

                    {{-- Stok dimunculkan untuk Non-BOM --}}
                    {{-- Kita beri ID agar JS bisa sembunyikan jika BOM di-edit jadi Item --}}
                    <div class="col-md-3 mb-3" id="qty_group" style="{{ $item->is_bom ? 'display:none;' : '' }}">
                        <label class="form-label small fw-bold text-primary">Stok Saat Ini</label>
                        <input type="number" name="stok_saat_ini" class="form-control border-primary bg-primary-subtle fw-bold" value="{{ $item->stok_saat_ini }}">
                    </div>

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

                <!-- Bagian BOM -->
                @if($item->is_bom)
                    <div class="alert alert-primary mb-3 mt-2">
                        <i class="fas fa-layer-group me-2"></i> <strong>Item ini adalah BOM.</strong>
                        Jika seluruh bahan baku di bawah dihapus, item ini akan berubah menjadi <strong>Item Biasa</strong>.
                    </div>
                    <div id="bom_section" class="bg-primary-subtle p-3 rounded mb-4 border border-primary-subtle">
                        <h6 class="fw-bold mb-3">Komponen Material (Resep)</h6>
                        <div id="bom_rows">
                            @foreach($item->materials as $mat)
                            <div class="row g-2 mb-2 material-row align-items-center">
                                <div class="col-7 search-results-wrapper">
                                    <input type="text" class="form-control mat-search-input" value="{{ $materialNames[$mat['item_id']] ?? 'Item #'.$mat['item_id'] }}" autocomplete="off">
                                    <input type="hidden" class="mat-id" value="{{ $mat['item_id'] }}">
                                    <div class="search-results-list list-group shadow-sm"></div>
                                </div>
                                <div class="col-3">
                                    <input type="number" step="0.01" class="form-control mat-qty" value="{{ $mat['qty'] }}">
                                </div>
                                <div class="col-2">
                                    <button type="button" class="btn btn-danger w-100" onclick="$(this).closest('.material-row').remove(); checkBomStatus();"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-primary mt-2" id="add_material_btn"><i class="fas fa-plus me-1"></i> Tambah Bahan Baku</button>
                    </div>
                @endif

                <input type="hidden" name="materials_data" id="materials_data">

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
<script>
    $(document).ready(function() {
        // Fungsi untuk mengecek jika BOM berubah jadi item (karena material kosong)
        window.checkBomStatus = function() {
            const rows = $('.material-row').length;
            if (rows === 0) {
                $('#qty_group').fadeIn();
            } else {
                $('#qty_group').fadeOut();
            }
        }

        // Search logic sama dengan sebelumnya
        $('#add_material_btn').on('click', function() {
            const rowId = 'mat-' + Date.now();
            const html = `
                <div class="row g-2 mb-2 material-row" id="${rowId}">
                    <div class="col-7 search-results-wrapper">
                        <input type="text" class="form-control mat-search-input" placeholder="Cari..." autocomplete="off">
                        <input type="hidden" class="mat-id">
                        <div class="search-results-list list-group"></div>
                    </div>
                    <div class="col-3"><input type="number" step="0.01" class="form-control mat-qty" value="1"></div>
                    <div class="col-2"><button type="button" class="btn btn-danger w-100" onclick="$(this).closest('.material-row').remove(); checkBomStatus();"><i class="fas fa-times"></i></button></div>
                </div>`;
            $('#bom_rows').append(html);
            checkBomStatus();
        });

        $(document).on('keyup', '.mat-search-input', function() {
            let query = $(this).val();
            let wrapper = $(this).closest('.search-results-wrapper');
            let list = wrapper.find('.search-results-list');
            if (query.length < 2) { list.hide(); return; }
            $.ajax({
                url: "{{ route('item.search.ajax') }}",
                data: { q: query },
                success: function(data) {
                    list.empty();
                    data.forEach(item => {
                        list.append(`<div class="list-group-item" data-id="${item.id}" data-name="${item.text}">${item.text}</div>`);
                    });
                    list.show();
                }
            });
        });

        $(document).on('click', '.list-group-item[data-id]', function() {
            let wrapper = $(this).closest('.search-results-wrapper');
            wrapper.find('.mat-search-input').val($(this).data('name'));
            wrapper.find('.mat-id').val($(this).data('id'));
            $('.search-results-list').hide();
        });

        $('#mainForm').on('submit', function() {
            const mats = [];
            $('.material-row').each(function() {
                const id = $(this).find('.mat-id').val();
                const qty = $(this).find('.mat-qty').val();
                if(id && qty) mats.push({ item_id: id, qty: qty });
            });
            $('#materials_data').val(JSON.stringify(mats));
        });
    });
</script>
@endsection
