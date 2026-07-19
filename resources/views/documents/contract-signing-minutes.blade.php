<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Penandatanganan Kontrak</title>
    <style>
        @page { size: A4 portrait; margin: 22mm 20mm; }
        * { box-sizing: border-box; }
        body { max-width: 170mm; margin: 0 auto; color: #111; font: 12pt/1.55 "Times New Roman", serif; }
        h1 { margin: 18px 0 0; text-align: center; font-size: 15pt; text-decoration: underline; }
        .number { margin: 0 0 30px; text-align: center; }
        p { margin: 0 0 14px; text-align: justify; }
        table { width: 100%; margin: 8px 0 18px; border-collapse: collapse; }
        td { padding: 3px 4px; vertical-align: top; }
        td:first-child { width: 48mm; }
        td:nth-child(2) { width: 5mm; }
        .signatures { margin-top: 42px; table-layout: fixed; text-align: center; }
        .signatures td { width: 50%; text-align: center; }
        .sign-space { height: 75px; }
        .print { position: fixed; top: 14px; right: 14px; padding: 9px 14px; border: 0; border-radius: 6px; background: #0f766e; color: white; font: bold 14px sans-serif; cursor: pointer; }
        @media print { .print { display: none; } body { max-width: none; } }
    </style>
</head>
<body>
    <button class="print" onclick="window.print()">Cetak Dokumen</button>
    <h1>BERITA ACARA PENANDATANGANAN KONTRAK</h1>
    <div class="number">Nomor: {{ $documentNumber }}</div>

    <p>Pada hari ini, {{ $dateText }}, bertempat di {{ $location }}, para pihak telah melaksanakan penandatanganan kontrak jual beli/pembiayaan unit rumah dengan data sebagai berikut:</p>

    <table>
        <tr><td>Metode Pembayaran</td><td>:</td><td><strong>{{ $paymentMethod }}</strong></td></tr>
        <tr><td>Nama Perumahan</td><td>:</td><td>{{ $housingName }}</td></tr>
        <tr><td>Blok / Nomor Unit</td><td>:</td><td>{{ $unitLabel }}</td></tr>
        <tr><td>Nilai Kontrak</td><td>:</td><td>{{ $contractValue }}</td></tr>
        <tr><td>Pihak Developer</td><td>:</td><td>{{ $developerRepresentative }}</td></tr>
        <tr><td>Nama Customer</td><td>:</td><td>{{ $customerName }}</td></tr>
        <tr><td>Nomor Identitas</td><td>:</td><td>{{ $customerIdentity }}</td></tr>
        <tr><td>Alamat Customer</td><td>:</td><td>{{ $customerAddress }}</td></tr>
    </table>

    <p>Para pihak menerangkan bahwa dokumen kontrak telah dibaca, dipahami, dan ditandatangani dalam keadaan sadar serta tanpa paksaan dari pihak mana pun. Dokumen pendukung dan salinan kontrak diserahkan sesuai ketentuan yang berlaku.</p>
    <p>Berita acara ini dibuat sebagai bukti pelaksanaan penandatanganan kontrak dan menjadi bagian yang tidak terpisahkan dari dokumen transaksi.</p>

    <table class="signatures">
        <tr><td>Pihak Developer,</td><td>Customer,</td></tr>
        <tr class="sign-space"><td></td><td></td></tr>
        <tr><td><strong><u>{{ $developerRepresentative }}</u></strong></td><td><strong><u>{{ $customerName }}</u></strong></td></tr>
    </table>
</body>
</html>
