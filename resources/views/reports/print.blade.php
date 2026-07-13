<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 16px; }
        .eyebrow { font-size: 11px; text-transform: uppercase; letter-spacing: .14em; color: #6b7280; font-weight: 700; }
        h1 { font-size: 22px; margin-top: 4px; }
        .meta { margin-top: 8px; font-size: 12px; color: #4b5563; }
        .filters { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0 16px; font-size: 11px; }
        .filter { border: 1px solid #d1d5db; padding: 4px 8px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 7px; text-align: left; vertical-align: top; }
        th { background: #e5e7eb; font-size: 10px; text-transform: uppercase; letter-spacing: .08em; }
        tbody tr:nth-child(even) { background: #f9fafb; }
        .summary { margin-top: 12px; font-size: 12px; font-weight: 700; }
        @media print {
            body { margin: 12mm; }
            .no-print { display: none; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()" style="margin-bottom: 12px; padding: 8px 12px; font-weight: 700;">Cetak / Simpan PDF</button>
    <div class="header">
        <p class="eyebrow">{{ $groupTitle }}</p>
        <h1>{{ $title }}</h1>
        <p class="meta">Dicetak: {{ $printedAt }}</p>
    </div>

    @if(! empty($filters))
        <div class="filters">
            @foreach($filters as $key => $value)
                <span class="filter">{{ str_replace('_', ' ', $key) }}: {{ $value }}</span>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach($columns as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $column)
                        <td>{{ $row[$column['key']] ?? '-' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($columns), 1) }}" style="text-align:center; font-weight:700;">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="summary">Total data: {{ number_format($summary['total_rows'] ?? count($rows), 0, ',', '.') }}</p>

    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
