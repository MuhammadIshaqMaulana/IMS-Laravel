<!-- Modal Update Qty Cepat -->
<div class="modal fade" id="qtyModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form id="qtyForm" method="POST">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-4 text-center">
                    <h5 class="mb-3 text-truncate">Update Quantity</h5>
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

<!-- Modal Pindahkan (Mendukung FOLDER atau ITEM) -->
<div class="modal fade" id="moveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <!-- Action sekarang menunjuk ke route tanpa parameter -->
        <form id="moveForm" action="{{ route('item.move') }}" method="POST">
            @csrf
            <input type="hidden" name="id">
            <input type="hidden" name="target_type">

            <div class="modal-content border-0 shadow">
                <div class="modal-header"><h5>Pindahkan: <span id="moveItemName" class="text-primary"></span></h5></div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Pilih folder tujuan:</p>
                    <select name="folder_id" class="form-select form-select-lg shadow-sm">
                        <option value="">(Root / Inventaris Utama)</option>
                        @foreach($allFolders as $f)
                            <option value="{{ $f->id }}">{{ $f->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Konfirmasi Pindah</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Bulk Edit -->
<div class="modal fade" id="bulkEditModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="bulkEditForm" action="{{ route('item.bulk-update') }}" method="POST">
            @csrf
            <input type="hidden" name="selected_items">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning">
                    <h5 class="fw-bold m-0">Bulk Edit Items</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Set Minimum Level:</label>
                        <input type="number" name="min_level_value" class="form-control">
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-warning fw-bold px-4 shadow-sm">Terapkan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>
