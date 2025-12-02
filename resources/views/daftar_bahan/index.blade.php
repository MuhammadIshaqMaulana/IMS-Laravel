@extends('layouts.app')

@section('title', 'Daftar Resep (Bill of Materials)')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Daftar Resep Produk Jadi</h1>

        <div class="d-flex justify-content-between mb-3">
            <a href="{{ route('daftar-bahan.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Bahan ke Resep
            </a>
        </div>

        @if ($produkJadi->isEmpty())
            <div class="alert alert-info text-center">
                Belum ada Produk Jadi yang terdaftar atau belum memiliki Resep.
                Silakan buat Produk Jadi terlebih dahulu.
            </div>
        @else
            <div class="accordion" id="accordionResep">
                @foreach ($produkJadi as $produk)
                <div class="accordion-item mb-3 card shadow-sm">
                    <h2 class="accordion-header" id="heading{{ $produk->id }}">
                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $produk->id }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="collapse{{ $produk->id }}">
                            <strong>{{ $produk->nama }} (SKU: {{ $produk->sku }})</strong> - Total {{ $produk->resep->count() }} Bahan
                        </button>
                    </h2>
                    <div id="collapse{{ $produk->id }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="heading{{ $produk->id }}" data-bs-parent="#accordionResep">
                        <div class="accordion-body">
                            @if ($produk->resep->isEmpty())
                                <p class="text-muted">Resep untuk produk ini masih kosong.</p>
                            @else
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Bahan Mentah</th>
                                            <th>Jumlah Digunakan</th>
                                            <th style="width: 150px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($produk->resep as $resep)
                                        <tr>
                                            <td>{{ $resep->bahanMentah->nama }} (Satuan: {{ $resep->bahanMentah->satuan }})</td>
                                            <td>{{ number_format($resep->jumlah_digunakan, 3) }}</td>
                                            <td>
                                                <a href="{{ route('daftar-bahan.edit', $resep->id) }}" class="btn btn-sm btn-info me-1" title="Edit Kuantitas">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <form action="{{ route('daftar-bahan.destroy', $resep->id) }}" method="POST" style="display:inline-block;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus Bahan dari Resep" onclick="return confirm('Yakin ingin menghapus bahan ini dari resep?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
