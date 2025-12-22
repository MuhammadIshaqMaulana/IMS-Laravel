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

<!-- Modal Bulk Edit (Placeholder) -->
<div class="modal fade" id="bulkEditModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning"><h5>Bulk Edit Items</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center py-5">
                <p class="text-muted">Gunakan fitur Bulk Update di Fase 10 atau tutup untuk lanjut.</p>
            </div>
        </div>
    </div>
</div>
