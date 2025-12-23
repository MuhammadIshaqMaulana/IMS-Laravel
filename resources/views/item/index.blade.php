@extends('layouts.app')

@section('title', $currentFolder ? $currentFolder->nama : 'Inventaris')

@section('content')
<div class="h-100 d-flex flex-column">
    <!-- HEADER & DYNAMIC BREADCRUMBS -->
    <div class="mb-4 d-flex justify-content-between align-items-end">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item">
                        <a href="{{ route('item.index') }}" class="text-decoration-none text-muted">
                            <i class="fas fa-home me-1"></i> Root
                        </a>
                    </li>
                    @foreach($breadcrumbs as $bc)
                        @if($loop->last)
                            <li class="breadcrumb-item active fw-bold text-dark" aria-current="page">{{ $bc->nama }}</li>
                        @else
                            <li class="breadcrumb-item">
                                <a href="{{ route('item.index', ['folder_id' => $bc->id]) }}" class="text-decoration-none text-muted">{{ $bc->nama }}</a>
                            </li>
                        @endif
                    @endforeach
                </ol>
            </nav>
            <h2 class="fw-bold m-0 text-dark">{{ $currentFolder ? $currentFolder->nama : 'Root Inventory' }}</h2>
        </div>
        <div class="btn-group shadow-sm">
            <a href="{{ route('item.create', ['folder_id' => request('folder_id')]) }}" class="btn btn-primary px-4 fw-bold">
                <i class="fas fa-plus me-2"></i> Create New
            </a>
        </div>
    </div>

    <!-- Bulk Action Toolbar -->
    <div id="bulkActions" class="bg-light p-2 mb-3 rounded shadow-sm border border-warning" style="display: none;">
        <div class="d-flex justify-content-between align-items-center px-2">
            <div>
                <span id="selectedCount" class="fw-bold small badge bg-dark me-2">0 Selected</span>
                <span class="text-muted small">Items only</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-warning fw-bold" onclick="openBulkModal()">
                    <i class="fas fa-edit me-1"></i> Edit Massal
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">Export</button>
                    <ul class="dropdown-menu shadow border-0">
                        <li><a class="dropdown-item" href="{{ route('item.export.csv') }}"><i class="fas fa-file-csv me-2 text-success"></i> CSV</a></li>
                        <li><a class="dropdown-item" href="{{ route('item.export.pdf') }}" target="_blank"><i class="fas fa-file-pdf me-2 text-danger"></i> PDF</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN GRID (Folders & Items) -->
    <div class="row overflow-y-auto flex-grow-1 g-4 pb-5">

        <!-- 1. TAMPILKAN SUB-FOLDERS -->
        @foreach($subFolders as $folder)
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm item-card">
                <a href="{{ route('item.index', ['folder_id' => $folder->id]) }}" class="text-decoration-none text-dark h-100 d-flex flex-column">
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 140px;">
                        <i class="fas fa-folder fa-5x text-warning opacity-75"></i>
                    </div>
                    <div class="card-body p-3 text-center">
                        <h6 class="card-title text-truncate fw-bold mb-0">{{ $folder->nama }}</h6>
                        <div class="mt-1">
                            <span class="badge bg-light text-muted fw-normal border">
                                {{ $folder->children()->count() }} folders, {{ $folder->items()->count() }} items
                            </span>
                        </div>
                    </div>
                </a>
                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark" title="Move Folder" onclick="openMoveModal('folder', {{ $folder->id }}, '{{ $folder->nama }}')">
                        <i class="fas fa-external-link-alt"></i>
                    </button>
                </div>
            </div>
        </div>
        @endforeach

        <!-- 2. TAMPILKAN ITEMS / BOM -->
        @foreach($items as $item)
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm item-card position-relative">
                <!-- Checkbox -->
                <div class="position-absolute top-0 start-0 p-2 z-3">
                    <input class="form-check-input item-checkbox" type="checkbox" data-item-id="{{ $item->id }}">
                </div>

                <a href="{{ route('item.show', $item->id) }}" class="text-decoration-none text-dark h-100 d-flex flex-column">
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center overflow-hidden" style="height: 160px;">
                        @if($item->image_link)
                            <img src="{{ $item->image_link }}" class="w-100 h-100 object-fit-cover">
                        @else
                            <i class="fas {{ $item->is_bom ? 'fa-layer-group' : 'fa-box' }} fa-4x text-secondary opacity-20"></i>
                        @endif
                    </div>
                    <div class="card-body p-3">
                        <h6 class="card-title text-truncate fw-bold mb-1">{{ $item->nama }}</h6>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="badge {{ $item->calculated_stock <= $item->stok_minimum ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }} border">
                                {{ number_format($item->calculated_stock, 0) }} {{ $item->satuan }}
                            </span>
                            <span class="fw-bold small text-muted">Rp{{ number_format($item->harga_jual, 0) }}</span>
                        </div>
                    </div>
                </a>
                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark" title="Move Item" onclick="openMoveModal('item', {{ $item->id }}, '{{ $item->nama }}')">
                        <i class="fas fa-external-link-alt"></i>
                    </button>
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark" title="Adjust Stock" onclick="openQtyModal({{ $item->id }}, '{{ $item->nama }}')">
                        <i class="fas fa-plus-minus"></i>
                    </button>
                </div>
            </div>
        </div>
        @endforeach

        <!-- EMPTY STATE -->
        @if($subFolders->isEmpty() && $items->isEmpty())
        <div class="col-12 text-center py-5 opacity-50">
            <i class="fas fa-box-open fa-5x mb-3 text-muted"></i>
            <h4 class="text-muted">Folder ini masih kosong.</h4>
            <p class="text-muted small">Coba buat folder atau item baru di sini.</p>
        </div>
        @endif
    </div>

    <!-- PAGINATION (Items Only) -->
    <div class="mt-4">
        {{ $items->appends(request()->query())->links() }}
    </div>
</div>

<!-- Modal-modal yang di-include -->
@include('item.modals')

<script>
    /**
     * Logika Modal Pindah Folder/Item (Target Type dipisah di tabel berbeda)
     */
    function openMoveModal(type, id, name) {
        const form = document.getElementById('moveForm');
        document.getElementById('moveItemName').textContent = name;
        form.querySelector('input[name="id"]').value = id;
        form.querySelector('input[name="target_type"]').value = type;

        const modal = new bootstrap.Modal(document.getElementById('moveModal'));
        modal.show();
    }

    /**
     * Logika Modal Update Qty Cepat (Hanya untuk Item)
     */
    function openQtyModal(id, name) {
        const form = document.getElementById('qtyForm');
        form.action = `/item/${id}/update-quantity`;
        document.querySelector('#qtyModal h5').textContent = `Adjust Stock: ${name}`;

        const modal = new bootstrap.Modal(document.getElementById('qtyModal'));
        modal.show();
    }

    /**
     * Logika Modal Bulk Edit (Seleksi massal item)
     */
    function openBulkModal() {
        const checked = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.dataset.itemId);
        const input = document.querySelector('#bulkEditForm input[name="selected_items"]');
        if(input) input.value = JSON.stringify(checked);

        const modal = new bootstrap.Modal(document.getElementById('bulkEditModal'));
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Toggle toolbar aksi massal
        const checkboxes = document.querySelectorAll('.item-checkbox');
        const bulkBar = document.getElementById('bulkActions');
        const countSpan = document.getElementById('selectedCount');

        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                const checked = document.querySelectorAll('.item-checkbox:checked');
                bulkBar.style.display = checked.length > 0 ? 'block' : 'none';
                countSpan.textContent = `${checked.length} Selected`;
            });
        });
    });
</script>

<style>
    .item-card {
        transition: all 0.2s cubic-bezier(.4,0,.2,1);
        cursor: default;
        border: 1px solid #f0f0f0 !important;
    }
    .item-card:hover {
        transform: translateY(-4px);
        border-color: #895129 !important;
        box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1) !important;
    }
    .item-card a { cursor: pointer; }
    .item-checkbox { cursor: pointer; z-index: 10; position: relative; }

    /* Breadcrumb Separator Custom */
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        font-size: 1.4rem;
        vertical-align: middle;
        line-height: 10px;
        color: #ccc;
    }
    .breadcrumb-item a { color: #6c757d; }
    .breadcrumb-item a:hover { color: #4a2c16; }
</style>
@endsection
