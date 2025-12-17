@extends('layouts.app')

@section('title', 'Dashboard - My IMS')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Dashboard (Overview)</h1>
    </div>

    <!-- Inventory Summary Cards -->
    <div class="col-md-3 mb-4">
        <div class="card bg-primary text-white shadow-sm">
            <div class="card-body">
                <i class="fas fa-boxes fa-2x float-end"></i>
                <h5 class="card-title">Total Item (Entitas)</h5>
                <p class="card-text h3">{{ $totalItems }}</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-info text-white shadow-sm">
            <div class="card-body">
                <i class="fas fa-calculator fa-2x float-end"></i>
                <h5 class="card-title">Total Kuantitas (Material)</h5>
                <p class="card-text h3">{{ number_format($totalQuantity, 2) }} Unit</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-success text-white shadow-sm">
            <div class="card-body">
                <i class="fas fa-dollar-sign fa-2x float-end"></i>
                <h5 class="card-title">Total Nilai (Inventaris)</h5>
                <p class="card-text h3">Rp{{ number_format($totalValue, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card bg-secondary text-white shadow-sm">
            <div class="card-body">
                <i class="fas fa-folder fa-2x float-end"></i>
                <h5 class="card-title">BOM/Kit Total</h5>
                <!-- DIUBAH: Menampilkan total BOM/Kit -->
                <p class="card-text h3">{{ $totalFolders }}</p>
                <small>(Dianggap sebagai Item yang memiliki Material)</small>
            </div>
        </div>
    </div>

    <!-- Items That Need Restocking -->
    <div class="col-md-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-exclamation-triangle me-2"></i> Items Yang Perlu Restock
            </div>
            <div class="card-body">
                @if($itemsKritis->isEmpty())
                    <div class="alert alert-success text-center">
                        Semua stok material Anda berada di atas batas minimum.
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($itemsKritis as $item)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-danger">{{ $item->nama }}</span>
                            <span class="badge bg-danger">
                                Stok: {{ number_format($item->stok_saat_ini, 2) }} / Min: {{ number_format($item->stok_minimum, 2) }}
                            </span>
                        </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('laporan.stok-minimum') }}" class="btn btn-sm btn-outline-danger w-100 mt-3">Lihat Semua Item Kritis</a>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-md-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-history me-2"></i> Aktivitas Terbaru (Produksi/Perakitan)
            </div>
            <div class="card-body">
                 <ul class="list-group list-group-flush">
                    @forelse ($recentActivity as $activity)
                    <li class="list-group-item">
                        <small class="text-muted">{{ $activity->created_at->diffForHumans() }}</small><br>
                        <strong>{{ Auth::user()->name }}</strong>
                        <span class="text-success">merakit</span>
                        <strong>{{ number_format($activity->jumlah_produksi, 0) }} unit</strong>
                        Item
                        <span class="text-primary">{{ $activity->itemProduksi->nama ?? 'Item Dihapus' }}</span>.
                    </li>
                    @empty
                        <div class="alert alert-info text-center">Belum ada transaksi produksi/perakitan yang dicatat.</div>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
