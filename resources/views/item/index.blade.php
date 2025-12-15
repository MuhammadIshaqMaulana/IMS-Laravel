@extends('layouts.app')

@section('title', 'Daftar Semua Item')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Daftar Semua Item ({{ $items->total() }})</h1>

        <div class="d-flex justify-content-between mb-3">
            <a href="{{ route('item.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Item Baru
            </a>
            <!-- Bulk Action UI akan ditambahkan di Fase 8 -->
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Nama Item</th>
                            <th>Jenis</th>
                            <th>Stok</th>
                            <th>Min. Level</th>
                            <th>Harga (@)</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->nama }}</td>
                            <td><span class="badge bg-{{ $item->jenis_item == 'bahan_mentah' ? 'warning' : ($item->jenis_item == 'produk_jadi' ? 'success' : 'secondary') }}">{{ strtoupper(str_replace('_', ' ', $item->jenis_item)) }}</span></td>
                            <td>{{ number_format($item->stok_saat_ini, 2) }} {{ $item->satuan }}</td>
                            <td>{{ number_format($item->stok_minimum, 2) }} {{ $item->satuan }}</td>
                            <td>Rp{{ number_format($item->harga_jual, 0, ',', '.') }}</td>
                            <td>
                                <a href="{{ route('item.edit', $item->id) }}" class="btn btn-sm btn-info me-1"><i class="fas fa-edit"></i> Edit</a>
                                <form action="{{ route('item.destroy', $item->id) }}" method="POST" style="display:inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus item ini? Semua data terkait akan terpengaruh.')"><i class="fas fa-trash"></i> Hapus</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">Belum ada item terdaftar.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $items->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
