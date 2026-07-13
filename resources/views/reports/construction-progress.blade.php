<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Progress Pembangunan</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 landscape; margin: 9mm; }
        body { margin: 0; color: #111827; font-family: Arial, sans-serif; font-size: 9px; }
        .no-print { margin-bottom: 8px; padding: 7px 11px; font-weight: 700; }
        .title { border: 1px solid #64748b; background: #d9ead3; padding: 7px; text-align: center; }
        .title h1 { margin: 0; font-size: 16px; text-transform: uppercase; }
        .meta { display: grid; grid-template-columns: 150px 1fr; border: 1px solid #64748b; border-top: 0; }
        .meta div { padding: 3px 6px; border-bottom: 1px dotted #94a3b8; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #64748b; padding: 4px 5px; vertical-align: middle; }
        th { background: #dbe4f0; text-align: center; text-transform: uppercase; }
        td.num { text-align: right; white-space: nowrap; }
        td.center { text-align: center; }
        tr.stage td { background: #e5e7eb; font-weight: 700; text-align: center; text-transform: uppercase; }
        tfoot { font-weight: 700; }
        tfoot tr.financial td { background: #fce4d6; }
        .note { margin-top: 6px; color: #475569; font-size: 8px; }
        @media print {
            .no-print { display: none; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Cetak / Simpan PDF</button>
    <div class="title"><h1>Laporan Progress Pembangunan</h1></div>
    <div class="meta">
        <div>Perumahan</div><div>: {{ $report['project'] }}</div>
        <div>Tipe Bangunan</div><div>: {{ $report['building_type'] }}</div>
        <div>Periode</div><div>: {{ $report['period']['label'] }}</div>
        <div>Nilai SPK</div><div>: @foreach($report['units'] as $unit) {{ $unit['label'] }} = Rp {{ number_format($unit['contract_total'], 0, ',', '.') }}{{ !$loop->last ? ' | ' : '' }} @endforeach</div>
        <div>Dicetak</div><div>: {{ $printedAt }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:35px">No.</th>
                <th rowspan="2">Jenis Pekerjaan</th>
                <th rowspan="2" style="width:105px">Jumlah Harga</th>
                <th rowspan="2" style="width:60px">Bobot (%)</th>
                @foreach($report['units'] as $unit)
                    <th colspan="2">{{ $unit['label'] }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($report['units'] as $unit)
                    <th>Kumulatif (%)</th><th>Tertimbang (%)</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php($lastStage = null)
            @forelse($report['rows'] as $row)
                @if($lastStage !== $row['stage'])
                    <tr class="stage"><td colspan="{{ 4 + count($report['units']) * 2 }}">{{ $row['stage'] }}</td></tr>
                    @php($lastStage = $row['stage'])
                @endif
                <tr>
                    <td class="center">{{ $row['no'] }}</td>
                    <td>{{ $row['work'] }}</td>
                    <td class="num">Rp {{ number_format($row['amount'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['weight'], 2, ',', '.') }}%</td>
                    @foreach($row['units'] as $unit)
                        <td class="num">{{ number_format($unit['cumulative'], 2, ',', '.') }}%</td>
                        <td class="num"><strong>{{ number_format($unit['weighted'], 2, ',', '.') }}%</strong></td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="center" colspan="{{ max(4 + count($report['units']) * 2, 4) }}">Belum ada item SPK.</td></tr>
            @endforelse
        </tbody>
        @if(count($report['rows']) > 0)
            <tfoot>
                @foreach([
                    ['Bobot kumulatif', 'cumulative_weight', 'percent', false],
                    ['Bobot periode sebelumnya', 'previous_weight', 'percent', false],
                    ['Bobot periode ini', 'period_weight', 'percent', false],
                    ['Total opname', 'opname_total', 'money', true],
                    ['Pembayaran sebelumnya', 'payment_previous', 'money', true],
                    ['Pembayaran saat ini', 'payment_period', 'money', true],
                    ['Total pembayaran SPK', 'payment_total', 'money', true],
                ] as [$label, $key, $format, $financial])
                    <tr class="{{ $financial ? 'financial' : '' }}">
                        <td colspan="4"><em>{{ $label }}</em></td>
                        @foreach($report['units'] as $unit)
                            <td class="num" colspan="2">
                                @if($format === 'money')
                                    Rp {{ number_format($unit[$key], 0, ',', '.') }}
                                @else
                                    {{ number_format($unit[$key], 2, ',', '.') }}%
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tfoot>
        @endif
    </table>
    <p class="note">Pembayaran hanya berasal dari termin SPK berstatus Dana Cair yang terhubung ke progress unit terpilih.</p>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
