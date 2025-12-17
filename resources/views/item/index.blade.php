@extends('layouts.app')

@section('title', 'Daftar Semua Item')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Daftar Semua Item ({{ $items->total() }})</h1>

        <!-- Kontrol Aksi Massal (Bulk Action) -->
        <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-white shadow-sm rounded">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="selectAllItems">
                <label class="form-check-label" for="selectAllItems">
                    Pilih Semua Item
                </label>
            </div>

            <!-- Tombol Aksi Massal (Akan ditampilkan saat ada item terpilih) -->
            <div id="bulkActions" style="display: none;">
                <span id="selectedCount" class="badge bg-secondary me-3">0 Item Terpilih</span>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#bulkEditModal">
                    <i class="fas fa-edit me-1"></i> Edit Massal
                </button>
            </div>







            <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-white shadow-sm rounded">
                <div class="form-check">
                    <!-- ... (Checkbox Select All) ... -->
                </div>

                <!-- Tombol Aksi Massal (Akan ditampilkan saat ada item terpilih) -->
                <div id="bulkActions" style="display: none;">
                    <span id="selectedCount" class="badge bg-secondary me-3">0 Item Terpilih</span>
                    <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#bulkEditModal">
                        <i class="fas fa-edit me-1"></i> Edit Massal
                    </button>
                </div>

                <!-- Dropdown Ekspor (BARU) -->
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-file-export me-1"></i> Ekspor
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="exportDropdown">
                        <li><a class="dropdown-item" href="{{ route('item.export.csv') }}"><i class="fas fa-file-csv me-1"></i> Ekspor ke CSV</a></li>
                        <li><a class="dropdown-item" href="{{ route('item.export.pdf') }}" target="_blank"><i class="fas fa-file-pdf me-1"></i> Ekspor ke PDF (Print)</a></li>
                    </ul>
                </div>

                <!-- Tombol Tambah Item -->
                <a href="{{ route('item.create') }}" class="btn btn-primary ms-2">
                    <i class="fas fa-plus"></i> Tambah Item Baru
                </a>
            </div>








            <!-- Tombol Tambah Item -->
            <a href="{{ route('item.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Item Baru
            </a>
        </div>

        <div id="itemContainer" class="row">
            @forelse ($items as $item)
            <div class="col-lg-4 col-md-6 mb-4 item-card-wrapper" data-item-id="{{ $item->id }}">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center bg-light">
                        <!-- Checkbox Seleksi Item Individu -->
                        <div class="form-check">
                            <input class="form-check-input item-checkbox" type="checkbox" data-item-id="{{ $item->id }}">
                        </div>

                        <!-- Penanda BOM -->
                        @if ($item->materials)
                           <span class="badge bg-primary"><i class="fas fa-puzzle-piece me-1"></i> BOM / KIT</span>
                        @else
                           <span class="badge bg-secondary">MATERIAL / ASSET</span>
                        @endif
                    </div>

                    <div class="card-body">
                        <!-- Image Placeholder (Nanti di Fase 11) -->
                        <div class="text-center mb-3">
                            <i class="fas fa-box" style="font-size: 3rem; color: #ccc;"></i>
                        </div>

                        <h5 class="card-title text-primary">{{ $item->nama }}</h5>

                        <p class="card-text mb-1">
                            <strong>SKU:</strong> <span class="text-muted">{{ $item->sku ?? '-' }}</span>
                        </p>

                        <p class="card-text mb-1">
                            @if ($item->materials)
                                <strong>Kapasitas Produksi:</strong>
                                <span class="fw-bold text-success">{{ number_format($item->calculated_stock, 0) }} {{ $item->satuan }}</span>
                                <small class="text-muted">(Stok Terhitung)</small>
                            @else
                                <strong>Stok Saat Ini:</strong>
                                <span class="fw-bold text-success">{{ number_format($item->stok_saat_ini, 2) }} {{ $item->satuan }}</span>
                            @endif
                        </p>

                        <p class="card-text mb-1">
                            <strong>Min. Level:</strong>
                            <span class="{{ $item->stok_saat_ini <= $item->stok_minimum && !$item->materials ? 'text-danger fw-bold' : 'text-secondary' }}">
                                {{ number_format($item->stok_minimum, 2) }} {{ $item->satuan }}
                            </span>
                        </p>
                        <p class="card-text mb-3">
                             <strong>Harga:</strong> Rp{{ number_format($item->harga_jual, 0, ',', '.') }}
                        </p>

                        <!-- Tags (Contoh Display) -->
                        @if ($item->tags)
                            <div class="mb-2">
                                @foreach($item->tags as $tag)
                                    <span class="badge bg-primary text-white">{{ $tag }}</span>
                                @endforeach
                            </div>
                        @endif

                        <!-- Custom Fields (Contoh Display) -->
                        @if ($item->custom_fields)
                            <div class="mb-2">
                                @foreach($item->custom_fields as $key => $value)
                                    <span class="badge bg-info text-dark">{{ $key }}</span>
                                @endforeach
                            </div>
                        @endif

                        <!-- Menampilkan Material/BOM di Card -->
                        @if ($item->materials)
                             <small class="d-block mt-3 text-muted">Material Penyusun ({{ count($item->materials) }} komponen)</small>
                        @endif

                    </div>
                    <div class="card-footer bg-white border-0 d-flex justify-content-end">
                        <a href="{{ route('item.edit', $item->id) }}" class="btn btn-sm btn-outline-info me-1">Edit</a>
                        <form action="{{ route('item.destroy', $item->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Yakin menghapus item ini?')">Hapus</button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="alert alert-info text-center mt-3">Belum ada item terdaftar.</div>
            </div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $items->links() }}
        </div>
    </div>
</div>

<!-- Modal Edit Massal -->
<div class="modal fade" id="bulkEditModal" tabindex="-1" aria-labelledby="bulkEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="bulkEditModalLabel"><i class="fas fa-edit"></i> Edit Massal Inventaris</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkEditForm" action="{{ route('item.bulk-update') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="lead" id="modalSelectedCount">Mempersiapkan edit untuk <span class="fw-bold text-primary">0 item</span>.</p>

                    <!-- Input tersembunyi untuk menyimpan ID item yang terpilih -->
                    <input type="hidden" name="selected_items" id="selectedItemsInput">

                    <div class="alert alert-info">
                        **Perhatian:** Harga akan dibulatkan **ke bawah** jika hasilnya desimal (integer).
                    </div>

                    <!-- Tabs Navigasi untuk Berbagai Tipe Edit -->
                    <ul class="nav nav-tabs" id="bulkEditTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="name-tab" data-bs-toggle="tab" data-bs-target="#name-pane" type="button" role="tab" aria-controls="name-pane" aria-selected="true">Nama & SKU</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="quantity-tab" data-bs-toggle="tab" data-bs-target="#quantity-pane" type="button" role="tab" aria-controls="quantity-pane" aria-selected="false">Level Min</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="price-tab" data-bs-toggle="tab" data-bs-target="#price-pane" type="button" role="tab" aria-controls="price-pane" aria-selected="false">Harga & Nilai</button>
                        </li>
                         <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tags-note-tab" data-bs-toggle="tab" data-bs-target="#tags-note-pane" type="button" role="tab" aria-controls="tags-note-pane" aria-selected="false">Tags & Catatan</button>
                        </li>
                    </ul>

                    <div class="tab-content pt-3" id="bulkEditTabsContent">
                        <!-- Pane 1: Nama & SKU -->
                        <div class="tab-pane fade show active" id="name-pane" role="tabpanel" aria-labelledby="name-tab" tabindex="0">
                            <div class="mb-3">
                                <label for="name_action" class="form-label">Aksi Nama</label>
                                <select class="form-select" name="name_action" id="name_action">
                                    <option value="">-- Pilih Aksi --</option>
                                    <option value="replace">Ganti dengan Teks Baru</option>
                                    <option value="prefix">Tambah Awalan (Prefix)</option>
                                    <option value="suffix">Tambah Akhiran (Suffix)</option>
                                    <option value="seq_prefix">Awalan Berurutan (Cth: 001 - Teks)</option>
                                    <option value="seq_suffix">Akhiran Berurutan (Cth: Teks - 001)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="name_value" class="form-label">Nilai Teks Baru</label>
                                <input type="text" class="form-control" name="name_value" placeholder="Masukkan teks (Cth: Promo Diskon)">
                            </div>
                        </div>

                        <!-- Pane 2: Kuantitas & Level Min -->
                         <div class="tab-pane fade" id="quantity-pane" role="tabpanel" aria-labelledby="quantity-tab" tabindex="0">
                             <div class="mb-3">
                                <label for="min_level_action" class="form-label">Aksi Stok Minimum</label>
                                <select class="form-select" name="min_level_action" id="min_level_action">
                                    <option value="">-- Pilih Aksi --</option>
                                    <option value="replace">Ganti dengan Nilai Baru</option>
                                    <option value="add">Tambahkan ke Nilai Eksisting</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="min_level_value" class="form-label">Nilai Numerik</label>
                                <input type="number" step="0.01" class="form-control" name="min_level_value" min="0" placeholder="Masukkan nilai (Cth: 10 atau 0.5)">
                            </div>
                        </div>

                        <!-- Pane 3: Harga & Nilai -->
                         <div class="tab-pane fade" id="price-pane" role="tabpanel" aria-labelledby="price-tab" tabindex="0">
                            <div class="mb-3">
                                <label for="price_action" class="form-label">Aksi Harga</label>
                                <select class="form-select" name="price_action" id="price_action">
                                    <option value="">-- Pilih Aksi --</option>
                                    <option value="replace">Ganti dengan Harga Baru</option>
                                    <option value="increment">Naikkan Harga (+)</option>
                                    <option value="decrement">Turunkan Harga (-)</option>
                                    <option value="multiply">Kalikan Harga (x)</option>
                                    <option value="divide">Bagikan Harga (:)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="price_value" class="form-label">Nilai Numerik (Rp)</label>
                                <input type="number" step="any" class="form-control" name="price_value" min="0" placeholder="Masukkan nilai (Cth: 5000)">
                            </div>
                        </div>

                         <!-- Pane 4: Tags & Catatan -->
                         <div class="tab-pane fade" id="tags-note-pane" role="tabpanel" aria-labelledby="tags-note-tab" tabindex="0">

                            <!-- Tags -->
                            <h6 class="mt-2">Edit Tags</h6>
                             <div class="mb-3">
                                <label for="tags_action" class="form-label">Aksi Tags</label>
                                <select class="form-select" name="tags_action" id="tags_action">
                                    <option value="">-- Pilih Aksi --</option>
                                    <option value="add">Tambahkan Tags Baru</option>
                                    <option value="remove">Hapus Tags Ini</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="tags_input" class="form-label">Daftar Tags (Pisahkan koma)</label>
                                <input type="text" class="form-control" name="tags_input" placeholder="Cth: roti, manis, diskon">
                            </div>

                            <hr>

                            <!-- Notes -->
                            <h6 class="mt-2">Edit Catatan (Notes)</h6>
                            <div class="mb-3">
                                <label for="note_action" class="form-label">Aksi Catatan</label>
                                <select class="form-select" name="note_action" id="note_action">
                                    <option value="">-- Pilih Aksi --</option>
                                    <option value="replace">Ganti dengan Teks Baru</option>
                                    <option value="prefix">Tambah Awalan (Prefix)</option>
                                    <option value="suffix">Tambah Akhiran (Suffix)</option>
                                    <option value="seq_prefix">Awalan Berurutan (Cth: 001 - Teks)</option>
                                    <option value="seq_suffix">Akhiran Berurutan (Cth: Teks - 001)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="note_value" class="form-label">Nilai Teks Baru</label>
                                <textarea class="form-control" name="note_value" rows="2" placeholder="Masukkan catatan baru"></textarea>
                            </div>

                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning" id="bulkApplyButton">Terapkan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- SCRIPT JS SELEKSI (DARI FASE 8) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('selectAllItems');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const bulkActionsContainer = document.getElementById('bulkActions');
    const selectedCountSpan = document.getElementById('selectedCount');
    const selectedItemsInput = document.getElementById('selectedItemsInput');
    const modalSelectedCount = document.getElementById('modalSelectedCount');
    const bulkApplyButton = document.getElementById('bulkApplyButton');

    // Array untuk menyimpan ID item yang terpilih
    let selectedItemIds = [];

    // Fungsi untuk mengupdate status tombol dan hitungan
    function updateSelectionStatus() {
        selectedItemIds = Array.from(itemCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.getAttribute('data-item-id'));

        const count = selectedItemIds.length;

        // Tampilkan/Sembunyikan Kontrol Bulk Action
        if (count > 0) {
            bulkActionsContainer.style.display = 'block';
            bulkApplyButton.disabled = false;
        } else {
            bulkActionsContainer.style.display = 'none';
            bulkApplyButton.disabled = true;
        }

        // Update hitungan di tampilan utama dan modal
        selectedCountSpan.textContent = `${count} Item Terpilih`;
        modalSelectedCount.innerHTML = `Mempersiapkan edit untuk <span class="fw-bold text-primary">${count} item</span>.`;

        // Update input tersembunyi untuk dikirim ke Controller
        selectedItemsInput.value = JSON.stringify(selectedItemIds);

        // Update status 'Pilih Semua'
        selectAllCheckbox.checked = count === itemCheckboxes.length && itemCheckboxes.length > 0;
    }

    // Listener untuk Item Individu
    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Beri highlight pada card wrapper
            const cardWrapper = this.closest('.item-card-wrapper');
            if (this.checked) {
                cardWrapper.classList.add('border', 'border-primary', 'border-3');
            } else {
                cardWrapper.classList.remove('border', 'border-primary', 'border-3');
            }
            updateSelectionStatus();
        });
    });

    // Listener untuk Pilih Semua
    selectAllCheckbox.addEventListener('change', function() {
        itemCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
            const cardWrapper = checkbox.closest('.item-card-wrapper');
             if (this.checked) {
                cardWrapper.classList.add('border', 'border-primary', 'border-3');
            } else {
                cardWrapper.classList.remove('border', 'border-primary', 'border-3');
            }
        });
        updateSelectionStatus();
    });

    // Inisialisasi status saat halaman dimuat
    updateSelectionStatus();
});
</script>
@endsection
