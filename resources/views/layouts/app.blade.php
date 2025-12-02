<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS Roti | @yield('title', 'Manajemen Inventaris')</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome (untuk ikon) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" xintegrity="sha512-SnH5WK+bZxgPHs44uWIX+LLMDJ8Wn1s5v5k8X/K3+g6T2P1d5o3m2yS2G0u5T5z5M5i5t5j5g5L5O5g5g5w5j5s5k5i5m5l5n5o5p5q5r5s5t5u5v5w5x5y5z5A5B5C5D5E5F5G5H5I5J5K5L5M5N5O5P5Q5R5S5T5U5V5W5X5Y5Z" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Custom Style Sederhana -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #e3f2fd; /* Warna biru muda khas toko roti */
            box-shadow: 0 2px 4px rgba(0,0,0,.05);
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0,0,0,.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="{{ url('/') }}">
                🥖 IMS Toko Roti
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">

                    <!-- Navigasi Bahan Mentah -->
                    <li class="nav-item">
                        <a class="nav-link {{ Route::currentRouteName() == 'bahan-mentah.index' ? 'active fw-bold' : '' }}"
                           href="{{ route('bahan-mentah.index') }}">
                           <i class="fas fa-wheat-awn me-1"></i> Bahan Mentah
                        </a>
                    </li>

                    <!-- Navigasi Produk Jadi -->
                    <li class="nav-item">
                        <a class="nav-link {{ Route::currentRouteName() == 'produk-jadi.index' ? 'active fw-bold' : '' }}"
                           href="{{ route('produk-jadi.index') }}">
                           <i class="fas fa-bread-slice me-1"></i> Produk Jadi
                        </a>
                    </li>

                    <!-- Navigasi Daftar Bahan/Resep -->
                    <li class="nav-item">
                        <a class="nav-link {{ Route::currentRouteName() == 'daftar-bahan.index' ? 'active fw-bold' : '' }}"
                           href="{{ route('daftar-bahan.index') }}">
                           <i class="fas fa-receipt me-1"></i> Resep (BOM)
                        </a>
                    </li>

                    <!-- Navigasi Transaksi (Produksi) -->
                    <li class="nav-item">
                        <a class="nav-link {{ Route::currentRouteName() == 'transaksi.index' ? 'active fw-bold' : '' }}"
                           href="{{ route('transaksi.index') }}">
                           <i class="fas fa-cogs me-1"></i> Transaksi
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="py-4">
        <div class="container">
            <!-- Pesan Sukses Global (Jika ada) -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Konten halaman spesifik akan dimuat di sini -->
            @yield('content')
        </div>
    </main>

    <!-- Bootstrap JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
