<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My IMS | @yield('title')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        html, body { height: 100%; overflow: hidden; }
        #app-wrapper { display: flex; height: 100vh; width: 100vw; }

        /* Main Navigation */
        #main-nav {
            width: 80px;
            background: #4a2c16;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
            z-index: 1000;
        }
        #main-nav .nav-link { color: rgba(255,255,255,0.6); font-size: 1.2rem; margin-bottom: 25px; transition: 0.2s; }
        #main-nav .nav-link:hover, #main-nav .nav-link.active { color: white; transform: scale(1.1); }

        /* Content Area */
        #content-area { flex-grow: 1; overflow-y: auto; background: #f8f9fa; padding: 30px; }

        /* Persiapan Folder Nav Fase 14 */
        #folder-sidebar {
            width: 0px; /* Nanti di Fase 14 diubah jadi resizable */
            background: white;
            border-right: 1px solid #dee2e6;
            overflow: hidden;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div id="app-wrapper">
        <!-- Main Nav (Icon Only) -->
        <nav id="main-nav">
            <a href="{{ route('dashboard') }}" class="nav-link {{ Route::is('dashboard') ? 'active' : '' }}"><i class="fas fa-home"></i></a>
            <a href="{{ route('item.index') }}" class="nav-link {{ Route::is('item.*') ? 'active' : '' }}"><i class="fas fa-boxes"></i></a>
            <a href="{{ route('transaksi.index') }}" class="nav-link {{ Route::is('transaksi.*') ? 'active' : '' }}"><i class="fas fa-exchange-alt"></i></a>
            <a href="{{ route('laporan.stok-minimum') }}" class="nav-link"><i class="fas fa-bell"></i></a>

            <div class="mt-auto">
                <form action="{{ route('logout') }}" method="POST">@csrf
                    <button type="submit" class="btn btn-link nav-link"><i class="fas fa-sign-out-alt"></i></button>
                </form>
            </div>
        </nav>

        <!-- Placeholder Folder Sidebar (Fase 14) -->
        <div id="folder-sidebar"></div>

        <!-- Scrollable Content -->
        <main id="content-area">
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
