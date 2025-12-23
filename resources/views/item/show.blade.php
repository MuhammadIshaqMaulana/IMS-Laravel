@extends('layouts.app')

@section('title', 'Detail: ' . $item->nama)

@section('content')
<div class="container-fluid py-2">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('item.index', ['folder_id' => $item->folder_id]) }}" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
            <h2 class="fw-bold m-0 text-dark">{{ $item->nama }}</h2>
            <span class="badge bg-light text-muted border">SKU: {{ $item->sku ?? 'N/A' }}</span>
        </div>
        <div class="btn-group shadow-sm">
            <a href="{{ route('item.edit', $item->id) }}" class="btn btn-warning fw-bold px-4"><i class="fas fa-edit me-2"></i> Edit</a>
            <button class="btn btn-outline-danger" onclick="confirmDelete()"><i class="fas fa-trash"></i></button>
            <form id="delete-form" action="{{ route('item.destroy', $item->id) }}" method="POST" style="display:none;">
                @csrf @method('DELETE')
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Kolom Info Utama -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-0">
                    <div class="bg-light d-flex align-items-center justify-content-center overflow-hidden" style="height: 300px; border-radius: 8px 8px 0 0;">
                        @if($item->image_link)
                            <img src="{{ $item->image_link }}" class="w-100 h-100 object-fit-cover">
                        @else
                            <i class="fas {{ $item->is_bom ? 'fa-layer-group' : 'fa-box' }} fa-6x text-secondary opacity-25"></i>
                        @endif
                    </div>
                    <div class="p-4 text-center border-top">
                        <div class="row">
                            <div class="col-6 border-end">
                                <h4 class="fw-bold mb-0 {{ $item->calculated_stock <= $item->stok_minimum ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($item->calculated_stock, 0) }}
                                </h4>
                                <small class="text-muted text-uppercase" style="font-size: 0.7rem;">Stok Saat Ini</small>
                            </div>
                            <div class="col-6">
                                <h4 class="fw-bold mb-0 text-dark">Rp{{ number_format($item->harga_jual, 0) }}</h4>
                                <small class="text-muted text-uppercase" style="font-size: 0.7rem;">Harga Jual</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold py-3 border-bottom">Informasi Dasar</div>
                <div class="card-body py-3">
                    <div class="mb-3">
                        <label class="small text-muted text-uppercase d-block mb-1">Satuan</label>
                        <p class="fw-bold mb-0">{{ $item->satuan }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted text-uppercase d-block mb-1">Stok Minimum (Alert)</label>
                        <p class="fw-bold mb-0 text-warning">{{ number_format($item->stok_minimum, 0) }} {{ $item->satuan }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted text-uppercase d-block mb-1">Pemasok</label>
                        <p class="fw-bold mb-0">{{ $item->pemasok ?? '-' }}</p>
                    </div>
                    <div class="mb-0">
                        <label class="small text-muted text-uppercase d-block mb-1">Label / Tags</label>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @if($item->tags)
                                @foreach($item->tags as $tag)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $tag }}</span>
                                @endforeach
                            @else
                                <span class="text-muted small italic">Tidak ada tag.</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Note Section (Restored) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold py-3 border-bottom">Catatan (Note)</div>
                <div class="card-body py-3 bg-light-subtle">
                    @if($item->note)
                        <p class="mb-0 text-dark" style="white-space: pre-wrap;">{{ $item->note }}</p>
                    @else
                        <p class="mb-0 text-muted italic small text-center">Tidak ada catatan untuk item ini.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Kolom History & BOM -->
        <div class="col-lg-8">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm bg-success text-white p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold mb-0">+{{ number_format($stats['total_in'], 0) }}</h3>
                                <small class="text-uppercase opacity-75">Stok Masuk</small>
                            </div>
                            <i class="fas fa-arrow-trend-up fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm bg-danger text-white p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold mb-0">-{{ number_format($stats['total_out'], 0) }}</h3>
                                <small class="text-uppercase opacity-75">Stok Keluar</small>
                            </div>
                            <i class="fas fa-arrow-trend-down fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>

            @if($item->is_bom)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold text-primary py-3 border-bottom">
                    <i class="fas fa-layer-group me-2"></i> Komponen Material (BOM)
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Item Material</th>
                                <th class="text-end pe-4">Kuantitas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($item->materials as $mat)
                                @php $m = \App\Models\Item::find($mat['item_id']); @endphp
                                <tr>
                                    <td class="ps-4">
                                        <a href="{{ route('item.show', $m->id) }}" class="text-decoration-none fw-bold text-dark">{{ $m->nama }}</a>
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-muted">{{ number_format($mat['qty'], 2) }} {{ $m->satuan }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="card border-0 shadow-sm overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="fas fa-history me-2 text-muted"></i> Log Histori Transaksi</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 500px;">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="sticky-top bg-white border-bottom shadow-sm" style="z-index: 5;">
                                <tr>
                                    <th class="ps-4 small text-uppercase text-muted fw-bold">Waktu</th>
                                    <th class="small text-uppercase text-muted fw-bold">Aktivitas</th>
                                    <th class="text-end pe-4 small text-uppercase text-muted fw-bold">Perubahan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($history as $h)
                                <tr>
                                    <td class="ps-4 small text-muted">{{ $h->tanggal_produksi->format('d M Y, H:i') }}</td>
                                    <td class="small">{{ $h->catatan }}</td>
                                    <td class="text-end pe-4 fw-bold {{ $h->jumlah_produksi >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $h->jumlah_produksi >= 0 ? '+' : '' }}{{ number_format($h->jumlah_produksi, 0) }}
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="3" class="text-center py-5 text-muted">Belum ada aktivitas tercatat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete() {
        if(confirm('Yakin ingin menghapus item ini secara permanen?')) {
            document.getElementById('delete-form').submit();
        }
    }
</script>
@endsection
