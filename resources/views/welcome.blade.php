@extends('layouts.app')

@section('title', 'Selamat Datang di IMS Toko Roti')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-10">
        <div class="card text-center shadow p-4">
            <h1 class="card-title display-4 text-primary">
                Selamat Datang di 🥖 IMS Toko Roti
            </h1>
            <p class="card-text lead">
                Sistem Manajemen Inventaris Manufaktur Roti Anda.
            </p>
            <hr class="my-4">
            <p class="mb-4">
                Mulai kelola inventaris Anda sekarang.
            </p>
            <div class="d-grid gap-2 col-6 mx-auto">
                <a href="{{ route('bahan-mentah.index') }}" class="btn btn-success btn-lg">
                    Mulai Kelola Bahan Mentah
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
