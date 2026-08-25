<?php

namespace App\Services;

use App\Models\Costumer;
use App\Models\MarketingLead;
use App\Models\MarketingLeadSource;
use App\Models\SalesLeadImportBatch;
use App\Models\SalesLeadIntakeRow;
use App\Models\SalesWorkItem;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class AdminSalesLeadIntakeService
{
    public function read(string $path, string $extension): array
    {
        return $extension === 'xlsx' ? $this->readXlsx($path) : $this->readCsv($path);
    }

    public function import(array $rows, User $user, string $filename): SalesLeadImportBatch
    {
        return DB::transaction(function () use ($rows, $user, $filename): SalesLeadImportBatch {
            $batch = SalesLeadImportBatch::query()->create([
                'batch_no' => 'LI-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'source_type' => 'file', 'original_filename' => $filename, 'total_rows' => count($rows), 'created_by' => $user->id,
            ]);
            $counts = ['imported' => 0, 'duplicate' => 0, 'invalid' => 0];
            foreach ($rows as $payload) {
                $result = $this->ingest($payload, 'file', $user, null, $batch->id);
                $counts[$result->status] = ($counts[$result->status] ?? 0) + 1;
            }
            $batch->update(['imported_rows' => $counts['imported'], 'duplicate_rows' => $counts['duplicate'], 'invalid_rows' => $counts['invalid']]);

            return $batch->refresh();
        });
    }

    public function ingest(array $payload, string $sourceType, ?User $user, ?string $idempotencyKey = null, ?int $batchId = null): SalesLeadIntakeRow
    {
        if ($idempotencyKey && ($existing = SalesLeadIntakeRow::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }
        $user ??= User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin_sales'))->orderBy('id')->first();
        $data = $this->normalize($payload);
        $duplicates = $this->duplicates($data['phone'], $data['email'], $data['identity_no']);
        $duplicate = $duplicates['lead'] ?? $duplicates['customer'];
        $errors = collect(['name', 'phone', 'source'])->filter(fn (string $key) => blank($data[$key]))->map(fn (string $key) => "Kolom {$key} wajib diisi.")->values();
        $status = $errors->isNotEmpty() ? 'invalid' : ($duplicate ? 'duplicate' : 'imported');

        return DB::transaction(function () use ($data, $payload, $sourceType, $user, $idempotencyKey, $batchId, $duplicates, $errors, $status): SalesLeadIntakeRow {
            $lead = null;
            if ($status === 'imported') {
                $source = MarketingLeadSource::query()->where('nama_sumber', $data['source'])->first();
                if (! $source) {
                    $errors = collect(['Sumber lead tidak terdaftar.']);
                    $status = 'invalid';
                } else {
                    $lead = MarketingLead::query()->create([
                        'lead_no' => $this->nextLeadNo(), 'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'],
                        'identity_no' => $data['identity_no'], 'lead_source_id' => $source->id, 'source_channel' => $data['channel'],
                        'ownership_type' => 'company', 'stage' => 'new', 'qualification_status' => 'unqualified',
                        'admin_sales_id' => $user?->id, 'created_by' => $user?->id, 'updated_by' => $user?->id,
                        'notes' => trim(implode("\n", array_filter(["Prioritas: {$data['priority']}", $data['notes']]))),
                    ]);
                    SalesWorkItem::query()->create([
                        'work_no' => 'AS-'.now()->format('YmdHisv').'-'.random_int(10, 99), 'category' => 'lead', 'title' => 'Verifikasi lead '.$lead->lead_no,
                        'description' => 'Lead masuk dari '.$sourceType.'. Periksa sumber dan kemungkinan duplikasi.', 'subject_type' => $lead->getMorphClass(), 'subject_id' => $lead->id,
                        'marketing_lead_id' => $lead->id, 'assigned_to' => $user?->id, 'assigned_by' => $user?->id, 'priority' => $data['priority'], 'status' => 'open', 'due_at' => now()->addHours(2),
                        'created_by' => $user?->id, 'updated_by' => $user?->id,
                    ]);
                }
            }

            return SalesLeadIntakeRow::query()->create([
                'batch_id' => $batchId, 'idempotency_key' => $idempotencyKey, 'source_type' => $sourceType, 'status' => $status,
                'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'], 'payload' => $payload,
                'validation_note' => $errors->implode(' '), 'duplicate_costumer_id' => $duplicates['customer']?->id,
                'duplicate_marketing_lead_id' => $duplicates['lead']?->id, 'marketing_lead_id' => $lead?->id,
            ]);
        });
    }

    private function normalize(array $row): array
    {
        $row = collect($row)->mapWithKeys(fn ($value, $key) => [Str::snake(Str::lower(trim((string) $key))) => is_string($value) ? trim($value) : $value])->all();

        $channels = ['website', 'whatsapp_company', 'office_phone', 'walk_in', 'company_social', 'ads', 'exhibition', 'company_referral', 'other'];
        $priorities = ['low', 'normal', 'high', 'urgent'];
        $channel = Str::snake(Str::lower((string) ($row['kanal'] ?? $row['channel'] ?? 'other')));
        $priority = Str::lower((string) ($row['prioritas'] ?? $row['priority'] ?? 'normal'));

        return [
            'name' => $row['nama'] ?? $row['name'] ?? null, 'phone' => preg_replace('/[^0-9+]/', '', (string) ($row['telepon'] ?? $row['phone'] ?? '')),
            'email' => Str::lower((string) ($row['email'] ?? '')) ?: null, 'identity_no' => (string) ($row['nik'] ?? $row['no_identitas'] ?? '') ?: null,
            'source' => $row['sumber'] ?? $row['sumber_lead'] ?? $row['source'] ?? null,
            'channel' => in_array($channel, $channels, true) ? $channel : 'other', 'priority' => in_array($priority, $priorities, true) ? $priority : 'normal', 'notes' => $row['catatan'] ?? $row['notes'] ?? null,
        ];
    }

    private function duplicates(?string $phone, ?string $email, ?string $identityNo): array
    {
        if (! $phone && ! $email && ! $identityNo) {
            return ['lead' => null, 'customer' => null];
        }

        $match = fn ($query) => $query->where(function ($query) use ($phone, $email, $identityNo) {
            $query->when($phone, fn ($q) => $q->orWhere('telepon', $phone))->when($email, fn ($q) => $q->orWhere('email', $email))->when($identityNo, fn ($q) => $q->orWhere('no_identitas', $identityNo));
        });

        return [
            'lead' => MarketingLead::query()->where(function ($query) use ($phone, $email, $identityNo) {
                $query->when($phone, fn ($q) => $q->orWhere('phone', $phone))->when($email, fn ($q) => $q->orWhere('email', $email))->when($identityNo, fn ($q) => $q->orWhere('identity_no', $identityNo));
            })->first(),
            'customer' => $match(Costumer::query())->first(),
        ];
    }

    private function nextLeadNo(): string
    {
        return 'LEAD-'.now()->format('Ymd').'-'.str_pad((string) (((int) MarketingLead::withTrashed()->whereDate('created_at', today())->count()) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new RuntimeException('File tidak dapat dibaca.');
        }
        $firstLine = fgets($handle) ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);
        $headers = fgetcsv($handle, separator: $delimiter) ?: [];
        if (isset($headers[0])) {
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        }
        $rows = [];
        while (($values = fgetcsv($handle, separator: $delimiter)) !== false) {
            if (count(array_filter($values, fn ($x) => $x !== null && $x !== '')) === 0) {
                continue;
            }
            $values = array_pad($values, count($headers), null);
            $rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
        }
        fclose($handle);

        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak valid.');
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = new DOMDocument;
            $doc->loadXML($xml);
            $xpath = new DOMXPath($doc);
            foreach ($xpath->query('//*[local-name()="si"]') as $item) {
                $text = '';
                foreach ($xpath->query('.//*[local-name()="t"]', $item) as $node) {
                    $text .= $node->textContent;
                }
                $shared[] = trim($text);
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('Sheet pertama tidak ditemukan.');
        }
        $sheet = new DOMDocument;
        $sheet->loadXML($sheetXml);
        $xpath = new DOMXPath($sheet);
        $matrix = [];
        foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
            $values = [];
            foreach ($xpath->query('./*[local-name()="c"]', $row) as $cell) {
                $ref = (string) $cell->attributes?->getNamedItem('r')?->nodeValue;
                preg_match('/[A-Z]+/', $ref, $match);
                $col = $this->columnIndex($match[0] ?? 'A');
                $value = (string) $xpath->evaluate('string(./*[local-name()="v"])', $cell);
                if ($cell->attributes?->getNamedItem('t')?->nodeValue === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($cell->attributes?->getNamedItem('t')?->nodeValue === 'inlineStr') {
                    $value = (string) $xpath->evaluate('string(./*[local-name()="is"]/*[local-name()="t"])', $cell);
                }
                $values[$col] = $value;
            }
            if ($values) {
                ksort($values);
                $matrix[] = array_replace(array_fill(0, max(array_keys($values)) + 1, null), $values);
            }
        }
        $headers = array_shift($matrix) ?: [];

        return array_values(array_filter(array_map(fn ($values) => array_combine($headers, array_pad($values, count($headers), null)), $matrix)));
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }

        return $index - 1;
    }
}
