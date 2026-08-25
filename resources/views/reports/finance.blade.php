<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #172033; font-size: 10px; margin: 24px; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .meta { color: #667085; margin-bottom: 16px; }
        .summary { width: 100%; margin: 12px 0 16px; border-collapse: separate; border-spacing: 6px; }
        .summary td { border: 1px solid #d7dce5; background: #f7f8fa; padding: 9px; }
        .summary span { display: block; color: #667085; font-size: 8px; text-transform: uppercase; }
        .summary strong { display: block; margin-top: 4px; font-size: 12px; }
        table.data { width: 100%; border-collapse: collapse; }
        .data th { background: #172033; color: white; padding: 7px; text-align: left; }
        .data td { border: 1px solid #d7dce5; padding: 6px; vertical-align: top; }
        .data tr:nth-child(even) td { background: #f7f8fa; }
        .footer { margin-top: 12px; color: #667085; font-size: 8px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ $scope }} &middot; Periode {{ $period }}</div>
    @if(!empty($summary))
        <table class="summary"><tr>
            @foreach($summary as $label => $value)
                <td><span>{{ $label }}</span><strong>{{ is_numeric($value) ? 'Rp '.number_format((float) $value, 0, ',', '.') : $value }}</strong></td>
            @endforeach
        </tr></table>
    @endif
    <table class="data">
        <thead><tr>@foreach($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $key => $label)
                        @php($value = data_get($row, $key))
                        <td>{{ is_numeric($value) && !in_array($key, ['code', 'reference'], true) ? number_format((float) $value, 0, ',', '.') : ($value ?? '-') }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ max(count($columns), 1) }}">Tidak ada data pada filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer">Dicetak {{ $printedAt }} &middot; Sumber: jurnal dan transaksi final pada sistem.</div>
</body>
</html>
