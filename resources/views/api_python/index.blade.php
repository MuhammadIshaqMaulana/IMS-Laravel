@extends('layouts.app')

@section('title', 'Uji Coba Integrasi API Python')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="mb-4"><i class="fas fa-code me-2"></i> Integrasi & Uji Coba API Python</h1>
    </div>

    <!-- Panel Status API -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">Status API Python</div>
            <div class="card-body">
                @php
                    // Variabel $status dikirim dari ApiPythonController::index()
                    $badgeClass = $status['online'] ? 'bg-success' : 'bg-danger';
                    $iconClass = $status['online'] ? 'fas fa-check-circle' : 'fas fa-times-circle';
                @endphp
                <p class="fs-5">Koneksi Status:</p>
                <span class="badge {{ $badgeClass }} fs-6">
                    <i class="{{ $iconClass }} me-1"></i> {{ $status['online'] ? 'ONLINE' : 'OFFLINE' }}
                </span>
                <p class="mt-3 text-muted">Pesan dari API: {{ $status['status_message'] }}</p>
                <hr>
                <small>Pastikan Anda menjalankan Flask API (`python api.py` di folder API) di port 8000 agar ini berfungsi.</small>
            </div>
        </div>
    </div>

    <!-- Panel Uji Validasi SKU -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">Uji Validasi SKU (POST Request)</div>
            <div class="card-body">
                <!-- Form action menunjuk ke ApiPythonController::validasiSku() -->
                <form action="{{ route('api-python.validasi-sku') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="sku_input" class="form-label">Masukkan SKU:</label>
                        <input type="text" class="form-control" id="sku_input" name="sku_input" placeholder="Cth: ROT-TEP-1234">
                        <small class="text-muted">Format yang diharapkan: AAA-BBB-1234</small>
                    </div>
                    <button type="submit" class="btn btn-info text-white">Validasi ke Python</button>
                </form>

                <hr>

                <!-- Hasil Pengujian yang dikirim dari controller (menggunakan session flash) -->
                @if(session('api_result'))
                    @php $result = session('api_result'); @endphp
                    <div class="alert alert-{{ $result['is_valid'] ? 'success' : 'warning' }} mt-3">
                        <strong>Hasil Validasi Python:</strong>
                        <p class="mb-1">SKU: <code>{{ $result['sku'] }}</code></p>
                        <p class="mb-1">Valid: {{ $result['is_valid'] ? 'YA' : 'TIDAK' }}</p>
                        <p class="mb-0">Pesan: {{ $result['pesan'] }}</p>
                    </div>
                @elseif(session('api_error'))
                    <div class="alert alert-danger mt-3">
                        <strong>Kesalahan Koneksi/API:</strong>
                        <p class="mb-0">{{ session('api_error') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
