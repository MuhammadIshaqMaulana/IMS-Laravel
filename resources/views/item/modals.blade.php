<!-- Modal Edit Nama Folder -->
<div class="modal fade" id="editFolderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="editFolderForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-content border-0 shadow">
                <div class="modal-header"><h5><i class="fas fa-edit me-2 text-warning"></i> Ubah Nama Folder</h5></div>
                <div class="modal-body">
                    <label class="form-label small fw-bold">Nama Folder Baru:</label>
                    <input type="text" name="nama" id="editFolderNameInput" class="form-control shadow-sm" required>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Pindahkan -->
<div class="modal fade" id="moveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="moveForm" action="{{ route('item.move') }}" method="POST">
            @csrf
            <input type="hidden" name="id">
            <input type="hidden" name="target_type">
            <div class="modal-content border-0 shadow">
                <div class="modal-header"><h5><i class="fas fa-external-link-alt me-2 text-primary"></i> Pindahkan: <span id="moveItemName" class="text-primary"></span></h5></div>
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

<!-- Modal Update Qty Cepat -->
<div class="modal fade" id="qtyModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form id="qtyForm" method="POST">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-body p-4 text-center">
                    <h5 class="mb-3 text-truncate fw-bold">Update Quantity</h5>
                    <input type="number" name="qty" class="form-control form-control-lg text-center mb-3 shadow-sm" placeholder="Contoh: 10 atau -5" required>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary fw-bold">Update Stok</button>
                        <button type="button" class="btn btn-link text-muted btn-sm" data-bs-dismiss="modal">Batal</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Bulk Edit Data -->
<div class="modal fade" id="bulkEditModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="bulkEditForm" action="{{ route('item.bulk-update') }}" method="POST">
            @csrf
            <input type="hidden" name="selected_items">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-warning">
                    <h5 class="fw-bold m-0"><i class="fas fa-edit me-2"></i> Bulk Edit Data</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Harga Jual Baru:</label>
                            <input type="number" name="harga_jual_value" class="form-control" placeholder="Biarkan kosong">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Stok Minimum:</label>
                            <input type="number" name="min_level_value" class="form-control" placeholder="Biarkan kosong">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Satuan:</label>
                            <input type="text" name="satuan_value" class="form-control" placeholder="cth: kg, pcs">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Pindah ke Folder:</label>
                            <select name="folder_id_value" class="form-select">
                                <option value="">(Jangan pindahkan)</option>
                                <option value="NULL">(Balik ke Root)</option>
                                @foreach($allFolders as $f)
                                    <option value="{{ $f->id }}">{{ $f->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Tambah Tags (Pisahkan koma):</label>
                            <input type="text" name="tags_input_value" class="form-control" placeholder="promo, cuci-gudang">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-warning fw-bold px-4 shadow-sm">Terapkan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Bulk Qty Adjustment (+/-) -->
<div class="modal fade" id="bulkQtyModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <form id="bulkQtyForm" action="{{ route('item.bulk-update-quantity') }}" method="POST">
            @csrf
            <input type="hidden" name="selected_items">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-info text-white">
                    <h5 class="fw-bold m-0"><i class="fas fa-plus-minus me-2"></i> Stok Massal</h5>
                </div>
                <div class="modal-body p-4 text-center">
                    <p class="small text-muted">Sesuaikan stok seluruh item terpilih:</p>
                    <input type="number" name="qty_adjustment" class="form-control form-control-lg text-center mb-3 shadow-sm" placeholder="cth: 50 atau -20" required>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-info text-white fw-bold">Update Massal</button>
                        <button type="button" class="btn btn-link text-muted btn-sm" data-bs-dismiss="modal">Batal</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Bulk Clone Confirmation -->
<div class="modal fade" id="bulkCloneModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="bulkCloneForm" action="{{ route('item.bulk-clone') }}" method="POST">
            @csrf
            <input type="hidden" name="selected_items">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="fw-bold m-0"><i class="fas fa-copy me-2"></i> Konfirmasi Clone</h5>
                </div>
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-clone fa-4x text-success opacity-25 mb-3"></i>
                    <h5>Duplikat <span id="cloneCountLabel" class="fw-bold">0</span> Item?</h5>
                    <p class="text-muted small">Setiap item akan diduplikasi dengan akhiran <span class="badge bg-light text-dark border"> - copy</span> pada namanya.</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">Ya, Duplikat Sekarang</button>
                </div>
            </div>
        </form>
    </div>
</div>
