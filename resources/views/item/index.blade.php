@extends('layouts.app')

@section('title', $search ? "Hasil Pencarian: $search" : ($currentFolder ? $currentFolder->nama : 'Inventaris'))

@section('content')
<div class="h-100 d-flex flex-column">
    <!-- ATAS: BREADCRUMBS & PENCARIAN -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('item.index') }}" class="text-decoration-none text-muted"><i class="fas fa-home"></i></a></li>
                    @foreach($breadcrumbs as $bc)
                        <li class="breadcrumb-item"><a href="{{ route('item.index', ['folder_id' => $bc->id]) }}" class="text-decoration-none text-muted">{{ $bc->nama }}</a></li>
                    @endforeach
                    @if($search) <li class="breadcrumb-item active">Cari: "{{ $search }}"</li> @endif
                </ol>
            </nav>
            <form action="{{ route('item.index') }}" method="GET" class="d-flex" style="width: 320px;">
                <div class="input-group input-group-sm shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Cari..." value="{{ $search }}">
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold m-0 text-dark">{{ $currentFolder ? $currentFolder->nama : 'Inventaris Utama' }}</h2>
            <div class="d-flex gap-2">
                <div class="dropdown shadow-sm">
                    <button class="btn btn-outline-dark dropdown-toggle fw-bold" type="button" data-bs-toggle="dropdown">Export</button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li><a class="dropdown-item" href="{{ route('item.export.csv') }}"><i class="fas fa-file-csv me-2 text-success"></i> CSV</a></li>
                        <li><a class="dropdown-item" href="{{ route('item.export.pdf') }}" target="_blank"><i class="fas fa-file-pdf me-2 text-danger"></i> PDF</a></li>
                    </ul>
                </div>
                <a href="{{ route('item.create', ['folder_id' => request('folder_id')]) }}" class="btn btn-primary px-4 fw-bold shadow-sm">+ Tambah Baru</a>
            </div>
        </div>
    </div>

    <!-- TOOLBAR MASSAL -->
    <div id="bulkActions" class="bg-dark text-white p-2 mb-3 rounded shadow-lg border border-warning" style="display: none; position: sticky; top: 0; z-index: 1000;">
        <div class="d-flex justify-content-between align-items-center px-2">
            <div class="d-flex align-items-center gap-3">
                <div class="form-check pt-1">
                    <input class="form-check-input" type="checkbox" id="selectAllItems" style="cursor: pointer;">
                    <label class="form-check-label small fw-bold text-warning" for="selectAllItems">Select All</label>
                </div>
                <span id="selectedCount" class="fw-bold small badge bg-warning text-dark">0 Selected</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-info text-white fw-bold shadow-sm" onclick="openBulkQtyModal()"><i class="fas fa-plus-minus me-1"></i> Stok</button>
                <button class="btn btn-sm btn-success fw-bold shadow-sm" onclick="openBulkCloneModal()"><i class="fas fa-copy me-1"></i> Clone</button>
                <button class="btn btn-sm btn-warning fw-bold shadow-sm" onclick="openBulkModal()"><i class="fas fa-edit me-1"></i> Edit Data</button>
            </div>
        </div>
    </div>

    <!-- GRID -->
    <div class="row overflow-y-auto flex-grow-1 g-4 pb-5">
        @foreach($subFolders as $folder)
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm item-card">
                <a href="{{ route('item.index', ['folder_id' => $folder->id]) }}" class="text-decoration-none text-dark h-100 d-flex flex-column">
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 140px;">
                        <i class="fas fa-folder fa-5x text-warning opacity-75"></i>
                    </div>
                    <div class="card-body p-3 text-center">
                        <h6 class="card-title text-truncate fw-bold mb-0">{{ $folder->nama }}</h6>
                        <small class="text-muted small">{{ $folder->items()->count() }} items</small>
                    </div>
                </a>
                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark" onclick="openEditFolderModal({{ $folder->id }}, '{{ $folder->nama }}')"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark" onclick="openMoveModal('folder', {{ $folder->id }}, '{{ $folder->nama }}')"><i class="fas fa-external-link-alt"></i></button>
                </div>
            </div>
        </div>
        @endforeach

        @foreach($items as $item)
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm item-card position-relative">
                <div class="position-absolute top-0 start-0 p-2 z-3"><input class="form-check-input item-checkbox" type="checkbox" data-item-id="{{ $item->id }}"></div>
                <a href="{{ route('item.show', $item->id) }}" class="text-decoration-none text-dark h-100 d-flex flex-column">
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center overflow-hidden" style="height: 140px;">
                        @if($item->image_link) <img src="{{ $item->image_link }}" class="w-100 h-100 object-fit-cover">
                        @else <i class="fas {{ $item->is_bom ? 'fa-layer-group' : 'fa-box' }} fa-4x text-secondary opacity-20"></i> @endif
                    </div>
                    <div class="card-body p-3">
                        <h6 class="card-title text-truncate fw-bold mb-1">{{ $item->nama }}</h6>
                        <div class="mb-2">
                            @if($item->tags) @foreach(array_slice($item->tags, 0, 2) as $tag) <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.6rem;">{{ $tag }}</span> @endforeach @endif
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div>
                                <span class="badge {{ $item->calculated_stock <= $item->stok_minimum ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }} border">{{ number_format($item->calculated_stock, 0) }} {{ $item->satuan }}</span>
                                @if($item->stok_minimum > 0) <div class="text-muted" style="font-size: 0.65rem;">Min: {{ number_format($item->stok_minimum, 0) }}</div> @endif
                            </div>
                            <span class="fw-bold small text-muted">Rp{{ number_format($item->harga_jual, 0) }}</span>
                        </div>
                    </div>
                </a>
                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark" onclick="openMoveModal('item', {{ $item->id }}, '{{ $item->nama }}')"><i class="fas fa-external-link-alt"></i></button>
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark" onclick="openQtyModal({{ $item->id }}, '{{ $item->nama }}')"><i class="fas fa-plus-minus"></i></button>
                </div>
            </div>
        </div>
        @endforeach
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
        const form = document.getElementById('moveForm');
        document.getElementById('moveItemName').textContent = name;
        form.querySelector('input[name="id"]').value = id;
        form.querySelector('input[name="target_type"]').value = type;
        new bootstrap.Modal('#moveModal').show();
    }
    function openQtyModal(id, name) {
        const form = document.getElementById('qtyForm');
        form.action = `/item/${id}/update-quantity`;
        document.querySelector('#qtyModal h5').textContent = `Adjust: ${name}`;
        new bootstrap.Modal('#qtyModal').show();
    }
    function getCheckedIds() { return Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.dataset.itemId); }
    function openBulkModal() {
        document.querySelector('#bulkEditForm input[name="selected_items"]').value = JSON.stringify(getCheckedIds());
        new bootstrap.Modal('#bulkEditModal').show();
    }
    function openBulkQtyModal() {
        document.querySelector('#bulkQtyForm input[name="selected_items"]').value = JSON.stringify(getCheckedIds());
        new bootstrap.Modal('#bulkQtyModal').show();
    }
    function openBulkCloneModal() {
        const ids = getCheckedIds();
        document.querySelector('#bulkCloneForm input[name="selected_items"]').value = JSON.stringify(ids);
        document.getElementById('cloneCountLabel').textContent = ids.length;
        new bootstrap.Modal('#bulkCloneModal').show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const checks = document.querySelectorAll('.item-checkbox');
        const bulkBar = document.getElementById('bulkActions');
        const selectAll = document.getElementById('selectAllItems');
        function updateUI() {
            const count = document.querySelectorAll('.item-checkbox:checked').length;
            bulkBar.style.display = count > 0 ? 'block' : 'none';
            document.getElementById('selectedCount').textContent = `${count} Selected`;
            selectAll.checked = count === checks.length && checks.length > 0;
        }
        checks.forEach(c => c.addEventListener('change', updateUI));
        selectAll.addEventListener('change', function() {
            checks.forEach(c => c.checked = this.checked);
            updateUI();
        });
    });
</script>
<style>
    .item-card { transition: all 0.2s; border: 1px solid #f0f0f0 !important; }
    .item-card:hover { transform: translateY(-4px); border-color: #895129 !important; box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1) !important; }
    .breadcrumb-item + .breadcrumb-item::before { content: "›"; font-size: 1.4rem; vertical-align: middle; line-height: 10px; }
</style>
@endsection
