<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\ApprovalWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class HeavyEquipmentController extends Controller
{
    public function show(string $id)
    {
        $this->authorizeModule('view');
        $equipment = DB::table('heavy_equipments as e')->join('heavy_equipment_types as t', 't.id', '=', 'e.heavy_equipment_type_id')->whereNull('e.deleted_at')->where('e.id', $id)->select('e.*', 't.name as type_name')->first();
        abort_if(! $equipment, 404);
        $usages = DB::table('heavy_equipment_usages as usage')
            ->join('heavy_equipment_operators as operator', 'operator.id', '=', 'usage.operator_id')
            ->leftJoin('perumahans as proyek', 'proyek.id', '=', 'usage.perumahan_id')
            ->leftJoin('detail_rumahs as rumah', 'rumah.id', '=', 'usage.detail_rumah_id')
            ->whereNull('usage.deleted_at')->where('usage.heavy_equipment_id', $id)->latest('usage.date')
            ->get(['usage.id', 'usage.transaction_no', 'usage.date', 'operator.name as operator_name', 'proyek.nama_perusahaan as project_name', 'rumah.nomor_rumah as house_number', 'usage.project as work_location', 'usage.hour_meter_start', 'usage.hour_meter_end', 'usage.duration_hours', 'usage.status']);
        $replacements = DB::table('heavy_component_replacements as replacement')
            ->leftJoin('heavy_equipment_components as old_component', 'old_component.id', '=', 'replacement.old_component_id')
            ->join('heavy_equipment_components as new_component', 'new_component.id', '=', 'replacement.new_component_id')
            ->leftJoin('heavy_equipment_operators as operator', 'operator.id', '=', 'replacement.operator_id')
            ->whereNull('replacement.deleted_at')->where('replacement.heavy_equipment_id', $id)->latest('replacement.date')
            ->get(['replacement.id', 'replacement.transaction_no', 'replacement.date', 'old_component.name as old_component_name', 'new_component.name as new_component_name', 'replacement.hour_meter', 'replacement.reason', 'operator.name as operator_name', 'replacement.technician']);

        return Inertia::render('Admin/HeavyEquipment/Show', [
            'title' => 'Detail Alat Berat', 'equipment' => (array) $equipment,
            'components' => DB::table('heavy_equipment_components')->whereNull('deleted_at')->where('heavy_equipment_id', $id)->get(),
            'usages' => $usages,
            'maintenances' => DB::table('heavy_equipment_maintenances')->whereNull('deleted_at')->where('heavy_equipment_id', $id)->latest('date')->get(),
            'damages' => DB::table('heavy_equipment_damages')->whereNull('deleted_at')->where('heavy_equipment_id', $id)->latest('date')->get(),
            'replacements' => $replacements,
            'fuelings' => DB::table('heavy_equipment_fuelings')->whereNull('deleted_at')->where('heavy_equipment_id', $id)->latest('date')->get(),
            'currentUsage' => $usages->firstWhere('status', 'in_use'), 'indexUrl' => '/admin/alat-berat/equipment',
        ]);
    }

    public function index(Request $request, string $section = 'dashboard')
    {
        $this->authorizeModule('view');
        $config = $this->config($section);
        $search = trim((string) $request->query('search'));
        $sortable = array_merge(['id'], array_column($config['fields'], 'name'));
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $query = $config['table'] ? DB::table($config['table'])->whereNull('deleted_at') : null;
        if ($query && $search !== '') {
            $query->where(fn (Builder $q) => collect($config['search'])->each(fn ($c, $i) => $i ? $q->orWhere($c, 'like', "%{$search}%") : $q->where($c, 'like', "%{$search}%")));
        }
        $options = $this->options();

        return Inertia::render('Admin/OperationsModule/Index', ['title' => 'Alat Berat', 'module' => 'heavy', 'section' => $section, 'sectionTitle' => $config['title'], 'baseUrl' => '/admin/alat-berat', 'menu' => $this->menu(), 'fields' => $config['fields'], 'columns' => $config['columns'] ?? null, 'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction], 'rows' => $query ? $query->orderBy($sort, $direction)->paginate(15)->withQueryString()->through(fn ($r) => $this->withArchive($section, $this->formatListRow($section, (array) $r, $options))) : ['data' => [], 'links' => []], 'summary' => $this->summary(), 'dashboardData' => $section === 'dashboard' ? $this->dashboardData() : null, 'options' => $options, 'permissions' => ['create' => $this->can('create'), 'update' => $this->can('update'), 'delete' => $this->can('delete'), 'export' => $this->can('export'), 'approve' => $this->can('approve'), 'print' => $this->can('print')]]);
    }

    public function create(Request $request, string $section)
    {
        $this->authorizeModule('create');

        return $this->formPage($section);
    }

    public function edit(Request $request, string $section, string $id)
    {
        $this->authorizeModule('update');

        return $this->formPage($section, $id);
    }

    public function store(Request $r, string $section)
    {
        $this->authorizeModule('create');
        $c = $this->config($section);
        $d = $r->validate($c['rules']);
        DB::transaction(function () use ($section, $c, $d) {
            $this->fillAutomaticCode($section, $d);
            $this->save($section, $c['table'], [...$d, 'created_by' => auth()->id(), 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        });

        return redirect("/admin/alat-berat/{$section}")->with('success', $c['title'].' berhasil disimpan.');
    }

    public function update(Request $r, string $section, string $id)
    {
        $this->authorizeModule('update');
        $c = $this->config($section);
        $d = $r->validate($c['rules']);
        DB::table($c['table'])->where('id', $id)->whereNull('deleted_at')->update([...$d, 'updated_by' => auth()->id(), 'updated_at' => now()]);

        return redirect("/admin/alat-berat/{$section}")->with('success', 'Data berhasil diperbarui.');
    }

    public function destroy(string $section, string $id)
    {
        $this->authorizeModule('delete');
        $c = $this->config($section);
        DB::table($c['table'])->where('id', $id)->update(['deleted_at' => now(), 'updated_by' => auth()->id(), 'updated_at' => now()]);

        return back()->with('success', 'Data diarsipkan; histori tidak dihapus permanen.');
    }

    public function export(string $section, string $format)
    {
        $this->authorizeModule('export');
        $c = $this->config($section);
        $rows = DB::table($c['table'])->whereNull('deleted_at')->get();
        if ($format === 'pdf') {
            return Pdf::loadView('reports.module-table', ['title' => $c['title'], 'rows' => $rows, 'printedAt' => now()->format('d/m/Y H:i')])->setPaper('a4', 'landscape')->download('alat-berat-'.$section.'.pdf');
        }$view = view('reports.module-table', ['title' => $c['title'], 'rows' => $rows, 'printedAt' => now()->format('d/m/Y H:i')]);

        return response($view)->header('Content-Type', 'application/vnd.ms-excel')->header('Content-Disposition', 'attachment; filename="alat-berat-'.$section.'.xls"');
    }

    private function formPage(string $section, ?string $id = null)
    {
        $c = $this->config($section);
        abort_unless($c['table'], 404);
        $row = $id ? DB::table($c['table'])->where('id', $id)->whereNull('deleted_at')->first() : null;
        if ($id) {
            abort_if(! $row, 404);
        }

return Inertia::render('Admin/OperationsModule/Form', ['title' => 'Alat Berat', 'module' => 'heavy', 'section' => $section, 'sectionTitle' => $c['title'], 'baseUrl' => '/admin/alat-berat', 'indexUrl' => "/admin/alat-berat/{$section}", 'actionUrl' => $id ? "/admin/alat-berat/{$section}/records/{$id}" : "/admin/alat-berat/{$section}/records", 'method' => $id ? 'put' : 'post', 'fields' => $c['fields'], 'options' => $this->options(), 'row' => $row ? (array) $row : null]);
    }

    private function save(string $s, string $table, array $d): void
    {
        if (filled($d['detail_rumah_id'] ?? null)) {
            $house = DB::table('detail_rumahs')->whereNull('deleted_at')->find($d['detail_rumah_id']);
            abort_if(! $house, 422, 'Unit rumah tidak ditemukan.');
            abort_if(filled($d['perumahan_id'] ?? null) && (int) $house->perumahan_id !== (int) $d['perumahan_id'], 422, 'Unit rumah tidak berada pada perumahan yang dipilih.');
            $d['perumahan_id'] = $house->perumahan_id;
        }
        if ($s === 'components' && ($d['heavy_equipment_id'] ?? null)) {
            $equipment = DB::table('heavy_equipments')->find($d['heavy_equipment_id']);
            abort_if(! $equipment || (int) $equipment->heavy_equipment_type_id !== (int) $d['heavy_equipment_type_id'], 422, 'Alat terpasang harus memiliki jenis yang sama dengan komponen.');
        }
        if ($s === 'replacements') {
            $equipment = DB::table('heavy_equipments')->lockForUpdate()->find($d['heavy_equipment_id']);
            $new = DB::table('heavy_equipment_components')->lockForUpdate()->find($d['new_component_id']);
            abort_if(! $equipment || ! $new || in_array($new->status, ['installed', 'damaged', 'service']) || (int) $new->heavy_equipment_type_id !== (int) $equipment->heavy_equipment_type_id, 422, 'Komponen baru tidak tersedia atau tidak sesuai jenis alat.');
            if ($d['old_component_id'] ?? null) {
                $old = DB::table('heavy_equipment_components')->lockForUpdate()->find($d['old_component_id']);
                abort_if(! $old || (int) $old->heavy_equipment_id !== (int) $equipment->id || $old->status !== 'installed', 422, 'Komponen lama tidak sedang terpasang pada alat yang dipilih.');
                DB::table('heavy_equipment_components')->where('id', $d['old_component_id'])->update(['heavy_equipment_id' => null, 'status' => $d['old_component_status'], 'condition' => $d['old_component_condition'] ?? 'used', 'updated_at' => now()]);
            }DB::table('heavy_equipment_components')->where('id', $d['new_component_id'])->update(['heavy_equipment_id' => $d['heavy_equipment_id'], 'status' => 'installed', 'updated_at' => now()]);
        }
        if ($s === 'usage') {
            $equipment = DB::table('heavy_equipments')->lockForUpdate()->find($d['heavy_equipment_id']);
            abort_if(! $equipment || in_array($equipment->status, ['service', 'damaged']), 422, 'Alat tidak dapat digunakan.');
            abort_if((float) $d['hour_meter_start'] < (float) $equipment->current_hour_meter, 422, 'Hour meter awal tidak boleh lebih kecil dari hour meter alat saat ini.');
            abort_if(($d['hour_meter_end'] ?? $d['hour_meter_start']) < $d['hour_meter_start'], 422, 'Hour meter akhir tidak boleh lebih kecil.');
            $isOpen = ! filled($d['hour_meter_end'] ?? null);
            abort_if($isOpen && DB::table('heavy_equipment_usages')->whereNull('deleted_at')->where('heavy_equipment_id', $equipment->id)->where('status', 'in_use')->exists(), 422, 'Alat masih memiliki penggunaan aktif. Selesaikan penggunaan sebelumnya terlebih dahulu.');
            $d['duration_hours'] = max(0, (float) ($d['hour_meter_end'] ?? 0) - (float) $d['hour_meter_start']);
            $d['status'] = $isOpen ? 'in_use' : 'completed';
            DB::table('heavy_equipments')->where('id', $equipment->id)->update(['current_hour_meter' => max($equipment->current_hour_meter, $d['hour_meter_end'] ?? $d['hour_meter_start']), 'status' => $d['status'] === 'completed' ? 'active' : 'in_use', 'updated_at' => now()]);
        }
        if ($s === 'maintenance') {
            DB::table('heavy_equipments')->where('id', $d['heavy_equipment_id'])->update(['status' => $d['status'] === 'completed' ? 'active' : 'service', 'updated_at' => now()]);
        }
        if ($s === 'damages') {
            DB::table('heavy_equipments')->where('id', $d['heavy_equipment_id'])->update(['status' => $d['repair_status'] === 'completed' ? 'active' : 'damaged', 'updated_at' => now()]);
        }
        if ($s === 'fuel') {
            $d['total_cost'] = (float) $d['liters'] * (float) $d['price_per_liter'];
        }
        DB::table($table)->insert($d);
    }

    private function summary(): array
    {
        return [['label' => 'Total Alat Berat', 'value' => DB::table('heavy_equipments')->whereNull('deleted_at')->count()], ['label' => 'Sedang Digunakan', 'value' => DB::table('heavy_equipments')->whereNull('deleted_at')->where('status', 'in_use')->count()], ['label' => 'Sedang Servis', 'value' => DB::table('heavy_equipments')->whereNull('deleted_at')->where('status', 'service')->count()], ['label' => 'Alat Rusak', 'value' => DB::table('heavy_equipments')->whereNull('deleted_at')->where('status', 'damaged')->count()], ['label' => 'Total Komponen', 'value' => DB::table('heavy_equipment_components')->whereNull('deleted_at')->count()], ['label' => 'Komponen Rusak', 'value' => DB::table('heavy_equipment_components')->whereNull('deleted_at')->where('status', 'damaged')->count()], ['label' => 'Maintenance Terdekat', 'value' => DB::table('heavy_equipment_maintenances')->whereNull('deleted_at')->whereDate('next_schedule', '>=', now())->min('next_schedule') ?? '-'], ['label' => 'Penggunaan Hari Ini', 'value' => DB::table('heavy_equipment_usages')->whereNull('deleted_at')->whereDate('date', today())->count()]];
    }

    private function fillAutomaticCode(string $section, array &$data): void
    {
        $definition = match ($section) {
            'equipment' => ['heavy_equipments', 'code', 'ALT'],'components' => ['heavy_equipment_components', 'code', 'KMP'],'replacements' => ['heavy_component_replacements', 'transaction_no', 'GNT'],'usage' => ['heavy_equipment_usages', 'transaction_no', 'PGL'],'maintenance' => ['heavy_equipment_maintenances', 'maintenance_no', 'MNT'],default => null
        };
        if (! $definition || filled($data[$definition[1]] ?? null)) {
            return;
        }$number = ((int) DB::table($definition[0])->lockForUpdate()->max('id')) + 1;
        do {
            $code = $definition[2].'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $number++;
        } while (DB::table($definition[0])->where($definition[1], $code)->exists());
        $data[$definition[1]] = $code;
    }

    private function dashboardData(): array
    {
        return ['activeUsages' => DB::table('heavy_equipment_usages as usage')->join('heavy_equipments as equipment', 'equipment.id', '=', 'usage.heavy_equipment_id')->join('heavy_equipment_operators as operator', 'operator.id', '=', 'usage.operator_id')->leftJoin('perumahans as proyek', 'proyek.id', '=', 'usage.perumahan_id')->leftJoin('detail_rumahs as rumah', 'rumah.id', '=', 'usage.detail_rumah_id')->whereNull('usage.deleted_at')->where('usage.status', 'in_use')->latest('usage.date')->limit(8)->get(['equipment.id', 'equipment.name as equipment_name', 'usage.transaction_no', 'operator.name as operator_name', 'proyek.nama_perusahaan as project_name', 'rumah.nomor_rumah as house_number', 'usage.hour_meter_start']), 'upcomingMaintenance' => DB::table('heavy_equipment_maintenances as maintenance')->join('heavy_equipments as equipment', 'equipment.id', '=', 'maintenance.heavy_equipment_id')->whereNull('maintenance.deleted_at')->whereIn('maintenance.status', ['scheduled', 'in_progress'])->orderBy('maintenance.next_schedule')->limit(8)->get(['equipment.id', 'equipment.name as equipment_name', 'maintenance.maintenance_type', 'maintenance.next_schedule', 'maintenance.status'])];
    }

    private function menu(): array
    {
        return collect([['dashboard', 'Dashboard'], ['equipment', 'Data Alat Berat'], ['types', 'Jenis Alat'], ['components', 'Komponen Alat Berat'], ['replacements', 'Riwayat Penggantian Komponen'], ['usage', 'Penggunaan Alat'], ['operators', 'Operator'], ['maintenance', 'Maintenance'], ['damages', 'Kerusakan'], ['fuel', 'Pengisian BBM'], ['reports', 'Laporan']])->map(fn ($x) => ['key' => $x[0], 'label' => $x[1]])->all();
    }

    private function options(): array
    {
        return [
            'types' => DB::table('heavy_equipment_types')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name, 'display_label' => $row->name])->values()->all(),
            'equipment' => DB::table('heavy_equipments')->whereNull('deleted_at')->orderBy('name')->get(['id', 'code', 'name', 'heavy_equipment_type_id', 'status'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name.' ('.$row->code.')', 'display_label' => $row->name, 'heavy_equipment_type_id' => (string) $row->heavy_equipment_type_id, 'status' => $row->status])->values()->all(),
            'components' => DB::table('heavy_equipment_components')->whereNull('deleted_at')->orderBy('name')->get(['id', 'code', 'name', 'heavy_equipment_type_id', 'heavy_equipment_id', 'status'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name.' ('.$row->code.')', 'display_label' => $row->name, 'heavy_equipment_type_id' => (string) $row->heavy_equipment_type_id, 'heavy_equipment_id' => $row->heavy_equipment_id ? (string) $row->heavy_equipment_id : '', 'status' => $row->status])->values()->all(),
            'operators' => DB::table('heavy_equipment_operators')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'identity_no'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => trim($row->name.($row->identity_no ? ' ('.$row->identity_no.')' : '')), 'display_label' => $row->name])->values()->all(),
            'perumahans' => DB::table('perumahans')->whereNull('deleted_at')->orderBy('nama_perusahaan')->get(['id', 'kode_proyek', 'nama_perusahaan'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan.($row->kode_proyek ? ' ('.$row->kode_proyek.')' : ''), 'display_label' => $row->nama_perusahaan])->values()->all(),
            'houseUnits' => DB::table('detail_rumahs')->whereNull('deleted_at')->orderBy('nomor_rumah')->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => 'Unit '.$row->nomor_rumah.($row->kode_nlok ? ' ('.$row->kode_nlok.')' : ''), 'display_label' => 'Unit '.$row->nomor_rumah, 'perumahan_id' => (string) $row->perumahan_id])->values()->all(),
        ];
    }

    private function formatListRow(string $section, array $row, array $options): array
    {
        if ($section === 'equipment') {
            $usage = DB::table('heavy_equipment_usages as usage')->join('heavy_equipment_operators as operator', 'operator.id', '=', 'usage.operator_id')->leftJoin('perumahans as proyek', 'proyek.id', '=', 'usage.perumahan_id')->leftJoin('detail_rumahs as rumah', 'rumah.id', '=', 'usage.detail_rumah_id')->where('usage.heavy_equipment_id', $row['id'])->where('usage.status', 'in_use')->whereNull('usage.deleted_at')->latest('usage.date')->first(['operator.name as operator_name', 'proyek.nama_perusahaan as project_name', 'rumah.nomor_rumah as house_number']);
            $row['current_assignment'] = $usage ? collect([$usage->operator_name, $usage->project_name, $usage->house_number ? 'Unit '.$usage->house_number : null])->filter()->join(' · ') : 'Tidak sedang digunakan';
        }
        if ($section === 'usage') {
            $row['work_assignment'] = collect([
                filled($row['perumahan_id'] ?? null) ? $this->optionLabel($options['perumahans'], $row['perumahan_id']) : null,
                filled($row['detail_rumah_id'] ?? null) ? $this->optionLabel($options['houseUnits'], $row['detail_rumah_id']) : null,
                $row['project'] ?? null,
            ])->filter()->unique()->join(' · ') ?: '-';
        }
        $relations = match ($section) {
            'equipment' => ['heavy_equipment_type_id' => 'types'],
            'components' => ['heavy_equipment_type_id' => 'types', 'heavy_equipment_id' => 'equipment'],
            'replacements' => ['heavy_equipment_id' => 'equipment', 'old_component_id' => 'components', 'new_component_id' => 'components', 'operator_id' => 'operators'],
            'usage' => ['heavy_equipment_id' => 'equipment', 'operator_id' => 'operators', 'perumahan_id' => 'perumahans', 'detail_rumah_id' => 'houseUnits'],
            'maintenance','damages','fuel' => ['heavy_equipment_id' => 'equipment'],
            default => [],
        };
        foreach ($relations as $column => $optionKey) {
            if (filled($row[$column] ?? null)) {
                $row[$column] = $this->optionLabel($options[$optionKey] ?? [], $row[$column]);
            }
        }

        return $row;
    }

    private function withArchive(string $section, array $row): array
    {
        if (! in_array($section, ['replacements', 'usage', 'maintenance', 'damages', 'fuel'], true)) {
            return $row;
        }$a = DB::table('operation_transaction_archives')->where(['module' => 'heavy', 'section' => $section, 'record_id' => $row['id']])->first();
        $p = $a ? DB::table('approval_requests')->where(['module_key' => "heavy-{$section}", 'model_id' => $a->id, 'status' => 'pending'])->latest('id')->first() : null;
        $can = $p ? app(ApprovalWorkflowService::class)->canReview(ApprovalRequest::find($p->id)) : false;

        return [...$row, 'archive_status' => $a?->status ?? 'draft', 'archive_document_no' => $a?->document_no, 'approval_step' => $p?->current_step, 'approval_total_steps' => $p?->total_steps, 'can_review' => $can];
    }

    private function optionLabel(array $options, mixed $value): string
    {
        $option = collect($options)->firstWhere('value', (string) $value);

        return $option['display_label'] ?? $option['label'] ?? (string) $value;
    }

    private function config(string $s): array
    {
        $t = fn ($n, $l, $r = true) => ['name' => $n, 'label' => $l, 'type' => 'text', 'required' => $r];
        $auto = fn ($n, $l) => ['name' => $n, 'label' => $l, 'type' => 'auto-code', 'required' => false];
        $n = fn ($n, $l, $r = true) => ['name' => $n, 'label' => $l, 'type' => 'number', 'required' => $r];
        $d = fn ($n, $l) => ['name' => $n, 'label' => $l, 'type' => 'date', 'required' => true];
        $o = fn ($n, $l, $k) => ['name' => $n, 'label' => $l, 'type' => 'select', 'optionsKey' => $k, 'required' => true];
        $columns = fn (...$definitions) => collect($definitions)->map(fn ($definition) => ['name' => $definition[0], 'label' => $definition[1], 'sortable' => $definition[2] ?? true])->all();

        return match ($s) {
            'dashboard','reports' => ['title' => $s === 'dashboard' ? 'Dashboard' : 'Laporan Alat Berat', 'table' => null, 'fields' => [], 'rules' => [], 'search' => []],
            'types' => ['title' => 'Jenis Alat', 'table' => 'heavy_equipment_types', 'fields' => [$t('name', 'Nama Jenis'), $t('description', 'Keterangan', false)], 'rules' => ['name' => 'required|string|max:255', 'description' => 'nullable|string'], 'search' => ['name', 'description']],
            'operators' => ['title' => 'Operator', 'table' => 'heavy_equipment_operators', 'fields' => [$t('name', 'Nama'), $t('phone', 'Nomor HP', false), $t('identity_no', 'Nomor Identitas', false), $t('certification', 'Sertifikasi', false)], 'rules' => ['name' => 'required|string', 'phone' => 'nullable|string', 'identity_no' => 'nullable|string', 'certification' => 'nullable|string'], 'search' => ['name', 'phone', 'identity_no', 'certification']],
            'equipment' => ['title' => 'Data Alat Berat', 'table' => 'heavy_equipments', 'columns' => $columns(['code', 'Kode'], ['name', 'Nama Alat'], ['heavy_equipment_type_id', 'Jenis'], ['status', 'Status'], ['current_assignment', 'Operator / Proyek / Unit', false], ['current_hour_meter', 'Hour Meter'], ['ownership', 'Kepemilikan']), 'fields' => [$auto('code', 'Kode Alat'), $t('name', 'Nama Alat'), $o('heavy_equipment_type_id', 'Jenis Alat', 'types'), $t('brand', 'Merk', false), $t('model', 'Model', false), $n('year', 'Tahun', false), $t('serial_no', 'Nomor Seri'), $t('license_plate', 'Nomor Polisi', false), $n('current_hour_meter', 'Hour Meter'), ['name' => 'ownership', 'label' => 'Kepemilikan', 'type' => 'select', 'options' => ['company' => 'Milik Perusahaan', 'rental' => 'Sewa'], 'required' => true], ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Aktif', 'in_use' => 'Digunakan', 'service' => 'Servis', 'damaged' => 'Rusak'], 'required' => true], $t('notes', 'Catatan', false)], 'rules' => ['code' => 'nullable|string', 'name' => 'required|string', 'heavy_equipment_type_id' => 'required|exists:heavy_equipment_types,id', 'brand' => 'nullable|string', 'model' => 'nullable|string', 'year' => 'nullable|integer|min:1900', 'serial_no' => 'required|string', 'license_plate' => 'nullable|string', 'current_hour_meter' => 'required|numeric|min:0', 'ownership' => ['required', Rule::in(['company', 'rental'])], 'status' => ['required', Rule::in(['active', 'in_use', 'service', 'damaged'])], 'notes' => 'nullable|string'], 'search' => ['code', 'name', 'brand', 'model', 'serial_no', 'license_plate', 'status']],
            'components' => ['title' => 'Komponen Alat Berat', 'table' => 'heavy_equipment_components', 'fields' => [$auto('code', 'Kode Komponen'), $t('name', 'Nama Komponen'), $o('heavy_equipment_type_id', 'Jenis Alat', 'types'), ['name' => 'heavy_equipment_id', 'label' => 'Alat Terpasang', 'type' => 'select', 'optionsKey' => 'equipment', 'required' => false], $t('component_type', 'Tipe Komponen'), $t('serial_no', 'Nomor Seri', false), $t('condition', 'Kondisi'), ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['installed' => 'Terpasang', 'available' => 'Tersedia', 'service' => 'Servis', 'damaged' => 'Rusak'], 'required' => true], $t('storage_location', 'Lokasi Penyimpanan', false)], 'rules' => ['code' => 'nullable|string', 'name' => 'required|string', 'heavy_equipment_type_id' => 'required|exists:heavy_equipment_types,id', 'heavy_equipment_id' => 'nullable|required_if:status,installed|exists:heavy_equipments,id', 'component_type' => 'required|string', 'serial_no' => 'nullable|string', 'condition' => 'required|string', 'status' => ['required', Rule::in(['installed', 'available', 'service', 'damaged'])], 'storage_location' => 'nullable|string'], 'search' => ['code', 'name', 'component_type', 'serial_no', 'status']],
            'replacements' => ['title' => 'Penggantian Komponen', 'table' => 'heavy_component_replacements', 'fields' => [$auto('transaction_no', 'Nomor Transaksi'), $d('date', 'Tanggal'), $o('heavy_equipment_id', 'Alat Berat', 'equipment'), $o('old_component_id', 'Komponen Lama', 'components'), $o('new_component_id', 'Komponen Baru', 'components'), $n('hour_meter', 'Hour Meter'), $t('reason', 'Alasan'), $o('operator_id', 'Operator', 'operators'), $t('technician', 'Teknisi', false), $t('old_component_condition', 'Kondisi Komponen Lama'), ['name' => 'old_component_status', 'label' => 'Status Komponen Lama', 'type' => 'select', 'options' => ['available' => 'Tersedia', 'service' => 'Servis', 'damaged' => 'Rusak'], 'required' => true]], 'rules' => ['transaction_no' => 'nullable|string', 'date' => 'required|date', 'heavy_equipment_id' => 'required|exists:heavy_equipments,id', 'old_component_id' => 'nullable|different:new_component_id|exists:heavy_equipment_components,id', 'new_component_id' => 'required|exists:heavy_equipment_components,id', 'hour_meter' => 'required|numeric|min:0', 'reason' => 'required|string', 'operator_id' => 'nullable|exists:heavy_equipment_operators,id', 'technician' => 'nullable|string', 'old_component_condition' => 'nullable|string', 'old_component_status' => ['required', Rule::in(['available', 'service', 'damaged'])]], 'search' => ['transaction_no', 'reason', 'technician']],
            'usage' => ['title' => 'Penggunaan Alat', 'table' => 'heavy_equipment_usages', 'columns' => $columns(['transaction_no', 'Nomor'], ['date', 'Tanggal'], ['heavy_equipment_id', 'Nama Alat'], ['operator_id', 'Operator'], ['work_assignment', 'Perumahan / Unit / Lokasi', false], ['hour_meter_start', 'HM Awal'], ['hour_meter_end', 'HM Akhir'], ['status', 'Status']), 'fields' => [$auto('transaction_no', 'Nomor Transaksi'), $d('date', 'Tanggal'), $o('heavy_equipment_id', 'Alat Berat', 'equipment'), $o('operator_id', 'Operator', 'operators'), ['name' => 'perumahan_id', 'label' => 'Perumahan / Proyek', 'type' => 'select', 'optionsKey' => 'perumahans', 'required' => false], ['name' => 'detail_rumah_id', 'label' => 'Unit Rumah', 'type' => 'select', 'optionsKey' => 'houseUnits', 'required' => false], $t('project', 'Lokasi Kerja Tambahan', false), $n('hour_meter_start', 'Hour Meter Awal'), $n('hour_meter_end', 'Hour Meter Akhir', false), $t('description', 'Keterangan', false)], 'rules' => ['transaction_no' => 'nullable|string', 'date' => 'required|date', 'heavy_equipment_id' => 'required|exists:heavy_equipments,id', 'operator_id' => 'required|exists:heavy_equipment_operators,id', 'perumahan_id' => 'nullable|exists:perumahans,id', 'detail_rumah_id' => 'nullable|exists:detail_rumahs,id', 'project' => 'nullable|string', 'hour_meter_start' => 'required|numeric|min:0', 'hour_meter_end' => 'nullable|numeric|min:0', 'description' => 'nullable|string'], 'search' => ['transaction_no', 'project', 'status']],
            'maintenance' => ['title' => 'Maintenance', 'table' => 'heavy_equipment_maintenances', 'fields' => [$auto('maintenance_no', 'Nomor Maintenance'), $d('date', 'Tanggal'), $o('heavy_equipment_id', 'Alat Berat', 'equipment'), $t('maintenance_type', 'Jenis Maintenance'), $t('workshop', 'Bengkel', false), $n('cost', 'Biaya'), ['name' => 'next_schedule', 'label' => 'Jadwal Berikutnya', 'type' => 'date', 'required' => false], $t('notes', 'Catatan', false), ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['scheduled' => 'Dijadwalkan', 'in_progress' => 'Dikerjakan', 'completed' => 'Selesai'], 'required' => true]], 'rules' => ['maintenance_no' => 'nullable|string', 'date' => 'required|date', 'heavy_equipment_id' => 'required|exists:heavy_equipments,id', 'maintenance_type' => 'required|string', 'workshop' => 'nullable|string', 'cost' => 'required|numeric|min:0', 'next_schedule' => 'nullable|date', 'notes' => 'nullable|string', 'status' => 'required|string'], 'search' => ['maintenance_no', 'maintenance_type', 'workshop', 'status']],
            'damages' => ['title' => 'Kerusakan', 'table' => 'heavy_equipment_damages', 'fields' => [$d('date', 'Tanggal'), $o('heavy_equipment_id', 'Alat Berat', 'equipment'), $t('description', 'Deskripsi Kerusakan'), $t('severity', 'Tingkat Kerusakan'), $t('repair_status', 'Status Perbaikan'), ['name' => 'completed_date', 'label' => 'Tanggal Selesai', 'type' => 'date', 'required' => false], $t('notes', 'Catatan', false)], 'rules' => ['date' => 'required|date', 'heavy_equipment_id' => 'required|exists:heavy_equipments,id', 'description' => 'required|string', 'severity' => 'required|string', 'repair_status' => 'required|string', 'completed_date' => 'nullable|date', 'notes' => 'nullable|string'], 'search' => ['description', 'severity', 'repair_status']],
            'fuel' => ['title' => 'Pengisian BBM', 'table' => 'heavy_equipment_fuelings', 'fields' => [$d('date','Tanggal'), $o('heavy_equipment_id','Alat Berat','equipment'), $t('fuel_type','Jenis BBM'), $n('liters','Liter'), $n('price_per_liter','Harga per Liter'), $n('hour_meter','Hour Meter'), $t('notes','Catatan',false)], 'rules' => ['date' => 'required|date', 'heavy_equipment_id' => 'required|exists:heavy_equipments,id', 'fuel_type' => 'required|string', 'liters' => 'required|numeric|min:0.01', 'price_per_liter' => 'required|numeric|min:0', 'hour_meter' => 'required|numeric|min:0', 'notes' => 'nullable|string'], 'search' => ['fuel_type', 'notes']],default => abort(404)
        };
    }

    private function can(string $a): bool
    {
        $section = (string) (request()->route('section') ?? 'equipment');

        return (bool) (auth()->user()?->can("heavy-equipment.{$section}.{$a}") || auth()->user()?->hasRole('super_admin'));
    }

    private function authorizeModule(string $a): void
    {
        abort_unless($this->can($a),403);
    }
}
