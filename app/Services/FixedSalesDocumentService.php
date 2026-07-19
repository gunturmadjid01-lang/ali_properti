<?php

namespace App\Services;

use App\Models\Spr;
use Illuminate\Support\Str;
use ZipArchive;

class FixedSalesDocumentService
{
    public function catalog(): array
    {
        return [
            ['id' => 'spr', 'name' => 'Surat Pemesanan Rumah', 'document_type' => 'spr', 'description' => 'Format baku SPR Perumahan Sidratul Muntaha', 'file' => 'SPR Perumahan Sidratul Muntaha 01.docx'],
            ['id' => 'ppjb', 'name' => 'Perjanjian Pengikatan Jual Beli', 'document_type' => 'ppjb', 'description' => 'Format baku PPJB PT Ali Property Indonesia', 'file' => 'PERJANJIAN PENGIKATAN JUAL BELI.docx'],
            ['id' => 'handover', 'name' => 'Berita Acara Penyerahan Kunci Rumah', 'document_type' => 'handover', 'description' => 'BAST dan checklist masa pemeliharaan rumah', 'file' => 'Berita Acara Penyerahan Kunci Rumah.docx'],
            ['id' => 'signing-minutes', 'name' => 'Berita Acara Penandatanganan Kontrak', 'document_type' => 'signing_minutes', 'description' => 'Dokumen baku tahap penandatanganan kontrak Cash Bertahap dan KPR Developer', 'file' => null],
        ];
    }

    public function original(string $type)
    {
        abort_unless($this->valid($type), 404);
        if ($type === 'signing-minutes') {
            return view('documents.contract-signing-minutes', $this->signingMinutesData());
        }

        return response()->download($this->path($type), collect($this->catalog())->firstWhere('id', $type)['file']);
    }

    public function forSpr(Spr $spr, string $type)
    {
        abort_unless($this->valid($type), 404);
        if ($type === 'signing-minutes') {
            return view('documents.contract-signing-minutes', $this->signingMinutesData($spr));
        }

        $target = $this->generate($spr, $type);

        return response()->download($target, str($type.'-'.$spr->kode_spr)->slug('-').'.docx')->deleteFileAfterSend(true);
    }

    public function generate(Spr $spr, string $type): string
    {
        abort_unless($this->valid($type), 404);
        $spr->loadMissing(['costumer', 'detailRumah.perumahan.cabang', 'creator', 'salesTransaction.processSteps.assignee']);
        $target = tempnam(sys_get_temp_dir(), 'ali-document-').'.docx';
        copy($this->path($type), $target);
        $zip = new ZipArchive;
        throw_unless($zip->open($target) === true, 'Template Word tidak dapat dibuka.');
        $xml = $zip->getFromName('word/document.xml');
        throw_unless($xml !== false, 'Isi template Word tidak ditemukan.');
        $zip->addFromString('word/document.xml', $this->replaceParagraphText($xml, $this->replacements($spr, $type)));
        $zip->close();

        return $target;
    }

    private function replacements(Spr $spr, string $type): array
    {
        $customer = $spr->costumer;
        $unit = $spr->detailRumah;
        $housing = $unit?->perumahan;
        $transaction = $spr->salesTransaction;
        $handover = $transaction?->processSteps?->firstWhere('code', 'customer_handover');
        $handoverData = $handover?->metadata['data'] ?? [];
        $date = match ($type) {
            'handover' => $handover?->actual_date ?? $handover?->planned_date ?? now(),default => $spr->tanggal_spr ?? now()
        };
        $dateText = $date->translatedFormat('l d F Y');
        $money = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
        $unitLabel = trim(($unit?->kode_nlok ?: '').' / '.($unit?->nomor_rumah ?: ''), ' /') ?: '-';
        $marketing = $spr->creator?->name ?: ($transaction?->marketing?->name ?: '-');
        $common = [
            '=A' => $unit?->kode_nlok ?: '-', '=39' => $unit?->nomor_rumah ?: '-',
            'MUHAMMAD RIDHA' => $customer?->nama ?: '-', 'Muhammad Ridha' => $customer?->nama ?: '-', 'HENDRA ARIANTO LATIF' => $customer?->nama ?: '-', 'Hazwar Hamzah' => $customer?->nama ?: '-', 'HAZWAR HAMZAH' => $customer?->nama ?: '-',
            'JL. MASJID' => $customer?->alamat ?: '-', 'Jl. Masjid' => $customer?->alamat ?: '-', 'Jl. Pongtiku' => $customer?->alamat ?: '-', 'Jl. Andi Makkasau' => $customer?->alamat ?: '-',
            '+62 852-9939-5671' => $customer?->telepon ?: '-', '+62 823-4342-0104' => $customer?->telepon ?: '-', 'PNS' => $customer?->pekerjaan ?: '-', 'Karyawan' => $customer?->pekerjaan ?: '-',
            'A/11' => $unitLabel, 'F/20' => $unitLabel, 'A/39' => $unitLabel, '173.000.000' => number_format((float) ($spr->nilai_pengajuan_akhir ?: $spr->harga_jual), 0, ',', '.'),
            '5.730.000' => number_format((float) $spr->uang_muka, 0, ',', '.'), '167.270.000' => number_format((float) $spr->nilai_pengajuan_kpr, 0, ',', '.'),
            '78 m2' => $this->area($unit?->luas_tanah), '78 M2' => $this->area($unit?->luas_tanah), '36 m2' => $this->area($unit?->luas_bangunan), '36/78' => trim(($unit?->tipe_rumah ?: '-').'/'.($unit?->luas_tanah ?: '-')),
            'Perumahan Sidratul Muntaha' => $housing?->nama_perusahaan ?: '-', 'PERUMAHAN SIDRATUL MUNTAHA' => Str::upper($housing?->nama_perusahaan ?: '-'), 'SIDRATUL MUNTAHA' => Str::upper($housing?->nama_perusahaan ?: '-'), 'Sidratul Muntaha' => $housing?->nama_perusahaan ?: '-',
            'Jl. Soekarno Hatta' => $housing?->alamat ?: '-', 'JL. SOEKARNO HATTA –  LINGK. TAHAYA – HAYA – KELURAHAN KAREMA – KABUPATEN MAMUJU – PROV. SULAWESI BARAT' => Str::upper($housing?->alamat ?: '-'),
            'Senin tanggal 22 bulan Juni tahun 2026' => $dateText, 'Senin tanggal 06 bulan Juli tahun 2026' => $dateText, '22 Juni 2026' => $date->translatedFormat('d F Y'), '06 Juli 2026' => $date->translatedFormat('d F Y'), '10 Februari 2024' => $date->translatedFormat('d F Y'),
            'SARI INTANG' => Str::upper($marketing), 'KAHARMAN' => Str::upper($handoverData['developer_officer'] ?? $handover?->assignee?->name ?? '-'),
        ];
        if ($type === 'ppjb') {
            $common['137/PPJB/API/VI/2026'] = $handoverData['contract_number'] ?? sprintf('%03d/PPJB/API/%s/%s', $spr->id, $this->romanMonth((int) $date->format('n')), $date->format('Y'));
        }
        if ($type === 'spr') {
            $common['001/API/II/2024'] = $spr->kode_spr;
        }

        return collect($common)->sortByDesc(fn ($value, $key) => strlen($key))->all();
    }

    private function replaceParagraphText(string $xml, array $replacements): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach ($xpath->query('//w:p') as $paragraph) {
            $nodes = iterator_to_array($xpath->query('.//w:t', $paragraph));
            if ($nodes === []) {
                continue;
            }

            $original = implode('', array_map(fn ($node) => $node->nodeValue, $nodes));
            $text = $original;
            foreach ($replacements as $search => $replacement) {
                $exact = str_starts_with($search, '=');
                if ($exact) {
                    $search = substr($search, 1);
                }

                if ($exact) {
                    if (trim($text) === $search) {
                        $text = (string) $replacement;
                    }
                    continue;
                }

                // str_replace bekerja satu kali terhadap snapshot paragraf.
                // Hasil replacement tidak diproses ulang sehingga mustahil loop,
                // termasuk bila hasilnya masih mengandung teks yang dicari.
                $text = str_replace($search, (string) $replacement, $text);
            }

            if ($text !== $original) {
                $nodes[0]->nodeValue = $text;
                foreach (array_slice($nodes, 1) as $node) {
                    $node->nodeValue = '';
                }
            }
        }

        return $dom->saveXML();
    }

    private function valid(string $type): bool
    {
        return in_array($type, ['spr', 'ppjb', 'handover', 'signing-minutes'], true);
    }

    private function path(string $type): string
    {
        return resource_path("documents/fixed/{$type}.docx");
    }

    private function area($value): string
    {
        return filled($value) ? rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').' m2' : '-';
    }

    private function romanMonth(int $month): string
    {
        return [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month];
    }

    private function signingMinutesData(?Spr $spr = null): array
    {
        $spr?->loadMissing(['costumer', 'detailRumah.perumahan', 'salesTransaction.processSteps.assignee']);
        $transaction = $spr?->salesTransaction;
        $step = $transaction?->processSteps?->firstWhere('code', 'contract_signing');
        $metadata = $step?->metadata['data'] ?? [];
        $date = $metadata['contract_datetime'] ?? $step?->actual_date ?? now();
        $date = $date instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($date) : \Illuminate\Support\Carbon::parse($date);
        $unit = $spr?->detailRumah;

        return [
            'documentNumber' => $metadata['contract_number'] ?? ($spr ? sprintf('%03d/BA-PK/API/%s/%s', $spr->id, $this->romanMonth((int) $date->format('n')), $date->format('Y')) : '___/BA-PK/API/___/____'),
            'dateText' => $date->translatedFormat('l, d F Y'),
            'location' => $metadata['location'] ?? '-',
            'developerRepresentative' => $metadata['developer_representative'] ?? '-',
            'customerName' => $spr?->costumer?->nama ?? '-',
            'customerIdentity' => $spr?->costumer?->no_identitas ?? '-',
            'customerAddress' => $spr?->costumer?->alamat ?? '-',
            'housingName' => $unit?->perumahan?->nama_perusahaan ?? '-',
            'unitLabel' => trim(($unit?->kode_nlok ?? '').' / '.($unit?->nomor_rumah ?? ''), ' /') ?: '-',
            'contractValue' => 'Rp '.number_format((float) ($metadata['final_contract_value'] ?? $spr?->nilai_pengajuan_akhir ?? $spr?->harga_jual ?? 0), 0, ',', '.'),
            'paymentMethod' => match ($transaction?->payment_method ?? $spr?->metode_pembayaran) {
                'cash_bertahap' => 'Cash Bertahap',
                'kpr_developer' => 'KPR Developer',
                default => '-',
            },
        ];
    }
}
