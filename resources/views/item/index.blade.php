@extends('layouts.app')

@section('title', $currentFolder ? $currentFolder->nama : 'Inventaris')

@section('content')
<div class="h-100 d-flex flex-column">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold m-0">{{ $currentFolder ? $currentFolder->nama : 'Inventaris Utama' }}</h2>
            <small class="text-muted">{{ $items->total() }} items ditemukan</small>
        </div>
        <a href="{{ route('item.create', ['folder_id' => request('folder_id')]) }}" class="btn btn-primary px-4 shadow-sm">
            <i class="fas fa-plus me-2"></i> Create
        </a>
    </div>

    <!-- Bulk Action Toolbar -->
    <div id="bulkActions" class="bg-light p-2 mb-3 rounded shadow-sm border border-warning" style="display: none;">
        <div class="d-flex justify-content-between align-items-center px-2">
            <span id="selectedCount" class="fw-bold small">0 Selected</span>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-warning fw-bold" onclick="openBulkModal()">
                    <i class="fas fa-edit me-1"></i> Edit Massal
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">Export</button>
                    <ul class="dropdown-menu shadow">
                        <li><a class="dropdown-item" href="{{ route('item.export.csv') }}"><i class="fas fa-file-csv me-2"></i> CSV</a></li>
                        <li><a class="dropdown-item" href="{{ route('item.export.pdf') }}" target="_blank"><i class="fas fa-file-pdf me-2"></i> PDF</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row overflow-y-auto flex-grow-1 g-4 pb-4">
        @forelse($items as $item)
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm item-card position-relative">
                <!-- Checkbox -->
                <div class="position-absolute top-0 start-0 p-2 z-3">
                    <input class="form-check-input item-checkbox" type="checkbox" data-item-id="{{ $item->id }}">
                </div>

                <!-- Clickable Area -->
                <a href="{{ $item->is_folder ? route('item.index', ['folder_id' => $item->id]) : route('item.show', $item->id) }}"
                   class="text-decoration-none text-dark h-100 d-flex flex-column">
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center overflow-hidden" style="height: 160px;">
                        @if($item->is_folder)
                            @php $lastImg = $item->getLastItemImage(); @endphp
                            @if($lastImg)
                                <div class="position-relative w-100 h-100">
                                    <img src="{{ $lastImg }}" class="w-100 h-100 object-fit-cover opacity-50">
                                    <i class="fas fa-folder fa-4x text-warning position-absolute top-50 start-50 translate-middle shadow"></i>
                                </div>
                            @else
                                <i class="fas fa-folder fa-5x text-warning opacity-75"></i>
                            @endif
                        @else
                            @if($item->image_link)
                                <img src="{{ $item->image_link }}" class="w-100 h-100 object-fit-cover">
                            @else
                                <i class="fas {{ $item->is_bom ? 'fa-layer-group' : 'fa-box' }} fa-4x text-secondary opacity-20"></i>
                            @endif
                        @endif
                    </div>
                    <div class="card-body p-3">
                        <h6 class="card-title text-truncate fw-bold mb-1">{{ $item->nama }}</h6>
                        @if($item->is_folder)
                            <small class="text-muted"><i class="fas fa-folder-open me-1"></i> {{ $item->itemsInFolder()->count() }} items</small>
                        @else
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="badge {{ $item->calculated_stock <= $item->stok_minimum ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                                    {{ number_format($item->calculated_stock, 0) }} {{ $item->satuan }}
                                </span>
                                <span class="fw-bold small text-muted">Rp{{ number_format($item->harga_jual, 0) }}</span>
                            </div>
                        @endif
                    </div>
                </a>

                <!-- Quick Actions Footer -->
                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark" onclick="openMoveModal({{ $item->id }}, '{{ $item->nama }}')">
                        <i class="fas fa-external-link-alt"></i>
                    </button>
                    @if(!$item->is_folder)
                        <button class="btn btn-outline-light btn-sm flex-grow-1 border text-dark" onclick="openQtyModal({{ $item->id }}, '{{ $item->nama }}')">
                            <i class="fas fa-plus-minus"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5">
            <h4 class="text-muted">Folder Kosong</h4>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $items->appends(request()->query())->links() }}
    </div>
</div>

@include('item.modals')

<script>
    // 1. Logika Modal Update Qty Cepat
    function openQtyModal(id, name) {
        const form = document.getElementById('qtyForm');
        form.action = `/item/${id}/update-quantity`;
        document.querySelector('#qtyModal h5').textContent = `Adjust Stock: ${name}`;
        new bootstrap.Modal(document.getElementById('qtyModal')).show();
    }

    // 2. Logika Modal Pindah Folder
    function openMoveModal(id, name) {
        const form = document.getElementById('moveForm');
        form.action = `/item/${id}/move`;
        document.getElementById('moveItemName').textContent = name;
        new bootstrap.Modal(document.getElementById('moveModal')).show();
    }

    // 3. Logika Bulk Action Modal
    function openBulkModal() {
        const checked = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.dataset.itemId);
        // Pastikan input hidden 'selected_items' ada di form bulkEditForm
        const input = document.querySelector('#bulkEditForm input[name="selected_items"]');
        if(input) input.value = JSON.stringify(checked);

        new bootstrap.Modal(document.getElementById('bulkEditModal')).show();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Logika Seleksi Checkbox
        const checkboxes = document.querySelectorAll('.item-checkbox');
        const bulkBar = document.getElementById('bulkActions');
        const countSpan = document.getElementById('selectedCount');

        checkboxes.forEach(cb => {
            cb.addEventListener('change', () => {
                const checked = document.querySelectorAll('.item-checkbox:checked');
                bulkBar.style.display = checked.length > 0 ? 'block' : 'none';
                countSpan.textContent = `${checked.length} Item Selected`;
            });
        });
    });
</script>

<style>
    .item-card { transition: all 0.2s; border: 1px solid #f0f0f0 !important; cursor: default; }
    .item-card:hover { transform: translateY(-4px); border-color: #895129 !important; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1) !important; }
    .item-checkbox { cursor: pointer; }
</style>
@endsection
