<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Pemakaian Barang</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 landscape; margin: 9mm; }
        body { margin: 0; color: #111827; font-family: Arial, sans-serif; font-size: 9px; }
        .no-print { margin-bottom: 8px; padding: 7px 11px; font-weight: 700; }
        .title { border: 1px solid #64748b; background: #d9ead3; padding: 8px; text-align: center; }
        .title h1 { margin: 0; font-size: 16px; text-transform: uppercase; }
        .meta { display: grid; grid-template-columns: 140px 1fr; border: 1px solid #64748b; border-top: 0; margin-bottom: 8px; }
        .meta div { padding: 4px 6px; border-bottom: 1px dotted #94a3b8; }
        h2 { margin: 10px 0 5px; font-size: 11px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #64748b; padding: 4px 5px; vertical-align: top; }
        th { background: #dbe4f0; text-align: center; text-transform: uppercase; }
        td.num { text-align: right; white-space: nowrap; }
        td.center { text-align: center; }
        tfoot td { background: #ecfdf5; font-weight: 700; }
        .note { margin-top: 7px; color: #475569; font-size: 8px; }
        @media print { .no-print { display: none; } thead { display: table-header-group; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Cetak / Simpan PDF</button>
    <div class="title"><h1>Laporan Pemakaian Barang untuk Progress Pembangunan</h1></div>
    <div class="meta">
        <div>Perumahan</div><div>: {{ $report['scope']['project'] }}</div>
        <div>Unit</div><div>: {{ $report['scope']['unit'] }}{{ $report['scope']['unit_type'] ? ' (Tipe '.$report['scope']['unit_type'].')' : '' }}</div>
        <div>Periode</div><div>: {{ $report['period']['label'] }}</div>
        <div>Ringkasan</div><div>: {{ $report['totals']['transactions'] }} transaksi, {{ $report['totals']['materials'] }} jenis material, {{ $report['totals']['item_lines'] }} baris barang</div>
        <div>Dicetak</div><div>: {{ $printedAt }}</div>
    </div>

    <h2>Ringkasan per Barang</h2>
    <table>
        <thead><tr><th>No.</th><th>Kode</th><th>Nama Barang</th><th>Jenis / Merek</th><th>Jumlah</th><th>Harga HPP</th><th>Estimasi Nilai</th><th>Transaksi</th></tr></thead>
        <tbody>
            @forelse($report['summary'] as $row)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td><td>{{ $row['material_code'] }}</td><td>{{ $row['material'] }}</td>
                    <td>{{ collect([$row['material_type'], $row['brand']])->filter()->join(' / ') ?: '-' }}</td>
                    <td class="num">{{ number_format($row['quantity'], 2, ',', '.') }} {{ $row['unit_name'] }}</td>
                    <td class="num">Rp {{ number_format($row['unit_price'], 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td><td class="center">{{ $row['transaction_count'] }}</td>
                </tr>
            @empty
                <tr><td class="center" colspan="8">Belum ada pemakaian barang pada filter ini.</td></tr>
            @endforelse
        </tbody>
        <tfoot><tr><td colspan="6">Total estimasi nilai HPP</td><td class="num">Rp {{ number_format($report['totals']['amount'], 0, ',', '.') }}</td><td></td></tr></tfoot>
    </table>

    <h2>Rincian Pemakaian pada Progress</h2>
    <table>
        <thead><tr><th>No.</th><th>Tanggal / Kode</th><th>Perumahan / Unit</th><th>Tahapan / Progress</th><th>Barang</th><th>Jumlah</th><th>Nilai HPP</th><th>Keterangan</th></tr></thead>
        <tbody>
            @forelse($report['details'] as $row)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}<br>{{ $row['code'] }}</td>
                    <td>{{ $row['project'] }}<br>{{ $row['unit'] }}</td>
                    <td>{{ $row['stage'] }}<br>{{ $row['progress'] }} ({{ number_format($row['progress_percentage'], 2, ',', '.') }}%)</td>
                    <td>{{ $row['material'] }}<br>{{ $row['work_item'] ?: $row['material_code'] }}</td>
                    <td class="num">{{ number_format($row['quantity'], 2, ',', '.') }} {{ $row['unit_name'] }}</td>
                    <td class="num">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                    <td>{{ $row['note'] ?: '-' }}</td>
                </tr>
            @empty
                <tr><td class="center" colspan="8">Tidak ada rincian pemakaian barang.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="note">Laporan hanya memuat pemakaian material yang terhubung ke progress pembangunan. Estimasi nilai menggunakan harga HPP material saat laporan ditampilkan.</p>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
