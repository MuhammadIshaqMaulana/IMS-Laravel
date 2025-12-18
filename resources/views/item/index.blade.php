@extends('layouts.app')

@section('title', $currentFolder ? $currentFolder->nama : 'Inventaris Utama')

@section('content')
<div class="h-100 d-flex flex-column">
    <!-- Breadcrumbs & Header -->
    <div class="mb-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('item.index') }}" class="text-decoration-none">Root</a></li>
                @if($currentFolder)
                    <li class="breadcrumb-item active">{{ $currentFolder->nama }}</li>
                @endif
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold m-0">{{ $currentFolder ? $currentFolder->nama : 'Inventaris' }}</h2>
            <div class="btn-group shadow-sm">
                <a href="{{ route('item.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Create</a>
            </div>
        </div>
    </div>

    <!-- Bulk Action Toolbar -->
    <div id="bulkActions" class="bg-white p-2 mb-3 rounded shadow-sm border-start border-4 border-warning" style="display: none;">
        <div class="d-flex justify-content-between align-items-center">
            <div class="ps-2">
                <span id="selectedCount" class="badge bg-dark">0 Selected</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#bulkEditModal">Bulk Edit</button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">Export</button>
                    <ul class="dropdown-menu shadow">
                        <li><a class="dropdown-item" href="{{ route('item.export.csv') }}">Export CSV</a></li>
                        <li><a class="dropdown-item" href="{{ route('item.export.pdf') }}" target="_blank">Print PDF</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Grid Layout Scrollable -->
    <div class="row overflow-y-auto flex-grow-1 g-3">
        @forelse($items as $item)
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm item-card position-relative" data-item-id="{{ $item->id }}">
                <!-- Checkbox Overlay -->
                <div class="position-absolute top-0 start-0 p-2 z-3">
                    <input class="form-check-input item-checkbox shadow-none" type="checkbox" data-item-id="{{ $item->id }}">
                </div>

                <!-- Clickable Content -->
                <a href="{{ $item->is_folder ? route('item.index', ['folder_id' => $item->id]) : route('item.show', $item->id) }}"
                   class="text-decoration-none text-dark h-100 d-flex flex-column">

                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center overflow-hidden" style="height: 140px;">
                        @if($item->is_folder)
                            <i class="fas fa-folder fa-4x text-warning opacity-75"></i>
                        @else
                            @if($item->image_link)
                                <img src="{{ $item->image_link }}" class="w-100 h-100 object-fit-cover">
                            @else
                                <i class="fas {{ $item->is_bom ? 'fa-layer-group' : 'fa-box' }} fa-3x text-secondary opacity-25"></i>
                            @endif
                        @endif
                    </div>

                    <div class="card-body p-3">
                        <h6 class="card-title text-truncate fw-bold mb-1">{{ $item->nama }}</h6>
                        @if($item->is_folder)
                            <small class="text-muted">{{ $item->itemsInFolder()->count() }} items inside</small>
                        @else
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    {{ number_format($item->calculated_stock, 0) }} {{ $item->satuan }}
                                </span>
                                <span class="fw-bold small">Rp{{ number_format($item->harga_jual, 0) }}</span>
                            </div>
                        @endif
                    </div>
                </a>

                <!-- Footer Actions -->
                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm flex-grow-1 text-dark border" onclick="openMoveModal({{ $item->id }}, '{{ $item->nama }}')">
                        <i class="fas fa-folder-open"></i>
                    </button>
                    @if(!$item->is_folder)
                    <button class="btn btn-outline-light btn-sm flex-grow-1 text-dark border" onclick="openQtyModal({{ $item->id }})">
                        <i class="fas fa-plus-minus"></i>
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5">
            <div class="mb-3 opacity-25"><i class="fas fa-folder-open fa-5x"></i></div>
            <h4 class="text-muted">Wah, foldernya kosong!</h4>
            <p class="text-muted">Pindahkan item ke sini atau buat item baru.</p>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-3">
        {{ $items->appends(request()->query())->links() }}
    </div>
</div>

<!-- Modal Update Qty Cepat -->
<div class="modal fade" id="qtyModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form id="qtyForm" method="POST">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-4 text-center">
                    <h5 class="mb-3">Update Quantity</h5>
                    <input type="number" name="qty" class="form-control form-control-lg text-center mb-3" placeholder="Contoh: 10 atau -5" required>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Update Stok</button>
                        <button type="button" class="btn btn-link text-muted btn-sm" data-bs-dismiss="modal">Batal</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Move Folder -->
<div class="modal fade" id="moveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="moveForm" method="POST">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-header"><h5>Pindahkan Item</h5></div>
                <div class="modal-body">
                    <p>Pindahkan <strong id="moveItemName"></strong> ke:</p>
                    <select name="folder_id" class="form-select">
                        <option value="">Root (Tanpa Folder)</option>
                        @foreach($allFolders as $f)
                            <option value="{{ $f->id }}">{{ $f->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Pindahkan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    .item-card { transition: all 0.2s; cursor: pointer; border: 1px solid #eee !important; }
    .item-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1) !important; border-color: #0d6efd !important; }
    .breadcrumb-item + .breadcrumb-item::before { content: "›"; font-size: 1.2rem; line-height: 1; vertical-align: middle; }
</style>

<script>
function openQtyModal(id) {
    const form = document.getElementById('qtyForm');
    form.action = `/item/${id}/update-quantity`;
    new bootstrap.Modal(document.getElementById('qtyModal')).show();
}

function openMoveModal(id, name) {
    const form = document.getElementById('moveForm');
    form.action = `/item/${id}/move`;
    document.getElementById('moveItemName').textContent = name;
    new bootstrap.Modal(document.getElementById('moveModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const bulkBar = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');

    checkboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            const checked = document.querySelectorAll('.item-checkbox:checked');
            bulkBar.style.display = checked.length > 0 ? 'block' : 'none';
            selectedCount.textContent = checked.length + ' Items Selected';
        });
    });
});
</script>
@endsection
