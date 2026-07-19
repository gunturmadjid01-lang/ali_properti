<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $record['heading'] ?? '' }}</title>
    <style>
        @page{size:A4;margin:14mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#172033;margin:0;font-size:12px}.toolbar{display:flex;gap:8px;justify-content:flex-end;margin-bottom:16px}.toolbar button{border:1px solid #94a3b8;background:#fff;padding:8px 14px;border-radius:6px;font-weight:700;cursor:pointer}.header{border-bottom:3px solid #0f4c81;padding-bottom:14px;margin-bottom:18px}.header small{color:#64748b;text-transform:uppercase;letter-spacing:1.5px}.header h1{font-size:25px;margin:6px 0}.header p{margin:0;color:#475569}.section{margin:20px 0;break-inside:avoid}.section h2{font-size:15px;color:#0f4c81;border-bottom:1px solid #cbd5e1;padding-bottom:6px}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px 18px}.field{border-bottom:1px dotted #cbd5e1;padding:5px 0}.field span{display:block;color:#64748b;font-size:10px;text-transform:uppercase}.field b{display:block;margin-top:3px}table{border-collapse:collapse;width:100%;font-size:11px}th,td{border:1px solid #cbd5e1;padding:7px;text-align:left;vertical-align:top}th{background:#eaf2f8;color:#0f4c81}.empty{padding:16px;text-align:center;color:#64748b;border:1px dashed #cbd5e1}.footer{margin-top:28px;border-top:1px solid #cbd5e1;padding-top:8px;color:#64748b;font-size:10px}@media print{.toolbar{display:none}body{font-size:11px}}
    </style>
</head>
<body>
<div class="toolbar"><button onclick="history.back()">Kembali</button><button onclick="window.print()">Cetak / Simpan PDF</button></div>
<header class="header"><small>{{ $title }}</small><h1>{{ $record['heading'] ?? '-' }}</h1><p>{{ $record['subtitle'] ?? '' }}</p></header>
<section class="section"><h2>Ringkasan Utama</h2><div class="grid">@foreach(($record['summary'] ?? []) as $label=>$value)<div class="field"><span>{{ $label }}</span><b>{{ filled($value) ? $value : '-' }}</b></div>@endforeach</div></section>
@foreach(['schedules'=>'Jadwal dan Tagihan','payments'=>'Pembayaran','processSteps'=>'Proses sampai Huni','timeline'=>'Histori','documents'=>'Dokumen'] as $key=>$label)
@if(!empty($record[$key]))<section class="section"><h2>{{ $label }}</h2><table><thead><tr>@foreach(array_keys((array)$record[$key][0]) as $column)<th>{{ str($column)->replace('_',' ')->title() }}</th>@endforeach</tr></thead><tbody>@foreach($record[$key] as $row)<tr>@foreach((array)$row as $value)<td>{{ is_array($value) ? collect($value)->pluck('label')->filter()->implode(', ') : ($value ?: '-') }}</td>@endforeach</tr>@endforeach</tbody></table></section>@endif
@endforeach
<footer class="footer">Dicetak dari sistem ERP pada {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()?->name ?? 'Sistem' }}.</footer>
@if($autoPrint)<script>window.addEventListener('load',()=>window.print())</script>@endif
</body></html>
