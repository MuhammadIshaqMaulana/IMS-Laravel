@extends('layouts.app')

@section('title', 'Detail: ' . $item->nama)

@section('content')
<div class="row">
    <!-- Kolom Kiri: Detail & Info -->
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h1 class="mb-1">{{ $item->nama }}</h1>
                        <span class="badge bg-{{ $item->is_bom ? 'primary' : 'secondary' }}">
                            {{ $item->is_bom ? 'BOM / Kit Bundle' : 'Material Item' }}
                        </span>
                        <p class="text-muted mt-2"><i class="fas fa-barcode me-1"></i> SKU: {{ $item->sku ?? 'N/A' }}</p>
                    </div>
                    <div class="text-end">
                        <div class="display-5 fw-bold text-success">{{ number_format($item->calculated_stock, 0) }}</div>
                        <div class="text-muted">{{ $item->satuan }} tersedia</div>
                    </div>
                </div>

                <div class="row border-top pt-4">
                    <div class="col-md-6 mb-3">
                        <label class="small text-muted d-block">Harga Jual / Nilai</label>
                        <h4 class="fw-bold">Rp{{ number_format($item->harga_jual, 0) }}</h4>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="small text-muted d-block">Pemasok</label>
                        <h5 class="fw-normal">{{ $item->pemasok ?? '-' }}</h5>
                    </div>
                </div>

                @if($item->is_bom)
                <div class="mt-4 p-3 bg-light rounded">
                    <h6 class="fw-bold mb-3"><i class="fas fa-list me-2"></i> Komponen Penyusun (BOM)</h6>
                    <table class="table table-sm table-borderless mb-0">
                        @foreach($item->materials as $mat)
                            @php $m = \App\Models\Item::find($mat['item_id']); @endphp
                            <tr>
                                <td>{{ $m->nama }}</td>
                                <td class="text-end fw-bold">{{ $mat['qty'] }} {{ $m->satuan }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
                @endif

                <div class="mt-4">
                    <h6>Catatan:</h6>
                    <p class="text-secondary">{{ $item->note ?: 'Tidak ada catatan.' }}</p>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <a href="{{ route('item.edit', $item->id) }}" class="btn btn-warning flex-grow-1">Edit Item</a>
                <button class="btn btn-outline-secondary flex-grow-1" onclick="openMoveModal({{ $item->id }})">Pindahkan</button>
            </div>
        </div>

        <!-- History Activity -->
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between">
                <span><i class="fas fa-history me-2"></i> Histori Aktivitas</span>
                <button class="btn btn-sm btn-outline-light">Filter</button>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($history as $h)
                        <tr>
                            <td>{{ $h->tanggal_produksi->format('d/m/Y H:i') }}</td>
                            <td>{{ $h->catatan ?: 'Produksi/Update Stok' }}</td>
                            <td class="{{ $h->jumlah_produksi >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $h->jumlah_produksi >= 0 ? '+' : '' }}{{ $h->jumlah_produksi }}
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center py-3 text-muted">Belum ada aktivitas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Tags & Custom Fields -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-bold">Tags</div>
            <div class="card-body">
                @if($item->tags)
                    @foreach($item->tags as $tag)
                        <span class="badge bg-info text-dark mb-1">{{ $tag }}</span>
                    @endforeach
                @else
                    <span class="text-muted small">Tidak ada tag.</span>
                @endif
            </div>
        </div>

        @if($item->custom_fields)
        <div class="card shadow-sm">
            <div class="card-header fw-bold">Custom Atribut</div>
            <div class="card-body">
                @foreach($item->custom_fields as $key => $val)
                    <div class="mb-2">
                        <span class="small text-muted">{{ $key }}</span>
                        <div class="fw-bold">{{ $val }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
