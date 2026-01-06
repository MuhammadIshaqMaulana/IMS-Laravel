@extends('layouts.app')
@section('title', 'Edit: ' . $item->nama)
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <form action="{{ route('item.update', $item->id) }}" method="POST" id="mainForm">
            @csrf @method('PUT')
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold m-0">Edit Inventaris</h3>
                <div class="form-check form-switch border p-2 px-4 rounded bg-white shadow-sm">
                    <input class="form-check-input" type="checkbox" name="is_bom" id="is_bom" {{ $item->is_bom ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold" for="is_bom"><i class="fas fa-layer-group me-1 text-primary"></i> Aktifkan Mode BOM</label>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4 p-4">
                <!-- Info Utama Sama dengan Create... -->
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold small">Nama Item</label>
                        <input type="text" name="nama" class="form-control" value="{{ old('nama', $item->nama) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold small">Folder</label>
                        <select name="folder_id" class="form-select">
                            <option value="">(Utama)</option>
                            @foreach($allFolders as $f)
                                <option value="{{ $f->id }}" {{ $item->folder_id == $f->id ? 'selected' : '' }}>{{ $f->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label small fw-bold">Satuan</label>
                        <input type="text" name="satuan" class="form-control" value="{{ $item->satuan }}">
                    </div>
                    <div class="col-md-3 mb-3" id="qty_group" style="display: {{ $item->is_bom ? 'none' : 'block' }}">
                        <label class="form-label small fw-bold text-primary">Stok Saat Ini</label>
                        <input type="number" name="stok_saat_ini" class="form-control border-primary" value="{{ $item->stok_saat_ini }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label small fw-bold">Stok Minimum</label>
                        <input type="number" name="stok_minimum" class="form-control" value="{{ $item->stok_minimum }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label small fw-bold">Harga Jual (Rp)</label>
                        <input type="number" name="harga_jual" class="form-control" value="{{ $item->harga_jual }}">
                    </div>
                </div>

                <!-- BOM SECTION -->
                <div id="bom_section" style="display: {{ $item->is_bom ? 'block' : 'none' }};" class="bg-primary-subtle p-3 rounded mb-3 border border-primary-subtle">
                    <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-list me-2"></i> Komponen Material (Resep BOM)</h6>
                    <div id="bom_rows">
                        @if($item->is_bom)
                            @foreach($item->materials as $mat)
                            <div class="row g-2 mb-2 material-row">
                                <div class="col-7">
                                    <select class="form-select mat-id shadow-sm">
                                        @foreach($allMaterials as $m)
                                            <option value="{{$m->id}}" {{ $mat['item_id'] == $m->id ? 'selected' : '' }}>{{$m->nama}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-3"><input type="number" step="0.01" class="form-control mat-qty shadow-sm" value="{{ $mat['qty'] }}"></div>
                                <div class="col-2"><button type="button" class="btn btn-danger w-100" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-times"></i></button></div>
                            </div>
                            @endforeach
                        @endif
                    </div>
                    <button type="button" class="btn btn-sm btn-primary mt-2" id="add_material_btn"><i class="fas fa-plus"></i> Tambah Bahan Baku</button>
                    <input type="hidden" name="materials_data" id="materials_data">
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label small fw-bold">Tags</label>
                        <input type="text" name="tags_input" class="form-control" value="{{ is_array($item->tags) ? implode(', ', $item->tags) : '' }}">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Catatan & Note</label>
                        <textarea name="note" class="form-control" rows="3">{{ $item->note }}</textarea>
                        @if($item->note) <div class="mt-2 p-2 bg-warning-subtle small rounded border border-warning-subtle"><strong>Note saat ini:</strong> {{ $item->note }}</div> @endif
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-warning btn-lg w-100 shadow-sm fw-bold" id="btnSubmit">Update Data Inventaris</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const isBom = document.getElementById('is_bom');
        const bomSection = document.getElementById('bom_section');
        const qtyGroup = document.getElementById('qty_group');

        isBom.addEventListener('change', () => {
            bomSection.style.display = isBom.checked ? 'block' : 'none';
            qtyGroup.style.display = isBom.checked ? 'none' : 'block';
        });

        const bomRows = document.getElementById('bom_rows');
        document.getElementById('add_material_btn').addEventListener('click', () => {
            const div = document.createElement('div');
            div.className = 'row g-2 mb-2 material-row';
            div.innerHTML = `
                <div class="col-7"><select class="form-select mat-id shadow-sm">@foreach($allMaterials as $m)<option value="{{$m->id}}">{{$m->nama}}</option>@endforeach</select></div>
                <div class="col-3"><input type="number" step="0.01" class="form-control mat-qty shadow-sm" placeholder="Qty"></div>
                <div class="col-2"><button type="button" class="btn btn-danger w-100" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-times"></i></button></div>`;
            bomRows.appendChild(div);
        });

        document.getElementById('btnSubmit').addEventListener('click', () => {
            const mats = [];
            if(isBom.checked) {
                document.querySelectorAll('.material-row').forEach(r => {
                    const id = r.querySelector('.mat-id').value;
                    const qty = r.querySelector('.mat-qty').value;
                    if(id && qty) mats.push({ item_id: id, qty: qty });
                });
            }
            document.getElementById('materials_data').value = JSON.stringify(mats);
        });
    });
</script>
@endsection
