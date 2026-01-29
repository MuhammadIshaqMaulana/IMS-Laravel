@extends('layouts.app')

@section('title', $search ? "Hasil Pencarian: $search" : ($currentFolder ? $currentFolder->nama : 'Inventaris'))

@section('content')
<div class="h-100 d-flex flex-column">
    <!-- [TETAP] ATAS: BREADCRUMBS & PENCARIAN GLOBAL -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="{{ route('item.index') }}" class="text-decoration-none text-muted"><i class="fas fa-home me-1"></i> Root</a></li>
                    @foreach($breadcrumbs as $bc)
                        <li class="breadcrumb-item"><a href="{{ route('item.index', ['folder_id' => $bc->id]) }}" class="text-decoration-none text-muted">{{ $bc->nama }}</a></li>
                    @endforeach
                </ol>
            </nav>
            <form action="{{ route('item.index') }}" method="GET" class="d-flex" style="width: 320px;">
                <div class="input-group input-group-sm shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 shadow-none" placeholder="Cari..." value="{{ $search }}">
                </div>
            </form>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold m-0 text-dark">
                <i class="fas {{ $currentFolder ? 'fa-folder-open text-warning' : 'fa-boxes text-brown' }} me-2"></i>
                {{ $currentFolder ? $currentFolder->nama : 'Inventaris Utama' }}
            </h2>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success fw-bold shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#importModal"><i class="fas fa-file-import me-1"></i> Import</button>
                <a href="{{ route('item.create', ['folder_id' => request('folder_id')]) }}" class="btn btn-primary px-4 fw-bold shadow-sm">+ Tambah Baru</a>
            </div>
        </div>
    </div>

        <!-- [BARU] GLOBAL SELECTION BAR -->
    <div id="globalSelectBar" class="alert alert-info py-2 mb-3 text-center shadow-sm" style="display: none; font-size: 0.85rem;">
        Halaman ini terpilih. <a href="javascript:void(0)" onclick="setGlobalSelect(true)" class="fw-bold text-decoration-underline">Pilih seluruh data di folder ini</a>
    </div>
    <div id="globalSelectedActive" class="alert alert-warning py-2 mb-3 text-center shadow-sm" style="display: none; font-size: 0.85rem;">
        <i class="fas fa-globe me-2"></i> Seluruh data dalam folder ini telah terpilih. <a href="javascript:void(0)" onclick="setGlobalSelect(false)" class="fw-bold text-decoration-underline">Batalkan Pilihan Global</a>
    </div>

    <!-- [DITIMPA] BULK ACTIONS TOOLBAR -->
    <div id="bulkActions" class="bg-dark text-white p-2 mb-3 rounded shadow-lg border border-warning sticky-top" style="display: none; z-index: 1000;">
        <div class="d-flex justify-content-between align-items-center px-2">
            <div class="d-flex align-items-center gap-3">
                <div class="form-check pt-1">
                    <input class="form-check-input" type="checkbox" id="selectAllItems" style="width: 1.2rem; height: 1.2rem;">
                    <label class="form-check-label small fw-bold text-warning" for="selectAllItems">Pilih Semua</label>
                </div>
                <span id="selectedCount" class="fw-bold small badge bg-warning text-dark px-2">0 Selected</span>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-success fw-bold px-3" onclick="exportSelected('csv')">CSV</button>
                <button class="btn btn-sm btn-danger fw-bold px-3" onclick="exportSelected('pdf')">PDF</button>
                <div class="vr mx-1 opacity-25"></div>
                <button class="btn btn-sm btn-info text-white fw-bold px-3" onclick="openBulkQtyModal()">Stok</button>
                <button class="btn btn-sm btn-warning fw-bold text-dark px-3" onclick="openBulkModal()">Edit Data</button>
            </div>
        </div>
    </div>

    <div class="row overflow-y-auto flex-grow-1 g-4 pb-5" id="inventoryGrid">
        @foreach($subFolders as $folder)
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card h-100 border-0 shadow-sm item-card">
                    <a href="{{ route('item.index', ['folder_id' => $folder->id]) }}" class="text-decoration-none text-dark h-100 d-flex flex-column">
                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 140px;"><i class="fas fa-folder fa-5x text-warning opacity-75"></i></div>
                        <div class="card-body p-3 text-center">
                            <h6 class="card-title text-truncate fw-bold mb-1">{{ $folder->nama }}</h6>
                            <div class="d-flex justify-content-center gap-2">
                                <span class="badge bg-light text-muted border fw-normal"><i class="fas fa-folder-open me-1 text-warning"></i> {{ $folder->children_count }}</span>
                                <span class="badge bg-light text-muted border fw-normal"><i class="fas fa-box me-1 text-secondary"></i> {{ $folder->items_count }}</span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        @endforeach

        <div class="row g-4 m-0 p-0" id="items-container">
            @include('item.partials.item_list', ['items' => $items, 'materialMap' => $materialMap])
        </div>

        @if($subFolders->isEmpty() && $items->isEmpty())
        <div class="col-12 text-center py-5 opacity-50">
            <i class="fas fa-box-open fa-5x mb-3 text-muted"></i>
            <h4 class="text-muted">Tidak ada data ditemukan.</h4>
        </div>
        @endif

        <!-- [BARU] SENTINEL (Pemicu Scroll) -->
        <div id="load-more-sentinel" class="col-12 text-center py-4" style="{{ $items->hasMorePages() ? '' : 'display: none;' }}">
            <div class="spinner-border text-primary" role="status"></div>
        </div>
    </div>
</div>

<!-- Modal Import CSV (DITAMBAHKAN ID FORM) -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('item.import') }}" method="POST" enctype="multipart/form-data" id="turboImportForm">
            @csrf
            <input type="hidden" name="folder_id" value="{{ request('folder_id') }}">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white"><h5 class="fw-bold m-0">Bulk Import CSV</h5></div>
                <div class="modal-body p-4">
                    <div class="alert alert-info small">Format CSV: <strong>nomor, nama, satuan, stok_saat_ini, stok_minimum, harga_jual, harga_beli, note, materials, tags</strong>.</div>
                    <label class="form-label fw-bold">Pilih File CSV:</label>
                    <input type="file" name="file_csv" class="form-control" accept=".csv" required>
                </div>
                <div class="modal-footer border-0"><button type="submit" class="btn btn-success fw-bold w-100 shadow-sm">Proses Import Sekarang</button></div>
            </div>
        </form>
    </div>
</div>

<!-- [DITIMPA] Modal Loading yang lebih universal -->
<div class="modal fade" id="turboLoadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg bg-dark text-white">
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-success mb-4 d-inline-block" role="status"
                     style="width: 4rem; height: 4rem; border-width: 0.4em;">
                </div>
                <h4 class="fw-bold mb-2">Sedang Memproses Data...</h4>
                <p class="text-white-50 small mb-0">Sistem sedang mengolah ribuan data inventaris kamu.</p>
                <div id="loadingTimer" class="fw-bold fs-4 text-white font-monospace mt-3">00:00</div>
            </div>
        </div>
    </div>
</div>

<!-- FORM EXPORT HIDDEN -->
<form id="exportForm" action="" method="GET" style="display:none;">
    <input type="hidden" name="ids" id="exportIds">
    <input type="hidden" name="global" id="exportGlobal" value="false">
    <input type="hidden" name="folder_id" value="{{ request('folder_id') }}">
</form>

@include('item.modals')

<script>
let isGlobalSelect = false;

    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAllItems');
        const bulkBar = document.getElementById('bulkActions');
        const countSpan = document.getElementById('selectedCount');
        const globalBar = document.getElementById('globalSelectBar');
        const globalActiveBar = document.getElementById('globalSelectedActive');

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('item-checkbox') || e.target.id === 'selectAllItems') {
                if (e.target.id === 'selectAllItems') {
                    document.querySelectorAll('.item-checkbox').forEach(c => c.checked = e.target.checked);
                }
                updateUI();
            }
        });

        function updateUI() {
            const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
            const totalOnPage = document.querySelectorAll('.item-checkbox').length;

            bulkBar.style.display = checkedCount > 0 ? 'block' : 'none';
            countSpan.textContent = isGlobalSelect ? "Seluruh Data Terpilih" : `${checkedCount} Terpilih`;

            // Gmail style notice
            if (checkedCount === totalOnPage && totalOnPage > 0 && !isGlobalSelect) {
                globalBar.style.display = 'block';
            } else {
                globalBar.style.display = 'none';
            }
        }

        window.setGlobalSelect = function(val) {
            isGlobalSelect = val;
            globalBar.style.display = 'none';
            globalActiveBar.style.display = val ? 'block' : 'none';
            updateUI();
        }
    });

    function exportSelected(format) {
        const ids = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.dataset.itemId);

        if (!isGlobalSelect && format === 'pdf' && ids.length > 1000) {
            alert("Maksimal PDF 1000 item. Kamu memilih " + ids.length + ". Kurangi atau pakai CSV.");
            return;
        }

        const form = document.getElementById('exportForm');
        document.getElementById('exportIds').value = JSON.stringify(ids);
        document.getElementById('exportGlobal').value = isGlobalSelect;

        form.action = format === 'csv' ? "{{ route('item.export.csv') }}" : "{{ route('item.export.pdf') }}";
        if(format === 'pdf') form.target = "_blank";
        form.submit();
    }

    // --- [BARU] SCRIPT BLOCKER & EXIT GUARD ---
    document.addEventListener('DOMContentLoaded', function() {
        const importForm = document.getElementById('turboImportForm');
        const loadingModalElement = document.getElementById('turboLoadingModal');
        const loadingModal = new bootstrap.Modal(loadingModalElement);
        const timerDisplay = document.getElementById('loadingTimer');

        let seconds = 0;
        let isImporting = false;

        if(importForm) {
            importForm.addEventListener('submit', function(e) {
                // 1. Tampilkan loading modal & tutup modal input
                const importModalEl = document.getElementById('importModal');
                const importModalBus = bootstrap.Modal.getInstance(importModalEl);
                if(importModalBus) importModalBus.hide();
                loadingModal.show();

                // 2. Jalankan timer
                setInterval(() => {
                    seconds++;
                    const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
                    const secs = String(seconds % 60).padStart(2, '0');
                    timerDisplay.textContent = `${mins}:${secs}`;
                }, 1000);

                // 3. LOCK status agar onbeforeunload TIDAK terpicu saat submit
                // Ini triknya: kita set false dulu sebentar supaya navigasi submit dianggap aman
                isImporting = false;

                // Kasih jeda 10ms lalu kunci lagi buat jaga-jaga user tekan F5/X setelah submit jalan
                setTimeout(() => { isImporting = true; }, 100);
            });
        }

        // EXIT GUARD: Hanya muncul jika user sengaja klik link lain atau tutup tab
        window.onbeforeunload = function() {
            if (isImporting) {
                return "Proses sedang berjalan...";
            }
        };
    });

    // --- [BARU] ENDLESS SCROLL LOGIC ---
    document.addEventListener('DOMContentLoaded', function() {
        let nextUrl = document.querySelector('.next-page-url')?.dataset.url;
        const itemsContainer = document.getElementById('items-container');
        const sentinel = document.getElementById('load-more-sentinel');
        let isLoading = false;

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && nextUrl && !isLoading) {
                loadMore();
            }
        }, { threshold: 0.1 });

        if (sentinel) observer.observe(sentinel);

        async function loadMore() {
            isLoading = true;
            try {
                const response = await fetch(nextUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const html = await response.text();

                // Hapus penanda URL lama
                const oldMarker = document.querySelector('.next-page-url');
                if (oldMarker) oldMarker.remove();

                // Append data baru
                itemsContainer.insertAdjacentHTML('beforeend', html);

                // Cari URL baru dari hasil append
                const newMarker = document.querySelector('.next-page-url');
                nextUrl = newMarker ? newMarker.dataset.url : null;

                if (!nextUrl) sentinel.style.display = 'none';

                // Re-init tooltips untuk item baru
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl); });

                // Update listener checkbox untuk bulk actions
                attachCheckboxListeners();

            } catch (error) {
                console.error("Gagal load data scroll:", error);
            } finally {
                isLoading = false;
            }
        }

        // Helper untuk re-attach listener checkbox pada item baru
        function attachCheckboxListeners() {
            const checks = document.querySelectorAll('.item-checkbox');
            checks.forEach(c => {
                c.removeEventListener('change', updateUI); // cegah double listener
                c.addEventListener('change', updateUI);
            });
        }
        // --- AKHIR ENDLESS SCROLL ---

        // ... (Script Import Form & Modal Navigasi tetap di bawah ini) ...
        const importForm = document.getElementById('turboImportForm');
        // ... (sisanya tetap sesuai kode lama loe)
    });

    // --- FUNGSI MODAL TETAP ---
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
    function openDeleteFolderModal(id, name) {
        const form = document.getElementById('deleteFolderForm');
        document.getElementById('deleteFolderNameText').textContent = name;
        form.action = `/folder/${id}/delete`;
        new bootstrap.Modal(document.getElementById('deleteFolderModal')).show();
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
</script>

<style>
    .item-card { transition: all 0.25s ease; border: 1px solid #f0f0f0 !important; }
    .item-card:hover { transform: translateY(-5px); border-color: #895129 !important; box-shadow: 0 15px 25px -5px rgba(0,0,0,0.1) !important; }
    .pagination-wrapper nav { display: flex; justify-content: center; }
</style>
@endsection
