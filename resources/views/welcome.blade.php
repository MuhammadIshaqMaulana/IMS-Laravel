@extends('layouts.app')

@section('title', 'Selamat Datang di IMS Toko Roti')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-10">
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card text-center shadow p-4">
            <h1 class="card-title display-4 text-primary">
                Selamat Datang di 🥖 My IMS
            </h1>
            <p class="card-text lead">
                Sistem Manajemen Inventaris Manufaktur Universal.
            </p>
            <hr class="my-4">

            @guest
                <p class="mb-3">
                    Akses ke sistem memerlukan otentikasi Admin.
                </p>
                <div class="d-grid gap-2 col-6 mx-auto">
                    <!-- Tombol Login (menuju Google Auth Controller) -->
                    <a href="{{ route('login.google') }}" class="btn btn-danger btn-lg">
                        <i class="fab fa-google me-2"></i> Login dengan Google (Admin)
                    </a>
                </div>
            @else
                <p class="mb-3">
                    Anda sudah login sebagai **{{ Auth::user()->name }}**.
                </p>
                <div class="d-grid gap-2 col-6 mx-auto">
                    <!-- Tombol Akses Dashboard (jika sudah login) -->
                    <a href="{{ route('dashboard') }}" class="btn btn-success btn-lg">
                        <i class="fas fa-home me-2"></i> Akses Dashboard
                    </a>
                </div>
            @endguest
        </div>
    </div>
</div>
@endsection
