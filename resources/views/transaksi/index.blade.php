@extends('layouts.app')

@section('title', 'Daftar Transaksi Produksi')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Daftar Transaksi Produksi</h1>

        <div class="d-flex justify-content-between mb-3">
            <!-- Rute diubah dari transaksi-produksi.create menjadi transaksi.create -->
            <a href="{{ route('transaksi.create') }}" class="btn btn-success">
                <i class="fas fa-plus"></i> Catat Produksi Baru
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal Produksi</th>
                            <th>Produk Jadi</th>
                            <th>Jumlah (Unit)</th>
                            <th>Dicatat Pada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transaksis as $transaksi)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $transaksi->tanggal_produksi->format('d M Y H:i') }}</td>
                            <td>{{ $transaksi->produkJadi->nama ?? 'Produk Dihapus' }} ({{ $transaksi->produkJadi->sku ?? '-' }})</td>
                            <td class="fw-bold text-success">{{ number_format($transaksi->jumlah_produksi, 0, ',', '.') }}</td>
                            <td>{{ $transaksi->created_at->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center">Belum ada Transaksi Produksi yang dicatat.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-3">
                    {{-- Asumsikan pagination links() sudah tersedia --}}
                    {{ $transaksis->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
