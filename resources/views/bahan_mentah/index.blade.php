@extends('layouts.app')

@section('title', 'Daftar Bahan Mentah')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4">Inventaris Bahan Mentah</h1>

        <div class="d-flex justify-content-between mb-3">
            <a href="{{ route('bahan-mentah.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Bahan Baru
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Bahan</th>
                            <th>Stok Saat Ini</th>
                            <th>Satuan</th>
                            <th>Stok Minimum</th>
                            <th>Pemasok</th>
                            <th style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bahanMentah as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->nama }}</td>
                            <td class="{{ $item->stok_saat_ini <= $item->stok_minimum ? 'text-danger fw-bold' : '' }}">
                                {{ number_format($item->stok_saat_ini, 2) }}
                            </td>
                            <td>{{ $item->satuan }}</td>
                            <td>{{ number_format($item->stok_minimum, 2) }}</td>
                            <td>{{ $item->pemasok ?? '-' }}</td>
                            <td>
                                <a href="{{ route('bahan-mentah.edit', $item->id) }}" class="btn btn-sm btn-warning me-1">Edit</a>
                                <form action="{{ route('bahan-mentah.destroy', $item->id) }}" method="POST" style="display:inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus bahan ini?')">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">Belum ada data Bahan Mentah.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
