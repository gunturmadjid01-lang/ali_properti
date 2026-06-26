<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsFieldOptions;
use App\Http\Controllers\Controller;
use App\Models\ContractorOpname;
use App\Models\DetailRumah;
use App\Models\FieldDefect;
use App\Models\InternalHandover;
use App\Models\ProgressPembangunan;
use App\Models\QualityInspection;
use App\Models\SafetyReport;
use App\Models\SiteManpowerLog;
use App\Models\SpkKontraktor;
use App\Models\SpkKontraktorPayment;
use App\Models\WorkChangeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FieldSupervisionController extends Controller
{
    use BuildsFieldOptions;

    protected array $sections = [
        'defect' => [
            'title' => 'Defect / Punch List',
            'model' => FieldDefect::class,
            'code' => 'kode_defect',
            'prefix' => 'DEF',
            'photo' => true,
            'approval' => true,
        ],
        'opname-kontraktor' => [
            'title' => 'Opname Kontraktor',
            'model' => ContractorOpname::class,
            'code' => 'kode_opname',
            'prefix' => 'OPN',
            'photo' => true,
            'approval' => true,
        ],
        'perubahan-pekerjaan' => [
            'title' => 'Perubahan Pekerjaan',
            'model' => WorkChangeRequest::class,
            'code' => 'kode_perubahan',
            'prefix' => 'CHG',
            'photo' => false,
            'approval' => true,
        ],
        'tenaga-kerja-alat' => [
            'title' => 'Tenaga Kerja & Alat',
            'model' => SiteManpowerLog::class,
            'code' => 'kode_log',
            'prefix' => 'MNP',
            'photo' => false,
            'approval' => false,
        ],
        'k3' => [
            'title' => 'K3 / Safety Report',
            'model' => SafetyReport::class,
            'code' => 'kode_k3',
            'prefix' => 'K3',
            'photo' => true,
            'approval' => true,
        ],
        'serah-terima-internal' => [
            'title' => 'Serah Terima Internal',
            'model' => InternalHandover::class,
            'code' => 'kode_serah_terima',
            'prefix' => 'STI',
            'photo' => false,
            'approval' => true,
        ],
    ];

    public function show(Request $request, string $section): Response
    {
        $config = $this->config($section);
        $model = $config['model'];
        $search = trim((string) $request->query('search', ''));
        $perumahanId = $request->query('perumahan_id');
        $detailRumahId = $request->query('detail_rumah_id');

        $rows = $model::query()
            ->with($this->relations($section))
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $section, $search))
            ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
            ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
            ->latest('tanggal')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Model $row) => $this->row($row, $section));

        return Inertia::render('Admin/FieldSupervision/Index', [
            'title' => $config['title'],
            'section' => $section,
            'baseUrl' => route('admin.field-supervision.show', $section, absolute: false),
            'filters' => ['search' => $search, 'perumahan_id' => $perumahanId, 'detail_rumah_id' => $detailRumahId],
            'rows' => $rows,
            'fields' => $this->fields($section),
            'options' => $this->options(),
            'config' => [
                'photo' => $config['photo'],
                'approval' => $config['approval'],
                'canApprove' => $this->canApproveFieldData(),
            ],
        ]);
    }

    public function store(Request $request, string $section): RedirectResponse
    {
        $this->authorizeFieldUser();
        $config = $this->config($section);
        $validated = $this->validated($request, $section);

        DB::transaction(function () use ($request, $section, $config, $validated): void {
            $payload = $this->normalizePayload($request, $section, $validated);
            $model = $config['model'];

            $row = $model::query()->create([
                ...$payload,
                $config['code'] => $this->nextCode($config['prefix']),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($section === 'tenaga-kerja-alat' && $row instanceof SiteManpowerLog) {
                $assetIds = in_array($validated['sumber_alat'] ?? 'tidak_ada', ['aset_kantor', 'kombinasi'], true)
                    ? ($validated['office_asset_ids'] ?? [])
                    : [];
                $row->officeAssets()->sync($assetIds);
            }
        });

        return back()->with('success', $config['title'].' berhasil disimpan.');
    }

    public function update(Request $request, string $section, string $id): RedirectResponse
    {
        $this->authorizeFieldUser();
        $config = $this->config($section);
        $row = $this->findRow($section, $id);
        abort_if(($row->record_status ?? 'draft') === 'locked', 422, 'Data sudah locked.');
        $validated = $this->validated($request, $section);

        DB::transaction(function () use ($request, $section, $config, $row, $validated): void {
            $payload = $this->normalizePayload($request, $section, $validated, $row);
            if ($config['approval']) {
                $payload['approval_status'] = 'menunggu_approval_manager';
                $payload['approved_by'] = null;
                $payload['approved_at'] = null;
            }
            $row->update([...$payload, 'updated_by' => auth()->id()]);

            if ($section === 'tenaga-kerja-alat' && $row instanceof SiteManpowerLog) {
                $assetIds = in_array($validated['sumber_alat'] ?? 'tidak_ada', ['aset_kantor', 'kombinasi'], true)
                    ? ($validated['office_asset_ids'] ?? [])
                    : [];
                $row->officeAssets()->sync($assetIds);
            }
        });

        return back()->with('success', $config['title'].' berhasil diperbarui.');
    }

    public function destroy(string $section, string $id): RedirectResponse
    {
        $this->authorizeFieldUser();
        $row = $this->findRow($section, $id);
        abort_if(($row->record_status ?? 'draft') === 'locked', 422, 'Data sudah locked.');
        if (isset($row->foto) && $row->foto) {
            Storage::disk('public')->delete($row->foto);
        }
        $row->delete();

        return back()->with('success', 'Data berhasil dihapus.');
    }

    public function approve(string $section, string $id): RedirectResponse
    {
        abort_unless($this->canApproveFieldData(), 403, 'Hanya manager atau owner yang dapat approve.');
        $config = $this->config($section);
        abort_unless($config['approval'], 422, 'Menu ini tidak membutuhkan approval.');

        DB::transaction(function () use ($section, $id): void {
            $row = $this->findRow($section, $id);
            if (($row->approval_status ?? null) === 'approved') {
                return;
            }

            if ($section === 'opname-kontraktor' && $row instanceof ContractorOpname) {
                $this->ensureOpnameWithinContract($row);
            }

            $row->update([
                'approval_status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            if ($section === 'serah-terima-internal' && $row instanceof InternalHandover) {
                $row->detailRumah?->update([
                    'status_pembangunan' => 'selesai',
                    'progress_terakhir' => max((float) ($row->detailRumah?->progress_terakhir ?? 0), (float) $row->progress_unit),
                    'updated_by' => auth()->id(),
                ]);
            }

            if ($section === 'opname-kontraktor' && $row instanceof ContractorOpname) {
                $row->update(['status' => 'disetujui']);
                $this->syncOpnameToSpkPayment($row->fresh());
            }
        });

        return back()->with('success', 'Data berhasil disetujui.');
    }

    public function lock(string $section, string $id): RedirectResponse
    {
        $this->authorizeFieldUser();
        $row = $this->findRow($section, $id);
        $row->update(['record_status' => 'locked', 'locked_at' => now(), 'locked_by' => auth()->id()]);

        return back()->with('success', 'Data berhasil dikunci.');
    }

    public function unlock(string $section, string $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['owner', 'super_admin']), 403, 'Hanya owner yang dapat membuka lock.');
        $row = $this->findRow($section, $id);
        $row->update(['record_status' => 'draft', 'locked_at' => null, 'locked_by' => null]);

        return back()->with('success', 'Data berhasil dibuka.');
    }

    protected function config(string $section): array
    {
        abort_unless(isset($this->sections[$section]), 404);

        return $this->sections[$section];
    }

    protected function findRow(string $section, string $id): Model
    {
        $model = $this->config($section)['model'];

        return $model::query()->findOrFail($id);
    }

    protected function relations(string $section): array
    {
        $base = ['perumahan:id,nama_perusahaan', 'detailRumah:id,perumahan_id,kode_nlok,nomor_rumah,progress_terakhir', 'creator:id,name', 'updater:id,name'];
        if ($this->config($section)['approval']) {
            $base[] = 'approvedBy:id,name';
        }
        if (in_array($section, ['defect', 'opname-kontraktor', 'perubahan-pekerjaan'], true)) {
            $base[] = 'tahapanPembangunan:id,nama_tahapan';
        }
        if (in_array($section, ['opname-kontraktor', 'perubahan-pekerjaan', 'tenaga-kerja-alat'], true)) {
            $base[] = 'spkKontraktor:id,nomor_spk,judul_pekerjaan,nilai_kontrak';
        }
        if ($section === 'tenaga-kerja-alat') {
            $base[] = 'officeAssets:id,kode_aset,nama_aset,status';
        }
        if ($section === 'opname-kontraktor') {
            $base[] = 'progressPembangunan:id,nama_progress,persentase,persentase_total';
        }
        if ($section === 'defect') {
            $base[] = 'qualityInspection:id,kode_inspeksi,item_pemeriksaan';
        }

        return $base;
    }

    protected function applySearch(Builder $query, string $section, string $search): void
    {
        $query->where(function (Builder $query) use ($section, $search): void {
            foreach ($this->searchColumns($section) as $column) {
                $query->orWhere($column, 'like', "%{$search}%");
            }
            $query->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%"))
                ->orWhereHas('detailRumah', fn (Builder $query) => $query->where('kode_nlok', 'like', "%{$search}%")->orWhere('nomor_rumah', 'like', "%{$search}%"));
        });
    }

    protected function searchColumns(string $section): array
    {
        return match ($section) {
            'defect' => ['kode_defect', 'temuan', 'instruksi_perbaikan', 'status'],
            'opname-kontraktor' => ['kode_opname', 'pekerjaan', 'status', 'catatan'],
            'perubahan-pekerjaan' => ['kode_perubahan', 'uraian_perubahan', 'alasan', 'status'],
            'tenaga-kerja-alat' => ['kode_log', 'sumber_tenaga_kerja', 'kontraktor', 'nama_mandor', 'alat_digunakan', 'pekerjaan'],
            'k3' => ['kode_k3', 'temuan', 'tindakan', 'status'],
            'serah-terima-internal' => ['kode_serah_terima', 'checklist', 'catatan', 'status'],
            default => [],
        };
    }

    protected function row(Model $row, string $section): array
    {
        $config = $this->config($section);
        $detail = $row->toArray();

        if ($section === 'tenaga-kerja-alat' && $row instanceof SiteManpowerLog) {
            $detail['office_asset_ids'] = $row->officeAssets->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
            $detail['office_assets_label'] = $row->officeAssets->map(fn ($asset) => $asset->kode_aset.' - '.$asset->nama_aset)->join(', ');
            unset($detail['office_assets']);
        }

        return [
            'id' => $row->id,
            'kode' => $row->{$config['code']},
            'tanggal' => optional($row->tanggal)->format('Y-m-d'),
            'perumahan_id' => (string) $row->perumahan_id,
            'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
            'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
            'spk_kontraktor_id' => (string) ($row->spk_kontraktor_id ?? ''),
            'progress_pembangunan_id' => (string) ($row->progress_pembangunan_id ?? ''),
            'quality_inspection_id' => (string) ($row->quality_inspection_id ?? ''),
            'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
            'unit' => $row->detailRumah ? trim(($row->detailRumah->kode_nlok ?? '').' '.($row->detailRumah->nomor_rumah ?? '')) : 'Kawasan',
            'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
            'spk' => $row->spkKontraktor?->nomor_spk ?? '-',
            'progress' => $row->progressPembangunan?->nama_progress ?? '-',
            'qc' => $row->qualityInspection?->kode_inspeksi ?? '-',
            'summary' => $this->summary($row, $section),
            'detail' => $detail,
            'foto_url' => isset($row->foto) && $row->foto ? route('media', ['path' => $row->foto], false) : null,
            'status' => $row->status ?? '-',
            'approval_status' => $row->approval_status ?? '-',
            'created_by_name' => $row->creator?->name ?? '-',
            'updated_by_name' => $row->updater?->name ?? '-',
            'approved_by_name' => $row->approvedBy?->name ?? '-',
            'record_status' => $row->record_status ?? 'draft',
            'can_approve' => $config['approval'] && ($row->approval_status ?? null) !== 'approved' && $this->canApproveFieldData(),
            'can_edit' => ($row->record_status ?? 'draft') !== 'locked',
            'can_delete' => ($row->record_status ?? 'draft') !== 'locked',
            'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && (bool) auth()->user()?->hasAnyRole(['pengawas', 'manajer_pimpro', 'owner', 'super_admin']),
            'can_unlock' => (bool) auth()->user()?->hasAnyRole(['owner', 'super_admin']),
        ];
    }

    protected function summary(Model $row, string $section): string
    {
        return match ($section) {
            'defect' => trim(($row->prioritas ?? '').' - '.$row->temuan),
            'opname-kontraktor' => trim(($row->pekerjaan ?? '').' - Rp '.number_format((float) $row->nilai_diajukan, 0, ',', '.')),
            'perubahan-pekerjaan' => trim(($row->jenis_perubahan ?? '').' - '.$row->uraian_perubahan),
            'tenaga-kerja-alat' => trim(
                ucwords(str_replace('_', ' ', (string) ($row->sumber_tenaga_kerja ?? 'kontraktor')))
                .' - '.($row->pekerjaan ?? '')
                .' - '.(((int) $row->mandor) + ((int) $row->tukang) + ((int) $row->kenek)).' orang'
                .' - Upah Rp '.number_format((float) $row->nilai_upah, 0, ',', '.')
                .((float) $row->biaya_sewa_alat > 0 ? ' - Sewa alat Rp '.number_format((float) $row->biaya_sewa_alat, 0, ',', '.') : '')
            ),
            'k3' => trim(($row->tingkat_risiko ?? '').' - '.$row->temuan),
            'serah-terima-internal' => trim(($row->kondisi_bangunan ?? '').' - progress '.$row->progress_unit.'%'),
            default => '-',
        };
    }

    protected function fields(string $section): array
    {
        $base = [
            ['name' => 'tanggal', 'label' => 'Tanggal', 'type' => 'date', 'required' => true],
            ['name' => 'perumahan_id', 'label' => 'Perumahan', 'type' => 'select', 'optionsKey' => 'perumahans', 'required' => true],
            ['name' => 'detail_rumah_id', 'label' => 'Unit', 'type' => 'select', 'optionsKey' => 'detailRumahs'],
        ];

        return match ($section) {
            'defect' => [...$base,
                ['name' => 'tahapan_pembangunan_id', 'label' => 'Tahapan', 'type' => 'select', 'optionsKey' => 'tahapanPembangunans'],
                ['name' => 'quality_inspection_id', 'label' => 'Referensi QC', 'type' => 'select', 'optionsKey' => 'qualityInspections'],
                ['name' => 'kategori', 'label' => 'Kategori', 'type' => 'select', 'optionsKey' => 'defectCategories'],
                ['name' => 'prioritas', 'label' => 'Prioritas', 'type' => 'select', 'optionsKey' => 'priorities'],
                ['name' => 'temuan', 'label' => 'Temuan', 'type' => 'textarea', 'required' => true],
                ['name' => 'instruksi_perbaikan', 'label' => 'Instruksi Perbaikan', 'type' => 'textarea'],
                ['name' => 'target_selesai', 'label' => 'Target Selesai', 'type' => 'date'],
                ['name' => 'tanggal_selesai', 'label' => 'Tanggal Selesai', 'type' => 'date'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'defectStatuses'],
            ],
            'opname-kontraktor' => [...$base,
                ['name' => 'spk_kontraktor_id', 'label' => 'SPK Kontraktor', 'type' => 'select', 'optionsKey' => 'spks'],
                ['name' => 'tahapan_pembangunan_id', 'label' => 'Tahapan', 'type' => 'select', 'optionsKey' => 'tahapanPembangunans'],
                ['name' => 'progress_pembangunan_id', 'label' => 'Progress Terkait', 'type' => 'select', 'optionsKey' => 'progressPembangunans'],
                ['name' => 'pekerjaan', 'label' => 'Pekerjaan', 'type' => 'text', 'required' => true],
                ['name' => 'progress_diakui', 'label' => 'Progress Diakui %', 'type' => 'number'],
                ['name' => 'nilai_diajukan', 'label' => 'Nilai Diajukan', 'type' => 'currency'],
                ['name' => 'nilai_disetujui', 'label' => 'Nilai Disetujui', 'type' => 'currency'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'opnameStatuses'],
                ['name' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea'],
            ],
            'perubahan-pekerjaan' => [...$base,
                ['name' => 'spk_kontraktor_id', 'label' => 'SPK Kontraktor', 'type' => 'select', 'optionsKey' => 'spks'],
                ['name' => 'tahapan_pembangunan_id', 'label' => 'Tahapan', 'type' => 'select', 'optionsKey' => 'tahapanPembangunans'],
                ['name' => 'jenis_perubahan', 'label' => 'Jenis Perubahan', 'type' => 'select', 'optionsKey' => 'changeTypes'],
                ['name' => 'uraian_perubahan', 'label' => 'Uraian Perubahan', 'type' => 'textarea', 'required' => true],
                ['name' => 'alasan', 'label' => 'Alasan', 'type' => 'textarea'],
                ['name' => 'estimasi_biaya', 'label' => 'Estimasi Biaya', 'type' => 'currency'],
                ['name' => 'estimasi_hari', 'label' => 'Estimasi Hari Tambahan', 'type' => 'number'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'requestStatuses'],
            ],
            'tenaga-kerja-alat' => [...$base,
                ['name' => 'sumber_tenaga_kerja', 'label' => 'Sumber Tenaga Kerja', 'type' => 'select', 'optionsKey' => 'manpowerSources', 'default' => 'kontraktor'],
                ['name' => 'spk_kontraktor_id', 'label' => 'SPK Kontraktor', 'type' => 'select', 'optionsKey' => 'spks'],
                ['name' => 'kontraktor', 'label' => 'Nama Kontraktor / Penyedia', 'type' => 'text'],
                ['name' => 'nama_mandor', 'label' => 'Nama Mandor / Koordinator', 'type' => 'text'],
                ['name' => 'mandor', 'label' => 'Jumlah Mandor', 'type' => 'number'],
                ['name' => 'tukang', 'label' => 'Jumlah Tukang', 'type' => 'number'],
                ['name' => 'kenek', 'label' => 'Jumlah Kenek', 'type' => 'number'],
                ['name' => 'tipe_upah', 'label' => 'Tipe Upah', 'type' => 'select', 'optionsKey' => 'wageTypes', 'default' => 'harian'],
                ['name' => 'jumlah_periode', 'label' => 'Jumlah Periode', 'type' => 'number', 'default' => '1', 'hideWhen' => ['tipe_upah' => ['borongan']]],
                ['name' => 'tarif_mandor', 'label' => 'Tarif Mandor / Orang / Periode', 'type' => 'currency', 'hideWhen' => ['tipe_upah' => ['borongan']]],
                ['name' => 'tarif_tukang', 'label' => 'Tarif Tukang / Orang / Periode', 'type' => 'currency', 'hideWhen' => ['tipe_upah' => ['borongan']]],
                ['name' => 'tarif_kenek', 'label' => 'Tarif Kenek / Orang / Periode', 'type' => 'currency', 'hideWhen' => ['tipe_upah' => ['borongan']]],
                ['name' => 'nilai_borongan', 'label' => 'Nilai Borongan', 'type' => 'currency', 'showWhen' => ['tipe_upah' => ['borongan']]],
                ['name' => 'jam_kerja', 'label' => 'Jam Kerja', 'type' => 'number', 'default' => '8'],
                ['name' => 'jam_lembur', 'label' => 'Jam Lembur', 'type' => 'number'],
                ['name' => 'tarif_lembur', 'label' => 'Tarif Lembur / Orang / Jam', 'type' => 'currency'],
                ['name' => 'nilai_upah', 'label' => 'Total Upah Terhitung', 'type' => 'computed-currency'],
                ['name' => 'sumber_alat', 'label' => 'Sumber Alat', 'type' => 'select', 'optionsKey' => 'equipmentSources', 'default' => 'tidak_ada'],
                ['name' => 'office_asset_ids', 'label' => 'Aset Kantor yang Digunakan', 'type' => 'multi-select', 'optionsKey' => 'officeAssets', 'showWhen' => ['sumber_alat' => ['aset_kantor', 'kombinasi']]],
                ['name' => 'alat_digunakan', 'label' => 'Alat Luar yang Digunakan', 'type' => 'textarea', 'showWhen' => ['sumber_alat' => ['aset_luar', 'kombinasi']]],
                ['name' => 'penyedia_alat', 'label' => 'Penyedia / Pemilik Alat Luar', 'type' => 'text', 'showWhen' => ['sumber_alat' => ['aset_luar', 'kombinasi']]],
                ['name' => 'biaya_sewa_alat', 'label' => 'Biaya Sewa Alat Luar', 'type' => 'currency', 'showWhen' => ['sumber_alat' => ['aset_luar', 'kombinasi']]],
                ['name' => 'pekerjaan', 'label' => 'Pekerjaan', 'type' => 'textarea'],
                ['name' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea'],
            ],
            'k3' => [...$base,
                ['name' => 'kategori', 'label' => 'Kategori', 'type' => 'select', 'optionsKey' => 'safetyCategories'],
                ['name' => 'tingkat_risiko', 'label' => 'Tingkat Risiko', 'type' => 'select', 'optionsKey' => 'riskLevels'],
                ['name' => 'temuan', 'label' => 'Temuan', 'type' => 'textarea', 'required' => true],
                ['name' => 'tindakan', 'label' => 'Tindakan', 'type' => 'textarea'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'defectStatuses'],
            ],
            'serah-terima-internal' => [...$base,
                ['name' => 'progress_unit', 'label' => 'Progress Unit %', 'type' => 'number'],
                ['name' => 'kondisi_bangunan', 'label' => 'Kondisi Bangunan', 'type' => 'select', 'optionsKey' => 'handoverConditions'],
                ['name' => 'checklist', 'label' => 'Checklist', 'type' => 'textarea'],
                ['name' => 'catatan', 'label' => 'Catatan', 'type' => 'textarea'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'optionsKey' => 'handoverStatuses'],
            ],
            default => $base,
        };
    }

    protected function options(): array
    {
        $options = $this->fieldOptions();
        $options['spks'] = SpkKontraktor::query()->with(['kontraktor:id,nama_kontraktor', 'detailRumah:id,kode_nlok,nomor_rumah'])
            ->latest('id')->limit(300)->get(['id', 'nomor_spk', 'kontraktor_id', 'detail_rumah_id', 'judul_pekerjaan', 'nilai_kontrak'])
            ->map(fn (SpkKontraktor $row) => ['value' => (string) $row->id, 'label' => $row->nomor_spk.' - '.$row->judul_pekerjaan, 'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''), 'nilai_kontrak' => (float) $row->nilai_kontrak])->values();
        $options['progressPembangunans'] = ProgressPembangunan::query()->where('approval_status', 'approved')->latest('tanggal')->limit(300)->get(['id', 'detail_rumah_id', 'nama_progress', 'persentase', 'persentase_total'])
            ->map(fn (ProgressPembangunan $row) => ['value' => (string) $row->id, 'label' => $row->nama_progress.' - '.$row->persentase.'%', 'detail_rumah_id' => (string) $row->detail_rumah_id])->values();
        $options['qualityInspections'] = QualityInspection::query()->latest('tanggal')->limit(300)->get(['id', 'detail_rumah_id', 'kode_inspeksi', 'item_pemeriksaan'])
            ->map(fn (QualityInspection $row) => ['value' => (string) $row->id, 'label' => $row->kode_inspeksi.' - '.str($row->item_pemeriksaan)->limit(48), 'detail_rumah_id' => (string) ($row->detail_rumah_id ?? '')])->values();
        $options['defectCategories'] = $this->simpleOptions(['pekerjaan', 'material', 'finishing', 'mep', 'struktur', 'lainnya']);
        $options['priorities'] = $this->simpleOptions(['low', 'medium', 'high', 'urgent']);
        $options['defectStatuses'] = $this->simpleOptions(['open', 'dalam_perbaikan', 'selesai', 'verified']);
        $options['opnameStatuses'] = $this->simpleOptions(['diajukan', 'review', 'disetujui', 'ditolak']);
        $options['requestStatuses'] = $this->simpleOptions(['diajukan', 'review', 'disetujui', 'ditolak', 'dikerjakan']);
        $options['changeTypes'] = $this->simpleOptions(['pekerjaan_tambah', 'pekerjaan_kurang', 'ubah_spek', 'ubah_desain', 'percepatan']);
        $options['safetyCategories'] = $this->simpleOptions(['checklist', 'insiden', 'near_miss', 'unsafe_action', 'unsafe_condition']);
        $options['riskLevels'] = $this->simpleOptions(['low', 'medium', 'high', 'critical']);
        $options['handoverConditions'] = $this->simpleOptions(['siap_review', 'perlu_perbaikan_minor', 'perlu_perbaikan_major', 'siap_serah_terima']);
        $options['handoverStatuses'] = $this->simpleOptions(['diajukan', 'review', 'siap_marketing', 'perlu_perbaikan']);
        $options['manpowerSources'] = $this->simpleOptions(['kontraktor', 'tukang_owner', 'mandor_internal', 'harian_lepas']);
        $options['wageTypes'] = $this->simpleOptions(['harian', 'borongan', 'mingguan', 'bulanan']);
        $options['equipmentSources'] = $this->simpleOptions(['tidak_ada', 'aset_kantor', 'aset_luar', 'kombinasi']);
        $options['officeAssets'] = \App\Models\OfficeAsset::query()
            ->whereNotIn('status', ['rusak', 'hilang'])
            ->orderBy('nama_aset')
            ->get(['id', 'kode_aset', 'nama_aset', 'status'])
            ->map(fn ($asset) => [
                'value' => (string) $asset->id,
                'label' => $asset->kode_aset.' - '.$asset->nama_aset.' ('.ucwords(str_replace('_', ' ', $asset->status)).')',
            ])
            ->values();

        return $options;
    }

    protected function validated(Request $request, string $section): array
    {
        $rules = [
            'tanggal' => ['required', 'date'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => [$section === 'serah-terima-internal' ? 'required' : 'nullable', 'exists:detail_rumahs,id'],
            'foto' => ['nullable', 'image', 'max:4096'],
        ];

        return $request->validate(match ($section) {
            'defect' => [...$rules, 'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'], 'quality_inspection_id' => ['nullable', 'exists:quality_inspections,id'], 'kategori' => ['required', 'string'], 'prioritas' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])], 'temuan' => ['required', 'string'], 'instruksi_perbaikan' => ['nullable', 'string'], 'target_selesai' => ['nullable', 'date'], 'tanggal_selesai' => ['nullable', 'date'], 'status' => ['required', 'string']],
            'opname-kontraktor' => [...$rules, 'spk_kontraktor_id' => ['nullable', 'exists:spk_kontraktors,id'], 'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'], 'progress_pembangunan_id' => ['nullable', 'exists:progress_pembangunans,id'], 'pekerjaan' => ['required', 'string'], 'progress_diakui' => ['nullable', 'numeric', 'min:0', 'max:100'], 'nilai_diajukan' => ['nullable', 'numeric', 'min:0'], 'nilai_disetujui' => ['nullable', 'numeric', 'min:0'], 'status' => ['required', 'string'], 'catatan' => ['nullable', 'string']],
            'perubahan-pekerjaan' => [...$rules, 'spk_kontraktor_id' => ['nullable', 'exists:spk_kontraktors,id'], 'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'], 'jenis_perubahan' => ['required', 'string'], 'uraian_perubahan' => ['required', 'string'], 'alasan' => ['nullable', 'string'], 'estimasi_biaya' => ['nullable', 'numeric', 'min:0'], 'estimasi_hari' => ['nullable', 'integer', 'min:0'], 'status' => ['required', 'string']],
            'tenaga-kerja-alat' => [
                ...$rules,
                'sumber_tenaga_kerja' => ['required', Rule::in(['kontraktor', 'tukang_owner', 'mandor_internal', 'harian_lepas'])],
                'spk_kontraktor_id' => ['nullable', 'exists:spk_kontraktors,id'],
                'kontraktor' => ['nullable', 'string'],
                'nama_mandor' => ['nullable', 'string'],
                'mandor' => ['nullable', 'integer', 'min:0'],
                'tukang' => ['nullable', 'integer', 'min:0'],
                'kenek' => ['nullable', 'integer', 'min:0'],
                'tipe_upah' => ['required', Rule::in(['harian', 'borongan', 'mingguan', 'bulanan'])],
                'jumlah_periode' => ['required_unless:tipe_upah,borongan', 'nullable', 'numeric', 'min:0.01'],
                'tarif_mandor' => ['nullable', 'numeric', 'min:0'],
                'tarif_tukang' => ['nullable', 'numeric', 'min:0'],
                'tarif_kenek' => ['nullable', 'numeric', 'min:0'],
                'nilai_borongan' => ['required_if:tipe_upah,borongan', 'nullable', 'numeric', 'min:0'],
                'nilai_upah' => ['nullable', 'numeric', 'min:0'],
                'jam_kerja' => ['nullable', 'numeric', 'min:0'],
                'jam_lembur' => ['nullable', 'numeric', 'min:0'],
                'tarif_lembur' => ['nullable', 'numeric', 'min:0'],
                'sumber_alat' => ['required', Rule::in(['tidak_ada', 'aset_kantor', 'aset_luar', 'kombinasi'])],
                'office_asset_ids' => ['nullable', 'array', Rule::requiredIf(fn () => in_array($request->input('sumber_alat'), ['aset_kantor', 'kombinasi'], true))],
                'office_asset_ids.*' => ['distinct', 'exists:office_assets,id'],
                'alat_digunakan' => ['nullable', 'string', Rule::requiredIf(fn () => in_array($request->input('sumber_alat'), ['aset_luar', 'kombinasi'], true))],
                'penyedia_alat' => ['nullable', 'string'],
                'biaya_sewa_alat' => ['nullable', 'numeric', 'min:0'],
                'pekerjaan' => ['nullable', 'string'],
                'catatan' => ['nullable', 'string'],
            ],
            'k3' => [...$rules, 'kategori' => ['required', 'string'], 'tingkat_risiko' => ['required', 'string'], 'temuan' => ['required', 'string'], 'tindakan' => ['nullable', 'string'], 'status' => ['required', 'string']],
            'serah-terima-internal' => [...$rules, 'progress_unit' => ['nullable', 'numeric', 'min:0', 'max:100'], 'kondisi_bangunan' => ['required', 'string'], 'checklist' => ['nullable', 'string'], 'catatan' => ['nullable', 'string'], 'status' => ['required', 'string']],
            default => $rules,
        });
    }

    protected function normalizePayload(Request $request, string $section, array $validated, ?Model $existing = null): array
    {
        $payload = collect($validated)->except(['foto', 'office_asset_ids'])->all();
        foreach (['detail_rumah_id', 'tahapan_pembangunan_id', 'quality_inspection_id', 'spk_kontraktor_id', 'progress_pembangunan_id'] as $key) {
            if (array_key_exists($key, $payload) && blank($payload[$key])) {
                $payload[$key] = null;
            }
        }

        if ($section === 'serah-terima-internal' && empty($payload['progress_unit']) && ! empty($payload['detail_rumah_id'])) {
            $payload['progress_unit'] = DetailRumah::query()->whereKey($payload['detail_rumah_id'])->value('progress_terakhir') ?? 0;
        }

        if ($section === 'tenaga-kerja-alat' && ($payload['sumber_tenaga_kerja'] ?? 'kontraktor') !== 'kontraktor') {
            $payload['spk_kontraktor_id'] = null;
        }

        if ($section === 'tenaga-kerja-alat') {
            $totalPekerja = (int) ($payload['mandor'] ?? 0) + (int) ($payload['tukang'] ?? 0) + (int) ($payload['kenek'] ?? 0);
            $jumlahPeriode = max(0, (float) ($payload['jumlah_periode'] ?? 0));
            $upahPokok = ($payload['tipe_upah'] ?? null) === 'borongan'
                ? (float) ($payload['nilai_borongan'] ?? 0)
                : (
                    ((int) ($payload['mandor'] ?? 0) * (float) ($payload['tarif_mandor'] ?? 0))
                    + ((int) ($payload['tukang'] ?? 0) * (float) ($payload['tarif_tukang'] ?? 0))
                    + ((int) ($payload['kenek'] ?? 0) * (float) ($payload['tarif_kenek'] ?? 0))
                ) * $jumlahPeriode;
            $upahLembur = $totalPekerja * (float) ($payload['jam_lembur'] ?? 0) * (float) ($payload['tarif_lembur'] ?? 0);

            $payload['nilai_upah'] = $upahPokok + $upahLembur;

            if (! in_array($payload['sumber_alat'] ?? 'tidak_ada', ['aset_luar', 'kombinasi'], true)) {
                $payload['alat_digunakan'] = null;
                $payload['penyedia_alat'] = null;
                $payload['biaya_sewa_alat'] = 0;
            }
        }

        if ($request->hasFile('foto')) {
            if ($existing && isset($existing->foto) && $existing->foto) {
                Storage::disk('public')->delete($existing->foto);
            }
            $payload['foto'] = $request->file('foto')->store('pengawas/'.$section, 'public');
        } elseif ($existing && isset($existing->foto)) {
            $payload['foto'] = $existing->foto;
        }

        return $payload;
    }

    protected function nextCode(string $prefix): string
    {
        return $prefix.'-'.now()->format('ymd-His').'-'.random_int(10, 99);
    }

    protected function ensureOpnameWithinContract(ContractorOpname $opname): void
    {
        if (! $opname->spk_kontraktor_id) {
            return;
        }

        $spk = SpkKontraktor::query()->find($opname->spk_kontraktor_id);
        if (! $spk) {
            return;
        }

        $nilaiOpname = (float) ($opname->nilai_disetujui ?: $opname->nilai_diajukan);
        $approvedBefore = ContractorOpname::query()
            ->where('spk_kontraktor_id', $opname->spk_kontraktor_id)
            ->where('id', '!=', $opname->id)
            ->where('approval_status', 'approved')
            ->sum('nilai_disetujui');

        if (((float) $approvedBefore + $nilaiOpname) > (float) $spk->nilai_kontrak) {
            throw ValidationException::withMessages([
                'nilai_disetujui' => 'Total opname disetujui melebihi nilai kontrak SPK. Sisa kontrak: Rp '.number_format(max(0, (float) $spk->nilai_kontrak - (float) $approvedBefore), 0, ',', '.').'.',
            ]);
        }
    }

    protected function syncOpnameToSpkPayment(ContractorOpname $opname): void
    {
        if (! $opname->spk_kontraktor_id) {
            return;
        }

        $nominal = (float) ($opname->nilai_disetujui ?: $opname->nilai_diajukan);
        if ($nominal <= 0) {
            return;
        }

        $payment = SpkKontraktorPayment::query()
            ->where('spk_kontraktor_id', $opname->spk_kontraktor_id)
            ->where('contractor_opname_id', $opname->id)
            ->first();

        if (! $payment) {
            $payment = SpkKontraktorPayment::query()
                ->where('spk_kontraktor_id', $opname->spk_kontraktor_id)
                ->whereNull('contractor_opname_id')
                ->where('status', 'menunggu_pengajuan')
                ->orderBy('termin_ke')
                ->first();
        }

        if (! $payment) {
            $lastTermin = (int) SpkKontraktorPayment::query()
                ->where('spk_kontraktor_id', $opname->spk_kontraktor_id)
                ->max('termin_ke');

            $payment = SpkKontraktorPayment::query()->create([
                'spk_kontraktor_id' => $opname->spk_kontraktor_id,
                'termin_ke' => $lastTermin + 1,
                'tanggal_jatuh_tempo' => $opname->tanggal,
                'tanggal_pembayaran' => null,
                'nominal' => 0,
                'status' => 'menunggu_pengajuan',
            ]);
        }

        if ($payment->status !== 'menunggu_pengajuan') {
            throw ValidationException::withMessages([
                'opname' => 'Termin SPK terkait sudah masuk proses pembayaran. Buat opname/termin baru untuk koreksi berikutnya.',
            ]);
        }

        $payment->update([
            'contractor_opname_id' => $opname->id,
            'tanggal_pembayaran' => null,
            'nominal' => $nominal,
            'keterangan' => trim('Opname '.$opname->kode_opname.' - '.$opname->pekerjaan),
            'status' => 'menunggu_pengajuan',
            'requested_by' => null,
            'requested_at' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    protected function simpleOptions(array $values): array
    {
        return collect($values)
            ->map(fn (string $value) => ['value' => $value, 'label' => ucwords(str_replace('_', ' ', $value))])
            ->values()
            ->all();
    }
}
