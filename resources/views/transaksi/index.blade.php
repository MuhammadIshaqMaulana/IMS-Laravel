@extends('layouts.app')
@section('title', 'History Log - Sortly Style')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0"><i class="fas fa-history me-2 text-primary"></i>Riwayat Aktivitas</h2>

            <div class="d-flex gap-2">
                <!-- DUAL DROPDOWN SORT SESUAI REQUEST -->
                <div class="btn-group shadow-sm">
                    <select class="form-select form-select-sm fw-bold border-end-0" onchange="location = this.value;" style="width: 160px; border-radius: 8px 0 0 8px;">
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'tanggal']) }}" {{ request('sort') == 'tanggal' ? 'selected' : '' }}>Tanggal</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'tipe']) }}" {{ request('sort') == 'tipe' ? 'selected' : '' }}>Tipe Aksi</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'aksi']) }}" {{ request('sort') == 'aksi' ? 'selected' : '' }}>Aksi</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'user']) }}" {{ request('sort') == 'user' ? 'selected' : '' }}>User</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'sku']) }}" {{ request('sort') == 'sku' ? 'selected' : '' }}>SKU</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'objek']) }}" {{ request('sort') == 'objek' ? 'selected' : '' }}>Nama Objek</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'target']) }}" {{ request('sort') == 'target' ? 'selected' : '' }}>Jml Target</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'perubahan']) }}" {{ request('sort') == 'perubahan' ? 'selected' : '' }}>Perubahan Stok</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'asal']) }}" {{ request('sort') == 'asal' ? 'selected' : '' }}>Folder Asal</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort' => 'tujuan']) }}" {{ request('sort') == 'tujuan' ? 'selected' : '' }}>Folder Tujuan</option>
                    </select>
                    <select class="form-select form-select-sm fw-bold" onchange="location = this.value;" style="width: 110px; border-radius: 0 8px 8px 0;">
                        <option value="{{ request()->fullUrlWithQuery(['order' => 'asc']) }}" {{ request('order') == 'asc' ? 'selected' : '' }}>Ascending</option>
                        <option value="{{ request()->fullUrlWithQuery(['order' => 'desc']) }}" {{ request('order', 'desc') == 'desc' ? 'selected' : '' }}>Descending</option>
                    </select>
                </div>
                <a href="{{ route('transaksi.create') }}" class="btn btn-success btn-sm fw-bold px-3">+ Produksi</a>
            </div>
        </div>

        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width: 1200px; font-size: 0.85rem;">
                    <thead class="bg-light text-muted text-uppercase small fw-bold">
                        <tr>
                            <th class="ps-4" style="width: 150px;">Tanggal</th>
                            <th style="width: 100px;">Tipe</th>
                            <th>Aksi (Detail)</th>
                            <th>User</th>
                            <th>SKU</th>
                            <th>Nama Objek</th>
                            <th class="text-center">Target</th>
                            <th class="text-center">Stok +/-</th>
                            <th>Asal</th>
                            <th class="pe-4">Tujuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transaksis as $t)
                        <tr>
                            <td class="ps-4 text-muted">
                                {{ $t->created_at->format('d/m/Y') }}
                                <div style="font-size: 0.7rem;">{{ $t->created_at->format('H:i') }} WIB</div>
                            </td>
                            <td>
                                @php
                                    $badgeColor = match($t->tipe_aksi) {
                                        'Pindah' => 'bg-info',
                                        'Import' => 'bg-success',
                                        'Tambah' => 'bg-primary',
                                        'Update Stok' => 'bg-warning text-dark',
                                        'Hapus' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                @endphp
                                <span class="badge {{ $badgeColor }} shadow-sm" style="font-size: 0.65rem;">{{ $t->tipe_aksi }}</span>
                            </td>
                            <td class="text-wrap" style="max-width: 300px;">
                                {!! $t->parsed_catatan !!}
                            </td>
                            <td class="fw-bold">{{ $t->parsed_user }}</td>
                            <td><code class="small">{{ $t->itemProduksi->sku ?? '-' }}</code></td>
                            <td>
                                @if($t->itemProduksi)
                                    <span class="fw-bold">{{ $t->itemProduksi->nama }}</span>
                                @else
                                    <span class="text-muted italic small">Folder/Bulk</span>
                                @endif
                            </td>
                            <td class="text-center fw-bold">{{ $t->target_count }}</td>
                            <td class="text-center">
                                @php $stok = $t->perubahan_stok; @endphp
                                @if($stok != '-')
                                    <span class="badge {{ str_contains($stok, '+') ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} border px-2">
                                        {{ $stok }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="small">{{ $t->folder_asal }}</td>
                            <td class="pe-4 small">{{ $t->folder_tujuan }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                                <p>Belum ada data transaksi yang tercatat.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 d-flex justify-content-center">
            {{ $transaksis->links() }}
        </div>
    </div>
</div>

<style>
    .table th { border-top: 0; padding: 12px 8px !important; }
    .table td { padding: 12px 8px !important; }
    .bg-success-subtle { background-color: #d1e7dd !important; }
    .bg-danger-subtle { background-color: #f8d7da !important; }
    .bg-info-subtle { background-color: #cff4fc !important; }
</style>
@endsection
