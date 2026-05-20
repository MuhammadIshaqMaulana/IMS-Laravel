@extends('layouts.app')

@section('title', 'Laporan Stok Kritis')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4 text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Stok Item Kritis</h1>
        <p class="lead">Daftar Item (Material/Aset) yang stoknya berada di batas minimum atau di bawah batas minimum dan memerlukan pemesanan ulang.</p>
    </div>

    @if ($bahanKritis->isEmpty())
        <div class="col-12">
            <div class="alert alert-success text-center mt-3">
                <i class="fas fa-check-circle me-2"></i> Semua item Anda berada di atas batas stok minimum!
            </div>
        </div>
    @else
        <div class="col-12 mb-4">
            <div class="alert alert-warning">
                Total **{{ $bahanKritis->count() }}** item berada pada atau di bawah batas kritis dari {{ $totalBahan }} total item material.
            </div>
        </div>

        @foreach ($bahanKritis as $bahan)
        <div class="col-md-6 mb-4">
            <div class="card shadow border-danger h-100">
                <div class="card-header bg-danger text-white">
                    <strong class="h5">{{ $bahan->nama }}</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <p class="mb-1"><strong>Stok Saat Ini:</strong></p>
                            <h4 class="text-danger">{{ number_format($bahan->stok_saat_ini, 2) }} {{ $bahan->satuan }}</h4>
                        </div>
                        <div class="col-sm-6">
                            <p class="mb-1"><strong>Batas Minimum (Reorder Point):</strong></p>
                            <h4 class="text-secondary">{{ number_format($bahan->stok_minimum, 2) }} {{ $bahan->satuan }}</h4>
                        </div>
                    </div>
                    <hr>
                    <p class="mb-1"><strong>Pemasok:</strong> {{ $bahan->pemasok ?? 'Tidak Diketahui' }}</p>
                    <!-- FIXED: Mengarah ke item.edit -->
                    <a href="{{ route('item.edit', $bahan->id) }}" class="btn btn-sm btn-outline-danger mt-3 float-end">
                        <i class="fas fa-truck-loading me-1"></i> Pesan Ulang / Edit Data
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    @endif
</div>
@endsection
