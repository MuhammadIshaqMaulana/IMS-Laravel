@extends('layouts.app')

@section('title', 'Daftar Produk Jadi')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Daftar Produk Jadi (Siap Jual)</h1>

        <div class="d-flex justify-content-between mb-3">
            <a href="{{ route('produk-jadi.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Produk Baru
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Produk</th>
                            <th>SKU</th>
                            <th>Harga Jual</th>
                            <th>Stok di Tangan</th>
                            <th>Status</th>
                            <th style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($produkJadi as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->nama }}</td>
                            <td>{{ $item->sku }}</td>
                            <td>Rp {{ number_format($item->harga_jual, 0, ',', '.') }}</td>
                            <td>{{ number_format($item->stok_di_tangan, 0, ',', '.') }}</td>
                            <td>
                                <span class="badge bg-{{ $item->aktif ? 'success' : 'secondary' }}">
                                    {{ $item->aktif ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('produk-jadi.edit', $item->id) }}" class="btn btn-sm btn-warning me-1">Edit</a>
                                <form action="{{ route('produk-jadi.destroy', $item->id) }}" method="POST" style="display:inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini? Semua data resep terkait akan hilang.')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">Belum ada data Produk Jadi.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
