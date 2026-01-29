@extends('layouts.app')

@section('title', 'Detail: ' . $item->nama)

@section('content')
<div class="container-fluid py-2">
    <!-- HEADER: NAVIGASI & AKSI -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('item.index', ['folder_id' => $item->folder_id]) }}" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="fas fa-arrow-left me-1"></i> Kembali ke Folder
            </a>
            <h2 class="fw-bold m-0 text-dark">{{ $item->nama }}</h2>
            <div class="mt-1">
                <span class="badge bg-light text-muted border">SKU: {{ $item->sku ?? 'N/A' }}</span>
                @if($item->is_bom)
                    <span class="badge bg-primary shadow-sm"><i class="fas fa-layer-group me-1"></i> Bill of Materials (BOM)</span>
                @else
                    <span class="badge bg-secondary shadow-sm"><i class="fas fa-box me-1"></i> Item Fisik</span>
                @endif
            </div>
        </div>
        <div class="btn-group shadow-sm">
            <a href="{{ route('item.edit', $item->id) }}" class="btn btn-warning fw-bold px-4">
                <i class="fas fa-edit me-2"></i> Edit Data
            </a>
            <button class="btn btn-outline-danger" onclick="confirmDelete()">
                <i class="fas fa-trash"></i>
            </button>
            <form id="delete-form" action="{{ route('item.destroy', $item->id) }}" method="POST" style="display:none;">
                @csrf @method('DELETE')
            </form>
        </div>
    </div>

    <div class="row">
        <!-- KOLOM KIRI: VISUAL & INFORMASI FINANSIAL -->
        <div class="col-lg-4">
            <!-- Card Foto & Ringkasan Utama -->
            <div class="card border-0 shadow-sm mb-4 overflow-hidden">
                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 250px;">
                    @if($item->image_link)
                        <img src="{{ $item->image_link }}" class="w-100 h-100 object-fit-cover">
                    @else
                        <i class="fas {{ $item->is_bom ? 'fa-layer-group text-primary' : 'fa-box text-secondary' }} fa-6x opacity-25"></i>
                    @endif
                </div>
                <div class="card-body p-0 border-top">
                    <div class="row g-0 text-center">
                        <div class="col-4 border-end py-3">
                            <h5 class="fw-bold mb-0 {{ $item->calculated_stock <= $item->stok_minimum ? 'text-danger' : 'text-success' }}">
                                {{ number_format($item->calculated_stock, 0) }}
                            </h5>
                            <small class="text-muted text-uppercase small-85">Stok</small>
                        </div>
                        <div class="col-4 border-end py-3 bg-light-subtle">
                            <h5 class="fw-bold mb-0 text-muted">Rp{{ number_format($item->harga_beli, 0) }}</h5>
                            <small class="text-muted text-uppercase small-85">Hrg Beli</small>
                        </div>
                        <div class="col-4 py-3">
                            <h5 class="fw-bold mb-0 text-dark">Rp{{ number_format($item->harga_jual, 0) }}</h5>
                            <small class="text-muted text-uppercase small-85">Hrg Jual</small>
                        </div>
                    </div>
                </div>
                <!-- Progress Margin (Gimmick Presentasi) -->
                @php
                    $margin = $item->harga_jual - $item->harga_beli;
                    $percent = $item->harga_beli > 0 ? ($margin / $item->harga_beli) * 100 : 0;
                @endphp
                <div class="card-footer bg-white border-top-0 px-4 pb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="fw-bold text-muted text-uppercase">Estimasi Margin</small>
                        <small class="fw-bold {{ $margin >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ $margin >= 0 ? '+' : '' }}{{ number_format($percent, 1) }}%
                        </small>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar {{ $margin >= 0 ? 'bg-success' : 'bg-danger' }}" role="progressbar" style="width: {{ min(max($percent, 0), 100) }}%"></div>
                    </div>
                </div>
            </div>

            <!-- Card Informasi Detail -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold py-3 border-bottom">
                    <i class="fas fa-info-circle me-2 text-primary"></i> Atribut Item
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="small text-muted text-uppercase d-block mb-1">Satuan Unit</label>
                        <p class="fw-bold mb-0 text-dark">{{ $item->satuan }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted text-uppercase d-block mb-1">Ambang Stok Minimum</label>
                        <p class="fw-bold mb-0 text-warning">
                            <i class="fas fa-bell me-1"></i> {{ number_format($item->stok_minimum, 0) }} {{ $item->satuan }}
                        </small>
                    </div>
                    <div class="mb-0">
                        <label class="small text-muted text-uppercase d-block mb-1">Label / Tags</label>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @if($item->tags)
                                @foreach($item->tags as $tag)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">#{{ $tag }}</span>
                                @endforeach
                            @else
                                <span class="text-muted small italic">N/A</span>
                            @endif
                        </div>
                    </div>
                    <!-- [BARU] TIMESTAMP SECTION -->
                    <div class="border-top pt-3 mt-auto">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Dibuat:</span>
                            <span class="fw-bold">{{ $item->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted">
                            <span>Diupdate:</span>
                            <span class="fw-bold">{{ $item->updated_at->format('d M Y, H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Catatan / Notes -->
            <div class="card border-0 shadow-sm mb-4 border-start border-warning border-4">
                <div class="card-header bg-white fw-bold py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-sticky-note me-2 text-warning"></i> Catatan Internal</span>
                </div>
                <div class="card-body bg-light-subtle">
                    @if($item->note)
                        <p class="mb-0 text-dark" style="white-space: pre-wrap; font-size: 0.9rem; line-height: 1.6;">{{ $item->note }}</p>
                    @else
                        <p class="mb-0 text-muted italic small text-center py-2">Tidak ada catatan untuk item ini.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: STATISTIK, BOM & LOG -->
        <div class="col-lg-8">
            <!-- Ringkasan Pergerakan -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm bg-success text-white p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold mb-0">{{ number_format($stats['total_in'], 0) }}</h3>
                                <small class="text-uppercase opacity-75">Total Stok Masuk</small>
                            </div>
                            <i class="fas fa-arrow-up-right-dots fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm bg-danger text-white p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h3 class="fw-bold mb-0">{{ number_format($stats['total_out'], 0) }}</h3>
                                <small class="text-uppercase opacity-75">Total Stok Keluar</small>
                            </div>
                            <i class="fas fa-arrow-down-right-dots fa-2x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bagian BOM (Jika Ada) -->
            @if($item->is_bom)
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white fw-bold py-3">
                    <i class="fas fa-layer-group me-2"></i> Komponen Material (Resep BOM)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Item Bahan Baku</th>
                                    <th class="text-center">Kebutuhan</th>
                                    <th class="text-end pe-4">Sub-total Hrg Beli</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalBomCost = 0; @endphp
                                @foreach($item->materials as $mat)
                                    @php
                                        $m = \App\Models\Item::find($mat['item_id']);
                                        $subCost = $m ? ($m->harga_beli * $mat['qty']) : 0;
                                        $totalBomCost += $subCost;
                                    @endphp
                                    <tr>
                                        <td class="ps-4">
                                            @if($m)
                                                <a href="{{ route('item.show', $m->id) }}" class="text-decoration-none fw-bold text-primary">
                                                    {{ $m->nama }}
                                                </a>
                                                <div class="small text-muted">SKU: {{ $m->sku }}</div>
                                            @else
                                                <span class="text-danger italic">Item tidak ditemukan (#{{ $mat['item_id'] }})</span>
                                            @endif
                                        </td>
                                        <td class="text-center fw-bold">{{ number_format($mat['qty'], 2) }} {{ $m->satuan ?? '' }}</td>
                                        <td class="text-end pe-4 text-muted">Rp{{ number_format($subCost, 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold border-top-2">
                                <tr>
                                    <td colspan="2" class="ps-4 text-uppercase small text-muted">Total Estimasi Biaya Produksi (Hrg Beli)</td>
                                    <td class="text-end pe-4 text-primary fs-5">Rp{{ number_format($totalBomCost, 0) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Log Histori Transaksi -->
            <div class="card border-0 shadow-sm overflow-hidden">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="fas fa-history me-2 text-secondary"></i> Log Aktivitas Terakhir</span>
                    <span class="badge bg-light text-dark border fw-normal">{{ $history->count() }} Transaksi</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 450px;">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="sticky-top bg-white border-bottom shadow-sm" style="z-index: 5;">
                                <tr>
                                    <th class="ps-4 small text-uppercase text-muted fw-bold">Tanggal & Waktu</th>
                                    <th class="small text-uppercase text-muted fw-bold">Keterangan Aktivitas</th>
                                    <th class="text-end pe-4 small text-uppercase text-muted fw-bold">Qty Perubahan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($history as $h)
                                <tr>
                                    <td class="ps-4 small text-muted">
                                        {{ $h->tanggal_produksi->format('d M Y') }}
                                        <div style="font-size: 0.7rem;">{{ $h->tanggal_produksi->format('H:i') }} WIB</div>
                                    </td>
                                    <td class="small fw-medium">{{ $h->catatan }}</td>
                                    <td class="text-end pe-4 fw-bold {{ $h->jumlah_produksi >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ $h->jumlah_produksi >= 0 ? '+' : '' }}{{ number_format($h->jumlah_produksi, 0) }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">
                                        <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                                        <p class="mb-0 italic">Belum ada histori transaksi untuk item ini.</p>
                                    </td>
                                </tr>
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
        if(confirm('Peringatan: Menghapus item ini akan menghapus seluruh histori transaksi terkait. Lanjutkan?')) {
            document.getElementById('delete-form').submit();
        }
    }
</script>

<style>
    .small-85 { font-size: 0.85rem; }
    .italic { font-style: italic; }
    .table thead th { border-top: 0; }
    .card { border-radius: 12px; }
    .bg-info-subtle { background-color: #e0f7fa !important; }
    .text-primary { color: #895129 !important; } /* Custom Theme Color */
    .bg-primary { background-color: #895129 !important; }
    .btn-warning { background-color: #ffc107; border-color: #ffc107; color: #000; }
</style>
@endsection
