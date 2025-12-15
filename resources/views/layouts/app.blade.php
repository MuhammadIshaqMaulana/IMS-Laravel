<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My IMS | @yield('title', 'Manajemen Inventaris')</title>
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome (untuk ikon) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" xintegrity="sha512-SnH5WK+bZxgPHs44uWIX+LLMDJ8Wn1s5v5k8X/K3+g6T2P1d5o3m2yS2G0u5T5z5M5i5t5j5g5L5O5g5g5w5j5s5k5i5m5l5n5o5p5q5r5s5t5u5v5w5x5y5z5A5B5C5D5E5F5G5H5I5J5K5L5M5N5O5P5Q5R5S5T5U5V5W5X5Y5Z" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Custom Style Sortly (Navigasi Samping) -->
    <style>
        body {
            background-color: #f8f9fa;
            display: flex; /* Untuk layout sidebar-content */
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: #895129; /* Warna Cokelat Gelap sesuai permintaan */
            color: white;
            padding: 15px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 5px rgba(0,0,0,.1);
        }
        .sidebar .nav-link {
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: background-color 0.2s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #6a3e1f; /* Warna sedikit lebih gelap saat hover/aktif */
            font-weight: bold;
        }
        .content {
            flex-grow: 1;
            padding: 20px;
        }
        .user-info {
            margin-top: auto; /* Mendorong ke bawah */
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigasi -->
    @auth
    <div class="sidebar">
        <!-- Logo dan Judul Aplikasi -->
        <div class="text-center mb-4">
            <a href="{{ route('dashboard') }}" class="text-white text-decoration-none">
                <span style="font-size: 2.5rem;">🥖</span>
                <h3 class="mt-2 mb-0">My IMS</h3>
            </a>
        </div>

        <nav class="nav flex-column">
            <!-- Navigasi Dashboard -->
            <a class="nav-link {{ Route::currentRouteName() == 'dashboard' ? 'active' : '' }}"
               href="{{ route('dashboard') }}">
               <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>

            <!-- Navigasi Item (ALL) - FIXED -->
            <!-- Menggunakan str_contains untuk menangani item.index, item.create, item.edit, dll. -->
            <a class="nav-link {{ str_contains(Route::currentRouteName(), 'item.') ? 'active' : '' }}"
               href="{{ route('item.index') }}">
               <i class="fas fa-boxes me-2"></i> Items (All)
            </a>

            <!-- Navigasi Resep (BOM) -->
            <a class="nav-link {{ Route::currentRouteName() == 'daftar-bahan.index' ? 'active' : '' }}"
               href="{{ route('daftar-bahan.index') }}">
               <i class="fas fa-receipt me-2"></i> Resep (BOM)
            </a>

            <!-- Navigasi Transaksi -->
            <a class="nav-link {{ Route::currentRouteName() == 'transaksi.index' ? 'active' : '' }}"
               href="{{ route('transaksi.index') }}">
               <i class="fas fa-cogs me-2"></i> Transaksi
            </a>

            <!-- Navigasi Stok Kritis -->
            <a class="nav-link {{ Route::currentRouteName() == 'laporan.stok-minimum' ? 'active' : '' }}"
               href="{{ route('laporan.stok-minimum') }}">
               <i class="fas fa-bell me-2"></i> Stok Kritis
            </a>

            <!-- Navigasi API Python -->
             <a class="nav-link {{ Route::currentRouteName() == 'api-python.index' ? 'active' : '' }}"
               href="{{ route('api-python.index') }}">
               <i class="fas fa-terminal me-2"></i> Uji API Python
            </a>
        </nav>

        <!-- Informasi Akun dan Logout (Dipindahkan ke Bawah) -->
        <div class="user-info">
             <div class="d-flex align-items-center mb-2">
                <i class="fas fa-user-circle me-2" style="font-size: 1.5rem;"></i>
                <div style="font-size: 0.9rem;">
                    <div>{{ Auth::user()->name }}</div>
                    <div style="opacity: 0.7;">Admin</div>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-light w-100 mt-2">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </button>
            </form>
        </div>
    </div>
    @endauth

    <!-- Konten Utama -->
    <div class="content">
        @guest
            <!-- Tampilan untuk pengguna yang belum login -->
            <div class="container py-5">
                @yield('content')
            </div>
        @else
            <div class="container-fluid">
                <!-- Pesan Sukses/Error Global -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @yield('content')
            </div>
        @endif
    </main>

    <!-- Bootstrap JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
