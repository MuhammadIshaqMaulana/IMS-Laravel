<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 8px; margin: 0; padding: 10px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 0.5px solid #ccc; padding: 4px; word-wrap: break-word; vertical-align: top; }
        th { background-color: #f8f9fa; font-weight: bold; text-align: left; text-transform: lowercase; }
        .img-preview { width: 25px; height: 25px; object-fit: cover; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 1px 3px; background: #eee; border-radius: 2px; font-size: 7px; }
    </style>
</head>
<body>
    <h3 style="text-align: center;">LAPORAN INVENTARIS SISTEM</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 30px;">image</th>
                <th style="width: 25px;">nomor</th>
                <th>nama</th>
                <th style="width: 35px;">satuan</th>
                <th style="width: 45px;" class="text-right">stok_saat_ini</th>
                <th style="width: 45px;" class="text-right">stok_minimum</th>
                <th style="width: 55px;" class="text-right">harga_jual</th>
                <th style="width: 55px;" class="text-right">harga_beli</th>
                <th>note</th>
                <th>materials</th>
                <th>tags</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td style="text-align: center;">
                    @if($item->image_link) <img src="{{ $item->image_link }}" class="img-preview"> @else - @endif
                </td>
                <td>{{ $loop->iteration }}</td>
                <td><strong>{{ $item->nama }}</strong><br><span style="color:#888;">{{ $item->sku }}</span></td>
                <td>{{ $item->satuan }}</td>
                <td class="text-right">{{ number_format($item->calculated_stock, 0) }}</td>
                <td class="text-right">{{ number_format($item->stok_minimum, 0) }}</td>
                <td class="text-right">{{ number_format($item->harga_jual, 0) }}</td>
                <td class="text-right">{{ number_format($item->harga_beli, 0) }}</td>
                <td style="font-size: 7px;">{{ $item->note }}</td>
                <td style="font-size: 7px;">
                    @if($item->is_bom && is_array($item->materials))
                        @foreach($item->materials as $m)
                            {{ $m['item_id'] }}({{ $m['qty'] }}){{ !$loop->last ? ',' : '' }}
                        @endforeach
                    @else - @endif
                </td>
                <td>{{ $item->tags ? implode(', ', $item->tags) : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
