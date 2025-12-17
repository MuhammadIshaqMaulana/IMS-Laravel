@extends('layouts.app')

@section('title', 'Manajemen Folder & Hierarki')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4"><i class="fas fa-folder me-2"></i> Manajemen Folder</h1>
    </div>

    <!-- Tombol Tambah Folder -->
    <div class="col-12 mb-4">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFolderModal">
            <i class="fas fa-folder-plus me-1"></i> Buat Folder Baru
        </button>
    </div>

    <!-- Daftar Folder -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-secondary text-white">Daftar Folder Utama</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    @forelse ($folders as $folder)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <i class="fas fa-folder me-2 text-warning"></i> {{ $folder->nama }}
                            <span class="badge bg-primary rounded-pill">{{ $folder->itemsInFolder()->count() }} Item</span>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-muted">Belum ada folder terdaftar.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <!-- Daftar Item di Root -->
    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-info text-dark">Item Tanpa Folder (Root)</div>
            <div class="card-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Stok</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rootItems as $item)
                        <tr>
                            <td>{{ $item->nama }}</td>
                            <td>{{ number_format($item->stok_saat_ini, 2) }} {{ $item->satuan }}</td>
                            <td>
                                <!-- Tombol Pindah -->
                                <button type="button" class="btn btn-sm btn-outline-secondary move-item-btn"
                                    data-item-id="{{ $item->id }}"
                                    data-item-name="{{ $item->nama }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#moveItemModal">
                                    Pindah
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center">Semua item sudah berada di dalam folder atau merupakan varian.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<!-- Modal Buat Folder -->
<div class="modal fade" id="createFolderModal" tabindex="-1" aria-labelledby="createFolderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="createFolderModalLabel">Buat Folder Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('folder.create') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="folder_name" class="form-label">Nama Folder</label>
                        <input type="text" class="form-control" id="folder_name" name="nama" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Folder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Pindah Item ke Folder -->
<div class="modal fade" id="moveItemModal" tabindex="-1" aria-labelledby="moveItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-dark">
                <h5 class="modal-title" id="moveItemModalLabel">Pindahkan Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('folder.move') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p>Pindahkan Item: <strong id="itemToMoveName"></strong></p>
                    <input type="hidden" name="item_id" id="itemToMoveId">

                    <div class="mb-3">
                        <label for="folder_id" class="form-label">Pilih Folder Tujuan</label>
                        <select class="form-select" name="folder_id" required>
                            <option value="">-- Pilih Folder --</option>
                            @foreach ($folders as $folder)
                                <option value="{{ $folder->id }}">{{ $folder->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info">Pindahkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const moveItemModal = document.getElementById('moveItemModal');
    moveItemModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const itemId = button.getAttribute('data-item-id');
        const itemName = button.getAttribute('data-item-name');

        const modalTitle = moveItemModal.querySelector('#itemToMoveName');
        const modalInputId = moveItemModal.querySelector('#itemToMoveId');

        modalTitle.textContent = itemName;
        modalInputId.value = itemId;
    });
});
</script>
@endsection
