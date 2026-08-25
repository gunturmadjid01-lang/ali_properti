<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->contract_no }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#111;font-size:12px;line-height:1.45;margin:32px} h1{text-align:center;font-size:18px;margin:0} h2{font-size:14px;margin-top:22px} table{width:100%;border-collapse:collapse;margin-top:10px} th,td{border:1px solid #333;padding:6px;vertical-align:top} .plain td{border:0;padding:2px}.right{text-align:right}.sign{margin-top:60px;display:grid;grid-template-columns:1fr 1fr;gap:100px;text-align:center}.muted{color:#555}@media print{button{display:none}body{margin:15mm}}
    </style>
</head>
<body>
<button onclick="window.print()">Cetak Kontrak</button>
<h1>KONTRAK PENAMBAHAN MUTU BANGUNAN</h1>
<p style="text-align:center">{{ $contract->contract_no }}<br><small>Versi dokumen {{ $contract->document_version ?? 1 }}</small></p>
<table class="plain">
    <tr><td width="25%">Perusahaan Pelaksana</td><td>: {{ $contract->company_snapshot['name'] ?? $contract->company?->nama_cabang }}</td></tr>
    <tr><td>Customer</td><td>: {{ $contract->customer_snapshot['name'] ?? $contract->customer?->nama }}</td></tr>
    <tr><td>Unit</td><td>: {{ $contract->unit_snapshot['housing'] ?? '' }} — Blok {{ $contract->unit_snapshot['block'] ?? '-' }} / No. {{ $contract->unit_snapshot['number'] ?? '-' }}</td></tr>
    <tr><td>Referensi SPR</td><td>: {{ $contract->spr?->kode_spr ?? 'Kontrak mandiri / tidak berasal dari SPR' }}</td></tr>
    <tr><td>Tanggal Kontrak</td><td>: {{ $contract->contract_date?->format('d/m/Y') }}</td></tr>
</table>
<h2>Rincian Pekerjaan</h2>
<table>
    <thead><tr><th>No</th><th>Pekerjaan dan Spesifikasi</th><th>Lokasi</th><th>Volume</th><th>Harga</th><th>Total</th></tr></thead>
    <tbody>
    @foreach($contract->items as $index => $item)
        <tr><td>{{ $index + 1 }}</td><td><strong>{{ $item->name }}</strong><br><span class="muted">{{ $item->specification }}</span></td><td>{{ $item->location }}</td><td class="right">{{ number_format($item->volume, 2, ',', '.') }} {{ $item->unit }}</td><td class="right">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td><td class="right">Rp {{ number_format($item->total, 0, ',', '.') }}</td></tr>
    @endforeach
    </tbody>
    <tfoot><tr><th colspan="5" class="right">Nilai Kontrak</th><th class="right">Rp {{ number_format($contract->contract_value, 0, ',', '.') }}</th></tr></tfoot>
</table>
<h2>Pembayaran</h2>
<p>Metode: {{ $contract->payment_method === 'cash' ? 'Cash / Lunas' : 'Cicilan / Termin' }}. Pembayaran wajib diarahkan ke rekening milik {{ $contract->company_snapshot['name'] ?? $contract->company?->nama_cabang }}.</p>
<table><thead><tr><th>Termin</th><th>Jatuh Tempo</th><th>Nilai</th></tr></thead><tbody>
@foreach($contract->schedules as $schedule)<tr><td>{{ $schedule->description }}</td><td>{{ $schedule->due_date?->format('d/m/Y') }}</td><td class="right">Rp {{ number_format($schedule->amount, 0, ',', '.') }}</td></tr>@endforeach
</tbody></table>
<h2>Jangka Waktu dan Garansi</h2>
<p>Rencana pekerjaan {{ $contract->planned_start_date?->format('d/m/Y') ?? '-' }} sampai {{ $contract->planned_finish_date?->format('d/m/Y') ?? '-' }}. Masa garansi {{ $contract->warranty_days }} hari setelah serah terima.</p>
<h2>Ketentuan</h2>
<p>{!! nl2br(e($contract->terms ?: 'Perubahan pekerjaan, volume, dan nilai kontrak hanya sah apabila disepakati tertulis oleh para pihak.')) !!}</p>
<div class="sign"><div>Customer<br><br><br><br><strong>{{ $contract->customer_snapshot['name'] ?? $contract->customer?->nama }}</strong></div><div>{{ $contract->company_snapshot['name'] ?? $contract->company?->nama_cabang }}<br><br><br><br><strong>{{ $contract->company_snapshot['manager'] ?? 'Penanggung Jawab' }}</strong></div></div>
</body>
</html>
