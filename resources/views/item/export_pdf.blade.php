<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Inventaris - My IMS</title>
    <!-- Gunakan CSS minimal untuk cetak -->
    <style>
        body { font-family: sans-serif; margin: 20px; }
        h1 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; font-size: 10px; vertical-align: top; }
        th { background-color: #f2f2f2; }
        .image-placeholder { width: 50px; height: 50px; background-color: #eee; text-align: center; line-height: 50px; font-size: 8px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

    <h1>Laporan Inventaris Universal My IMS</h1>
    <p>Tanggal Ekspor: {{ now()->format('d M Y H:i:s') }}</p>

    <table>
        <thead>
            <tr>
                <th style="width: 50px;">Gambar</th>
                <th>Nama Item / Varian</th>
                <th>SKU / ID</th>
                <th>Stok</th>
                <th>Min. Level</th>
                <th>Harga Jual (Rp)</th>
                <th>Tags</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
            <tr>
                <td>
                    @if ($item->image_link)
                        <!-- Link gambar diisi dengan placeholder karena tidak dapat dimuat di lingkungan Canvas/Print -->
                        <div class="image-placeholder">IMG</div>
                    @else
                        <div class="image-placeholder">N/A</div>
                    @endif
                </td>
                <td>
                    <strong>{{ $item->nama }}</strong>
                    @if ($item->materials)
                         <span style="font-weight: bold; color: blue;">(BOM/KIT)</span>
                    @endif
                </td>
                <td>{{ $item->sku ?? $item->id }}</td>
                <td>
                    @if ($item->materials)
                         Kapasitas: {{ number_format($item->calculated_stock, 0) }} {{ $item->satuan }}
                    @else
                        {{ number_format($item->stok_saat_ini, 2) }} {{ $item->satuan }}
                    @endif
                </td>
                <td>{{ number_format($item->stok_minimum, 2) }}</td>
                <td>{{ number_format($item->harga_jual, 0, ',', '.') }}</td>
                <td>{{ is_array($item->tags) ? implode(', ', $item->tags) : '' }}</td>
                <td>{{ Str::limit($item->note, 50) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <script>
        // Memicu dialog cetak (Print) setelah tampilan dimuat
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
