@extends('layouts.app')

@section('title', $search ? "Hasil Pencarian: $search" : ($currentFolder ? $currentFolder->nama : 'Inventaris'))

@section('content')
<div class="h-100 d-flex flex-column">
    <!-- ATAS: BREADCRUMBS & PENCARIAN GLOBAL -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('item.index') }}" class="text-decoration-none text-muted">
                            <i class="fas fa-home me-1"></i> Root
                        </a>
                    </li>
                    @foreach($breadcrumbs as $bc)
                        <li class="breadcrumb-item">
                            <a href="{{ route('item.index', ['folder_id' => $bc->id]) }}" class="text-decoration-none text-muted">{{ $bc->nama }}</a>
                        </li>
                    @endforeach
                    @if($search)
                        <li class="breadcrumb-item active fw-bold text-primary">Cari: "{{ $search }}"</li>
                    @endif
                </ol>
            </nav>

            <!-- FORM PENCARIAN GLOBAL -->
            <form action="{{ route('item.index') }}" method="GET" class="d-flex" style="width: 320px;">
                <div class="input-group input-group-sm shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 shadow-none" placeholder="Cari nama, SKU, atau tag..." value="{{ $search }}">
                    @if($search)
                        <a href="{{ route('item.index') }}" class="btn btn-outline-secondary border-start-0 text-muted"><i class="fas fa-times"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold m-0 text-dark">
                <i class="fas {{ $currentFolder ? 'fa-folder-open text-warning' : 'fa-boxes text-brown' }} me-2"></i>
                {{ $currentFolder ? $currentFolder->nama : 'Inventaris Utama' }}
            </h2>
            <div class="d-flex gap-2">
                <!-- FITUR IMPORT -->
                <button class="btn btn-outline-success fw-bold shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-file-import me-1"></i> Import
                </button>

                <!-- DROPDOWN EXPORT -->
                <div class="dropdown shadow-sm">
                    <button class="btn btn-outline-dark dropdown-toggle fw-bold" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-file-export me-2"></i> Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                        <li><a class="dropdown-item" href="{{ route('item.export.csv') }}"><i class="fas fa-file-csv me-2 text-success"></i> Simpan ke CSV</a></li>
                        <li><a class="dropdown-item" href="{{ route('item.export.pdf') }}" target="_blank"><i class="fas fa-file-pdf me-2 text-danger"></i> Cetak PDF</a></li>
                    </ul>
                </div>

                <a href="{{ route('item.create', ['folder_id' => request('folder_id')]) }}" class="btn btn-primary px-4 fw-bold shadow-sm">
                    <i class="fas fa-plus me-2"></i> Tambah Baru
                </a>
            </div>
        </div>
    </div>

    <!-- TOOLBAR AKSI MASSAL (MUNCUL OTOMATIS) -->
    <div id="bulkActions" class="bg-dark text-white p-2 mb-3 rounded shadow-lg border border-warning" style="display: none; position: sticky; top: 0; z-index: 1000;">
        <div class="d-flex justify-content-between align-items-center px-2">
            <div class="d-flex align-items-center gap-3">
                <div class="form-check pt-1">
                    <input class="form-check-input shadow-none" type="checkbox" id="selectAllItems" style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
                    <label class="form-check-label small fw-bold text-warning" for="selectAllItems" style="cursor: pointer;">Pilih Semua</label>
                </div>
                <span id="selectedCount" class="fw-bold small badge bg-warning text-dark px-2 py-1">0 Selected</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-info text-white fw-bold shadow-sm px-3" onclick="openBulkQtyModal()" title="Sesuaikan Stok Massal">
                    <i class="fas fa-plus-minus me-1"></i> Stok
                </button>
                <button class="btn btn-sm btn-success fw-bold shadow-sm px-3" onclick="openBulkCloneModal()" title="Duplikasi Data Terpilih">
                    <i class="fas fa-copy me-1"></i> Clone
                </button>
                <button class="btn btn-sm btn-warning fw-bold shadow-sm px-3" onclick="openBulkModal()" title="Edit Field Massal">
                    <i class="fas fa-edit me-1"></i> Edit Data
                </button>
            </div>
        </div>
    </div>

    <!-- GRID UTAMA (FOLDERS & ITEMS) -->
    <div class="row overflow-y-auto flex-grow-1 g-4 pb-5" id="inventoryGrid">

        <!-- 1. FOLDER CARD DENGAN DUAL COUNTER -->
        @foreach($subFolders as $folder)
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm item-card">
                <a href="{{ route('item.index', ['folder_id' => $folder->id]) }}" class="text-decoration-none text-dark h-100 d-flex flex-column">
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 140px;">
                        <i class="fas fa-folder fa-5x text-warning opacity-75"></i>
                    </div>
                    <div class="card-body p-3 text-center">
                        <h6 class="card-title text-truncate fw-bold mb-1">{{ $folder->nama }}</h6>
                        <div class="d-flex justify-content-center gap-2">
                            <span class="badge bg-light text-muted border fw-normal" title="Sub-folder">
                                <i class="fas fa-folder-open me-1 text-warning"></i> {{ $folder->children_count }}
                            </span>
                            <span class="badge bg-light text-muted border fw-normal" title="Item Fisik">
                                <i class="fas fa-box me-1 text-secondary"></i> {{ $folder->items_count }}
                            </span>
                        </div>
                    </div>
                </a>
                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark shadow-sm" onclick="openEditFolderModal({{ $folder->id }}, '{{ $folder->nama }}')" title="Ubah Nama Folder">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark shadow-sm" onclick="openMoveModal('folder', {{ $folder->id }}, '{{ $folder->nama }}')" title="Pindahkan Folder">
                        <i class="fas fa-external-link-alt"></i>
                    </button>
                    <!-- TOMBOL DELETE BARU -->
                    <button class="btn btn-outline-danger btn-sm flex-grow-1 border shadow-sm" onclick="openDeleteFolderModal({{ $folder->id }}, '{{ $folder->nama }}')" title="Hapus Folder & Isi">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        @endforeach

        <!-- 2. ITEM CARD -->
        @foreach($items as $item)
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm item-card position-relative">
                <!-- Checkbox untuk Bulk Action -->
                <div class="position-absolute top-0 start-0 p-2 z-3">
                    <input class="form-check-input item-checkbox shadow-none" type="checkbox" data-item-id="{{ $item->id }}" style="width: 1.2rem; height: 1.2rem; cursor: pointer;">
                </div>

                <a href="{{ route('item.show', $item->id) }}" class="text-decoration-none text-dark h-100 d-flex flex-column">
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center overflow-hidden" style="height: 140px;">
                        @if($item->image_link)
                            <img src="{{ $item->image_link }}" class="w-100 h-100 object-fit-cover" onerror="this.src='https://placehold.co/400x300?text=No+Image'">
                        @else
                            <i class="fas {{ $item->is_bom ? 'fa-layer-group text-primary' : 'fa-box text-secondary' }} fa-4x opacity-20"></i>
                        @endif
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="card-title text-truncate fw-bold mb-0 flex-grow-1 text-dark">{{ $item->nama }}</h6>
                            @if($item->is_bom)
                                <span class="badge bg-primary text-white" style="font-size: 0.55rem; letter-spacing: 1px;">BOM</span>
                            @endif
                        </div>

                        <!-- Tags & Note Indicator -->
                        <div class="mb-2 d-flex flex-wrap gap-1 align-items-center">
                            @if(is_array($item->tags))
                                @foreach(array_slice($item->tags, 0, 2) as $tag)
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small" style="font-size: 0.6rem;">#{{ $tag }}</span>
                                @endforeach
                            @endif
                            @if($item->note)
                                <i class="fas fa-sticky-note text-warning ms-auto small shadow-sm" title="{{ $item->note }}" data-bs-toggle="tooltip"></i>
                            @endif
                        </div>

                        <!-- BOM MATERIALS PREVIEW (NAMA BAHAN) -->
                        @if($item->is_bom && is_array($item->materials))
                            <div class="bg-light rounded p-2 mb-2 border border-light-subtle">
                                <small class="text-muted d-block fw-bold mb-1" style="font-size: 0.6rem; text-transform: uppercase;">Komponen Material:</small>
                                <div class="d-flex flex-column gap-1">
                                    @foreach(array_slice($item->materials, 0, 3) as $m)
                                        <div class="d-flex justify-content-between small text-truncate" style="font-size: 0.65rem; color: #555;">
                                            <span>• {{ $materialMap[$m['item_id']] ?? 'Item #'.$m['item_id'] }}</span>
                                            <span class="fw-bold text-muted">x{{ $m['qty'] }}</span>
                                        </div>
                                    @endforeach
                                    @if(count($item->materials) > 3)
                                        <small class="text-primary italic" style="font-size: 0.6rem;">+{{ count($item->materials) - 3 }} bahan lainnya...</small>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Stok & Info Harga Ganda -->
                        <div class="d-flex justify-content-between align-items-end mt-2">
                            <div>
                                <span class="badge {{ $item->calculated_stock <= $item->stok_minimum ? 'bg-danger' : 'bg-success-subtle text-success' }} border shadow-sm">
                                    {{ number_format($item->calculated_stock, 0) }} {{ $item->satuan }}
                                </span>
                                @if($item->stok_minimum > 0)
                                    <div class="text-muted fw-bold" style="font-size: 0.65rem; margin-top: 3px;">Min: {{ number_format($item->stok_minimum, 0) }}</div>
                                @endif
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block" style="font-size: 0.55rem; line-height: 1;">Beli: Rp{{ number_format($item->harga_beli, 0) }}</small>
                                <span class="fw-bold text-dark" style="font-size: 0.85rem;">Jual: Rp{{ number_format($item->harga_jual, 0) }}</span>
                            </div>
                        </div>
                    </div>
                </a>
                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark shadow-sm" onclick="openMoveModal('item', {{ $item->id }}, '{{ $item->nama }}')" title="Pindahkan Item"><i class="fas fa-external-link-alt"></i></button>
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark shadow-sm" onclick="openQtyModal({{ $item->id }}, '{{ $item->nama }}')" title="Update Stok Cepat"><i class="fas fa-plus-minus"></i></button>
                </div>
            </div>
        </div>
        @endforeach

        @if($subFolders->isEmpty() && $items->isEmpty())
        <div class="col-12 text-center py-5 opacity-50">
            <i class="fas fa-box-open fa-5x mb-3 text-muted"></i>
            <h4 class="text-muted">Tidak ada data ditemukan.</h4>
        </div>
        @endif
    </div>

    <!-- PAGINASI (FIX OVERLAP) -->
    <div class="mt-4 pagination-wrapper">
        {{ $items->appends(request()->query())->links() }}
    </div>
</div>

<!-- Modal Import CSV -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('item.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="folder_id" value="{{ request('folder_id') }}">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white"><h5 class="fw-bold m-0">Bulk Import CSV</h5></div>
                <div class="modal-body p-4">
                    <div class="alert alert-info small">Format CSV: <strong>nomor, nama, satuan, stok_saat_ini, stok_minimum, harga_jual, harga_beli, note, materials, tags</strong>.</div>
                    <label class="form-label fw-bold">Pilih File CSV:</label>
                    <input type="file" name="file_csv" class="form-control" accept=".csv" required>
                    <small class="text-muted mt-2 d-block">Gunakan kolom 'materials' untuk nomor urut (nomor) item lain di file ini.</small>
                </div>
                <div class="modal-footer border-0"><button type="submit" class="btn btn-success fw-bold w-100 shadow-sm">Proses Import Sekarang</button></div>
            </div>
        </form>
    </div>
</div>

@include('item.modals')

<script>
    function openEditFolderModal(id, name) {
        const form = document.getElementById('editFolderForm');
        form.action = `/folder/${id}/update`;
        document.getElementById('editFolderNameInput').value = name;
        new bootstrap.Modal(document.getElementById('editFolderModal')).show();
    }
    function openMoveModal(type, id, name) {
        const modalEl = document.getElementById('moveModal');
        const form = document.getElementById('moveForm');
        document.getElementById('moveItemName').textContent = name;
        form.querySelector('input[name="id"]').value = id;
        form.querySelector('input[name="target_type"]').value = type;
        new bootstrap.Modal(modalEl).show();
    }
    function openQtyModal(id, name) {
        const form = document.getElementById('qtyForm');
        form.action = `/item/${id}/update-quantity`;
        document.querySelector('#qtyModal h5').textContent = `Update: ${name}`;
        new bootstrap.Modal(document.getElementById('qtyModal')).show();
    }
    function getCheckedIds() { return Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.dataset.itemId); }
    function openBulkModal() {
        document.querySelector('#bulkEditForm input[name="selected_items"]').value = JSON.stringify(getCheckedIds());
        new bootstrap.Modal(document.getElementById('bulkEditModal')).show();
    }
    function openBulkQtyModal() {
        document.querySelector('#bulkQtyForm input[name="selected_items"]').value = JSON.stringify(getCheckedIds());
        new bootstrap.Modal(document.getElementById('bulkQtyModal')).show();
    }
    function openBulkCloneModal() {
        const ids = getCheckedIds();
        document.querySelector('#bulkCloneForm input[name="selected_items"]').value = JSON.stringify(ids);
        document.getElementById('cloneCountLabel').textContent = ids.length;
        new bootstrap.Modal(document.getElementById('bulkCloneModal')).show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const checks = document.querySelectorAll('.item-checkbox');
        const bulkBar = document.getElementById('bulkActions');
        const countSpan = document.getElementById('selectedCount');
        const selectAll = document.getElementById('selectAllItems');

        function updateUI() {
            const count = document.querySelectorAll('.item-checkbox:checked').length;
            bulkBar.style.display = count > 0 ? 'block' : 'none';
            countSpan.textContent = `${count} Selected`;
            selectAll.checked = count === checks.length && checks.length > 0;
        }

        checks.forEach(c => c.addEventListener('change', updateUI));

        selectAll.addEventListener('change', function() {
            checks.forEach(c => c.checked = this.checked);
            updateUI();
        });

        // Initialize Tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });
    });
    function openDeleteFolderModal(id, name) {
        const modalEl = document.getElementById('deleteFolderModal');
        const form = document.getElementById('deleteFolderForm');
        document.getElementById('deleteFolderNameText').textContent = name;
        form.action = `/folder/${id}/delete`; // Pastikan rute ini sesuai di web.php
        new bootstrap.Modal(modalEl).show();
    }
</script>

<style>
    .item-card { transition: all 0.25s cubic-bezier(.4,0,.2,1); border: 1px solid #f0f0f0 !important; }
    .item-card:hover { transform: translateY(-5px); border-color: #895129 !important; box-shadow: 0 15px 25px -5px rgba(0,0,0,0.1) !important; }
    .breadcrumb-item + .breadcrumb-item::before { content: "›"; font-size: 1.5rem; vertical-align: middle; line-height: 10px; color: #ddd; }
    .pagination-wrapper nav { display: flex; justify-content: center; }
    .pagination-wrapper svg { width: 20px; height: 20px; vertical-align: middle; }
    .pagination-wrapper nav > div:first-child { display: none; }
</style>
@endsection
