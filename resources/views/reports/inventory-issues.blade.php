<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; }
        h1 { margin: 0 0 4px; font-size: 18px; }
        .muted { color: #6b7280; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 5px; margin: 12px 0; }
        .summary td { border: 1px solid #d1d5db; padding: 8px; }
        .summary strong { display: block; margin-top: 3px; font-size: 15px; }
        .transaction { margin: 0 0 14px; border: 1px solid #9ca3af; page-break-inside: avoid; }
        .header { padding: 8px; background: #f3f4f6; }
        .meta { width: 100%; border-collapse: collapse; }
        .meta td { width: 25%; vertical-align: top; padding-right: 8px; }
        table.items { width: 100%; border-collapse: collapse; }
        .items th, .items td { border-top: 1px solid #d1d5db; padding: 6px; text-align: left; }
        .items th { background: #f9fafb; font-size: 8px; text-transform: uppercase; }
        .status { font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="muted">Dicetak {{ $printedAt }} | Periode {{ $filters['date_from'] ?: 'awal' }} s.d. {{ $filters['date_to'] ?: 'sekarang' }}</div>

    <table class="summary"><tr>
        @foreach ($summary as $card)
            <td>{{ $card['label'] }}<strong>{{ $card['value'] }}</strong></td>
        @endforeach
    </tr></table>

    @forelse ($transactions as $transaction)
        <div class="transaction">
            <div class="header">
                <table class="meta"><tr>
                    <td><strong>{{ $transaction['transaction_no'] }}</strong><br>{{ $transaction['date'] }} | {{ ucwords(str_replace('_', ' ', $transaction['transaction_type'])) }}</td>
                    <td><strong>Penanggung jawab</strong><br>{{ $transaction['borrower'] }}<br>Pengambil: {{ $transaction['taken_by_name'] ?: '-' }}</td>
                    <td><strong>Tujuan</strong><br>{{ $transaction['project_name'] ?: ($transaction['destination_location'] ?: '-') }}<br>{{ $transaction['house_number'] ? 'Unit '.$transaction['house_number'] : '' }}</td>
                    <td><strong>Status</strong><br><span class="status">{{ $transaction['is_overdue'] ? 'TERLAMBAT' : strtoupper(str_replace('_', ' ', $transaction['status'])) }}</span><br>Petugas: {{ $transaction['officer_name'] ?: '-' }}</td>
                </tr></table>
            </div>
            <table class="items">
                <thead><tr><th>Nama Barang</th><th>Unit Aset</th><th>Keluar</th><th>Kembali</th><th>Sisa</th><th>Kondisi</th></tr></thead>
                <tbody>
                @foreach ($transaction['items'] as $line)
                    <tr>
                        <td><strong>{{ $line['item_name'] }}</strong><br><span class="muted">{{ $line['item_code'] }}</span></td>
                        <td>{{ $line['kode_aset'] ? 'Unit '.$line['kode_aset'] : '-' }}</td>
                        <td>{{ $line['quantity'] }} {{ $line['unit'] }}</td>
                        <td>{{ $line['returned_quantity'] }}</td>
                        <td>{{ $line['quantity'] - $line['returned_quantity'] }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $line['condition_out'])) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div style="padding: 6px;"><strong>Keperluan:</strong> {{ $transaction['purpose'] }}</div>
        </div>
    @empty
        <p>Tidak ada transaksi pada filter ini.</p>
    @endforelse
</body>
</html>
