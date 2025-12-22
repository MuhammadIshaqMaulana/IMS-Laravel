<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My IMS | @yield('title')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        html, body { height: 100%; overflow: hidden; margin: 0; background-color: #fff; }
        #app-wrapper { display: flex; height: 100vh; width: 100vw; }

        /* 1. Main Icon Nav (Fixed) */
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
        #main-nav .nav-link { color: rgba(255,255,255,0.6); font-size: 1.4rem; margin-bottom: 30px; transition: 0.2s; }
        #main-nav .nav-link:hover, #main-nav .nav-link.active { color: white; transform: scale(1.1); }

        /* 2. Folder Sidebar (Resizable) */
        #folder-sidebar {
            width: 260px;
            min-width: 150px;
            max-width: 600px;
            background: #fcfcfc;
            border-right: 1px solid #eee;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex-shrink: 0;
        }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #f0f0f0; }
        .folder-tree { flex-grow: 1; overflow-y: auto; padding: 10px; }
        .folder-item {
            display: block;
            padding: 8px 12px;
            color: #444;
            text-decoration: none;
            border-radius: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .folder-item:hover { background: #f0f0f0; color: #895129; }
        .folder-item.active { background: #fdf5ef; color: #895129; font-weight: bold; }

        /* 3. Resizer */
        #resizer {
            width: 4px;
            cursor: col-resize;
            background: transparent;
            transition: background 0.3s;
            flex-shrink: 0;
        }
        #resizer:hover, #resizer.resizing { background: #895129; }

        /* 4. Content Area */
        #content-area {
            flex-grow: 1;
            overflow-y: auto;
            background: #fff;
            padding: 25px;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #ddd; border-radius: 10px; }
    </style>
</head>
<body>
    <div id="app-wrapper">
        @auth
        <nav id="main-nav">
            <a href="{{ route('dashboard') }}" class="nav-link {{ Route::is('dashboard') ? 'active' : '' }}"><i class="fas fa-home"></i></a>
            <a href="{{ route('item.index') }}" class="nav-link {{ Route::is('item.*') ? 'active' : '' }}"><i class="fas fa-boxes"></i></a>
            <a href="{{ route('transaksi.index') }}" class="nav-link {{ Route::is('transaksi.*') ? 'active' : '' }}"><i class="fas fa-history"></i></a>
            <a href="{{ route('laporan.stok-minimum') }}" class="nav-link"><i class="fas fa-bell"></i></a>
            <div class="mt-auto">
                <form action="{{ route('logout') }}" method="POST">@csrf
                    <button type="submit" class="btn btn-link nav-link"><i class="fas fa-sign-out-alt"></i></button>
                </form>
            </div>
        </nav>

        <div id="folder-sidebar">
            <div class="sidebar-header"><h6 class="fw-bold m-0 text-muted small">FOLDERS</h6></div>
            <div class="folder-tree">
                <a href="{{ route('item.index') }}" class="folder-item {{ !request('folder_id') ? 'active' : '' }}">
                    <i class="fas fa-hdd me-2 opacity-50"></i> Root
                </a>
                @php $sidebarFolders = \App\Models\Item::where('tags', 'like', '%"folder"%')->orderBy('nama')->get(); @endphp
                @foreach($sidebarFolders as $sf)
                    <a href="{{ route('item.index', ['folder_id' => $sf->id]) }}"
                       class="folder-item {{ request('folder_id') == $sf->id ? 'active' : '' }}">
                        <i class="fas fa-folder me-2 text-warning"></i> {{ $sf->nama }}
                    </a>
                @endforeach
            </div>
        </div>
        <div id="resizer"></div>
        @endauth

        <main id="content-area">
            @if(session('success'))
                <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
            @endif
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('folder-sidebar');
        const resizer = document.getElementById('resizer');
        let isResizing = false;

        resizer?.addEventListener('mousedown', (e) => {
            isResizing = true;
            resizer.classList.add('resizing');
        });

        document.addEventListener('mousemove', (e) => {
            if (!isResizing) return;
            const newWidth = e.clientX - 80;
            if (newWidth > 150 && newWidth < 600) {
                sidebar.style.width = `${newWidth}px`;
            }
        });

        document.addEventListener('mouseup', () => {
            isResizing = false;
            resizer?.classList.remove('resizing');
        });
    </script>
</body>
</html>
