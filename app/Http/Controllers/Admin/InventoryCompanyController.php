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

class InventoryCompanyController extends Controller
{
    public function storeDivision(Request $request)
    {
        $this->authorizeModule('create', 'locations');
        $data = $request->validate(['name' => 'required|string|max:100']);
        $id = DB::table('inventory_divisions')->updateOrInsert(['name' => $data['name']], ['is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
        $row = DB::table('inventory_divisions')->where('name', $data['name'])->first();

        return response()->json(['value' => (string) $row->id, 'label' => $row->name], $id ? 201 : 200);
    }

    public function index(Request $request, string $section = 'dashboard')
    {
        $this->authorizeModule('view', $section);
        if ($section === 'reports') {
            return $this->reportPage($request);
        }
        if ($section === 'ledger') {
            return $this->ledgerPage($request);
        }
        $config = $this->config($section);
        $search = trim((string) $request->query('search'));
        $sortable = array_merge(['id'], array_column($config['fields'], 'name'));
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $query = $config['table'] ? DB::table($config['table'])->whereNull('deleted_at') : null;
        if ($query && $search !== '') {
            $query->where(fn (Builder $q) => collect($config['search'])->each(fn ($col, $i) => $i ? $q->orWhere($col, 'like', "%{$search}%") : $q->where($col, 'like', "%{$search}%")));
        }
        $options = $this->options();

        return Inertia::render('Admin/OperationsModule/Index', [
            'title' => 'Inventaris Perusahaan', 'module' => 'inventory', 'section' => $section, 'sectionTitle' => $config['title'],
            'baseUrl' => '/admin/inventaris-perusahaan', 'menu' => $this->menu(), 'fields' => $config['fields'], 'columns' => $config['columns'] ?? null, 'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
            'rows' => $query ? $query->orderBy($sort, $direction)->paginate(15)->withQueryString()->through(fn ($row) => $this->withArchive($section, $this->formatListRow($section, (array) $row, $options))) : ['data' => [], 'links' => []],
            'summary' => $this->summary(), 'dashboardData' => $section === 'dashboard' ? $this->dashboardData() : null, 'options' => $options,
            'permissions' => ['create' => $this->can('create', $section), 'update' => ! in_array($section, ['loans', 'returns'], true) && $this->can('update', $section), 'delete' => ! in_array($section, ['loans', 'returns'], true) && $this->can('delete', $section), 'export' => $this->can('export', $section), 'verify' => $this->can('verify', $section), 'approve' => $this->can('approve', $section), 'print' => $this->can('print', $section)],
        ]);
    }

    private function withArchive(string $section, array $row): array
    {
        if (! in_array($section, ['receipts', 'loans', 'returns', 'transfers', 'damages', 'losses', 'stock-opname'], true)) {
            return $row;
        }
        $archive = DB::table('operation_transaction_archives')->where(['module' => 'inventory', 'section' => $section, 'record_id' => $row['id']])->first();
        $approval = $archive ? DB::table('approval_requests')->where(['module_key' => "inventory-{$section}", 'model_id' => $archive->id, 'status' => 'pending'])->latest('id')->first() : null;
        $canReview = $approval ? app(ApprovalWorkflowService::class)->canReview(ApprovalRequest::find($approval->id)) : false;

        return [...$row, 'archive_status' => $archive?->status ?? 'draft', 'archive_document_no' => $archive?->document_no, 'approval_step' => $approval?->current_step, 'approval_total_steps' => $approval?->total_steps, 'can_review' => $canReview];
    }

    public function create(Request $request, string $section)
    {
        $this->authorizeModule('create', $section);
        if ($section === 'loans') {
            return $this->loanFormPage();
        }
        if ($section === 'returns') {
            return $this->returnFormPage($request);
        }

        return $this->formPage($section);
    }

    public function edit(Request $request, string $section, string $id)
    {
        $this->authorizeModule('update', $section);
        abort_if(in_array($section, ['loans', 'returns'], true), 422, 'Transaksi yang sudah diposting tidak dapat diedit langsung. Gunakan transaksi pengembalian atau koreksi agar audit stok tetap utuh.');

        return $this->formPage($section, $id);
    }

    public function show(string $section, string $id)
    {
        $this->authorizeModule('view', $section);
        abort_unless(in_array($section, ['items', 'units', 'loans'], true), 404);

        return Inertia::render('Admin/Inventory/Show', $this->detailData($section, $id));
    }

    public function store(Request $request, string $section)
    {
        $this->authorizeModule('create', $section);
        if ($section === 'loans') {
            return $this->storeLoan($request);
        }
        if ($section === 'returns') {
            return $this->storeReturn($request);
        }

        $config = $this->config($section);
        $data = $request->validate($config['rules']);
        DB::transaction(function () use ($section, $config, $data) {
            $payload = [...$data, 'created_by' => auth()->id(), 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()];
            $this->fillAutomaticCode($section, $payload);
            $this->validateRelatedData($section, $payload);
            $this->storeTransaction($section, $config['table'], $payload);
        });

        return redirect("/admin/inventaris-perusahaan/{$section}")->with('success', $config['title'].' berhasil disimpan.');
    }

    public function update(Request $request, string $section, string $id)
    {
        $this->authorizeModule('update', $section);
        $config = $this->config($section);
        $data = $request->validate($config['rules']);

        DB::transaction(function () use ($section, $config, $data, $id): void {
            $current = DB::table($config['table'])->lockForUpdate()->where('id', $id)->whereNull('deleted_at')->first();
            abort_if(! $current, 404);

            $payload = [...$data, 'updated_by' => auth()->id(), 'updated_at' => now()];
            unset($payload['initial_location_id']);
            $this->validateRelatedData($section, $payload);

            if ($section === 'items' && ($payload['inventory_type'] ?? null) === 'unit') {
                unset($payload['total_stock'], $payload['available_stock']);
            }

            if ($section === 'units') {
                // Status unit bergerak melalui transaksi pinjam/rusak/hilang, bukan diedit manual.
                $payload['status'] = $current->status;
            }

            DB::table($config['table'])->where('id', $id)->update($payload);

            if ($section === 'units') {
                $this->syncUnitItemStock((int) $current->inventory_item_id);
                $this->syncUnitItemStock((int) $payload['inventory_item_id']);
            } elseif ($section === 'items' && ($payload['inventory_type'] ?? null) === 'unit') {
                $this->syncUnitItemStock((int) $id);
            }
        });

        return redirect("/admin/inventaris-perusahaan/{$section}")->with('success', $config['title'].' berhasil diperbarui.');
    }

    public function destroy(string $section, string $id)
    {
        $this->authorizeModule('delete', $section);
        $config = $this->config($section);

        DB::transaction(function () use ($section, $id, $config): void {
            $row = DB::table($config['table'])->lockForUpdate()->where('id', $id)->whereNull('deleted_at')->first();
            abort_if(! $row, 404);
            abort_if($section === 'units' && $row->status === 'borrowed', 422, 'Unit yang sedang dipinjam tidak dapat diarsipkan. Kembalikan unit terlebih dahulu.');
            abort_if($section === 'items' && DB::table('office_assets')->whereNull('deleted_at')->where('inventory_item_id', $id)->exists(), 422, 'Barang masih mempunyai unit aset aktif dan tidak dapat diarsipkan.');

            DB::table($config['table'])->where('id', $id)->update(['deleted_at' => now(), 'updated_by' => auth()->id(), 'updated_at' => now()]);

            if ($section === 'units') {
                $this->syncUnitItemStock((int) $row->inventory_item_id);
            }
        });

        return back()->with('success', 'Data diarsipkan dan histori tetap tersimpan.');
    }

    public function verifyStockOpname(string $id)
    {
        $this->authorizeModule('verify', 'stock-opname');
        DB::transaction(function () use ($id) {
            $opname = DB::table('inventory_stock_opnames')->lockForUpdate()->where('id', $id)->where('status', 'draft')->first();
            abort_if(! $opname, 422, 'Stock opname sudah diverifikasi atau tidak ditemukan.');
            foreach (DB::table('inventory_stock_opname_items')->where('inventory_stock_opname_id', $id)->get() as $line) {
                $item = DB::table('inventory_items')->lockForUpdate()->find($line->inventory_item_id);
                abort_if($item->inventory_type === 'unit' && (int) $line->difference !== 0, 422, 'Selisih barang berbasis unit harus diselesaikan melalui data Unit Aset agar kode aset dan histori tidak hilang.');
                if ($item->inventory_type === 'quantity') {
                    $this->adjustLocationStock((int) $item->id, $opname->inventory_location_id ? (int) $opname->inventory_location_id : null, ['total_stock' => (int) $line->difference, 'available_stock' => (int) $line->difference]);
                    $this->syncQuantityItemStock((int) $item->id);
                    if ((int) $line->difference !== 0) {
                        $this->recordMovement([
                            'movement_type' => 'stock_opname_adjustment', 'reference_type' => 'inventory_stock_opname', 'reference_id' => $opname->id, 'reference_no' => $opname->opname_no,
                            'inventory_item_id' => $item->id, 'office_asset_id' => null, 'from_location_id' => $opname->inventory_location_id, 'to_location_id' => $opname->inventory_location_id,
                            'quantity' => abs((int) $line->difference), 'condition_bucket' => 'available', 'notes' => 'Selisih stock opname: '.$line->difference,
                        ]);
                    }
                }
            }
            DB::table('inventory_stock_opnames')->where('id', $id)->update(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now(), 'updated_by' => auth()->id(), 'updated_at' => now()]);
        });

        return back()->with('success', 'Stock opname diverifikasi dan selisih stok diterapkan.');
    }

    public function export(Request $request, string $section, string $format)
    {
        $this->authorizeModule('export', $section);
        if ($section === 'reports') {
            return $this->exportReport($request, $format);
        }
        $config = $this->config($section);
        $rows = DB::table($config['table'])->whereNull('deleted_at')->get();
        if ($format === 'pdf') {
            return Pdf::loadView('reports.module-table', ['title' => $config['title'], 'rows' => $rows, 'printedAt' => now()->format('d/m/Y H:i')])->setPaper('a4', 'landscape')->download('inventaris-'.$section.'.pdf');
        }
        $html = view('reports.module-table', ['title' => $config['title'], 'rows' => $rows, 'printedAt' => now()->format('d/m/Y H:i')])->render();

        return response($html)->header('Content-Type', 'application/vnd.ms-excel')->header('Content-Disposition', 'attachment; filename="inventaris-'.$section.'.xls"');
    }

    private function loanFormPage()
    {
        return Inertia::render('Admin/Inventory/LoanForm', [
            'title' => 'Transaksi Pengambilan Barang',
            'indexUrl' => '/admin/inventaris-perusahaan/loans',
            'actionUrl' => '/admin/inventaris-perusahaan/loans/records',
            'options' => $this->options(),
            'defaults' => [
                'date' => now()->toDateString(),
                'transaction_type' => 'loan',
                'transaction_no' => '',
            ],
        ]);
    }

    private function returnFormPage(Request $request)
    {
        $transactions = DB::table('inventory_loans as loans')
            ->whereNull('loans.deleted_at')
            ->whereIn('loans.status', ['borrowed', 'assigned', 'partially_returned', 'overdue'])
            ->latest('loans.date')
            ->get(['loans.id', 'loans.transaction_no', 'loans.borrower', 'loans.taken_by_name', 'loans.source_location_id', 'loans.status'])
            ->map(function ($loan): array {
                $items = DB::table('inventory_loan_items as lines')
                    ->join('inventory_items as items', 'items.id', '=', 'lines.inventory_item_id')
                    ->leftJoin('office_assets as assets', 'assets.id', '=', 'lines.office_asset_id')
                    ->where('lines.inventory_loan_id', $loan->id)
                    ->whereColumn('lines.returned_quantity', '<', 'lines.quantity')
                    ->get([
                        'lines.id as loan_item_id', 'lines.quantity', 'lines.returned_quantity', 'lines.condition_out',
                        'items.id as inventory_item_id', 'items.name as item_name', 'items.code as item_code',
                        'items.inventory_type', 'items.unit', 'assets.kode_aset',
                    ])
                    ->map(fn ($line) => [
                        ...(array) $line,
                        'outstanding_quantity' => (int) $line->quantity - (int) $line->returned_quantity,
                    ])->values()->all();

                return [
                    'value' => (string) $loan->id,
                    'label' => collect([$loan->borrower, $loan->taken_by_name, $loan->transaction_no])->filter()->unique()->join(' · '),
                    'transaction_no' => $loan->transaction_no,
                    'borrower' => $loan->borrower,
                    'taken_by_name' => $loan->taken_by_name,
                    'source_location_id' => $loan->source_location_id ? (string) $loan->source_location_id : '',
                    'status' => $loan->status,
                    'items' => $items,
                ];
            })->filter(fn ($loan) => count($loan['items']) > 0)->values()->all();

        return Inertia::render('Admin/Inventory/ReturnForm', [
            'title' => 'Pengembalian dan Pemeriksaan Barang',
            'indexUrl' => '/admin/inventaris-perusahaan/returns',
            'actionUrl' => '/admin/inventaris-perusahaan/returns/records',
            'locations' => $this->options()['locations'],
            'transactions' => $transactions,
            'selectedLoanId' => (string) $request->query('loan', ''),
            'today' => now()->toDateString(),
        ]);
    }

    private function storeLoan(Request $request)
    {
        $legacyItem = $request->filled('inventory_item_id') ? [[
            'inventory_item_id' => $request->input('inventory_item_id'),
            'office_asset_id' => $request->input('office_asset_id'),
            'quantity' => $request->input('quantity', 1),
            'condition_out' => $request->input('condition_out', 'good'),
            'notes' => null,
        ]] : null;
        $request->merge([
            'taken_by_name' => $request->input('taken_by_name') ?: $request->input('borrower'),
            'transaction_type' => $request->input('transaction_type', 'loan'),
            'planned_return_date' => $request->input('planned_return_date') ?: ($request->input('transaction_type', 'loan') === 'consumption' ? null : now()->addDays(7)->toDateString()),
            'items' => $request->input('items', $legacyItem),
        ]);

        $data = $request->validate([
            'transaction_no' => 'nullable|string|max:100',
            'transaction_type' => ['required', Rule::in(['loan', 'placement', 'consumption'])],
            'date' => 'required|date',
            'borrower' => 'required|string|max:255',
            'taken_by_name' => 'required|string|max:255',
            'taken_by_phone' => 'nullable|string|max:50',
            'inventory_division_id' => 'nullable|exists:inventory_divisions,id',
            'source_location_id' => 'nullable|exists:inventory_locations,id',
            'inventory_location_id' => 'required|exists:inventory_locations,id',
            'perumahan_id' => 'nullable|exists:perumahans,id',
            'detail_rumah_id' => 'nullable|exists:detail_rumahs,id',
            'planned_return_date' => 'nullable|required_unless:transaction_type,consumption|date|after_or_equal:date',
            'purpose' => 'required|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.office_asset_id' => 'nullable|exists:office_assets,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.condition_out' => ['required', Rule::in(['good', 'fair', 'needs_service', 'damaged'])],
            'items.*.notes' => 'nullable|string',
        ]);

        if (filled($data['detail_rumah_id'] ?? null)) {
            $house = DB::table('detail_rumahs')->whereNull('deleted_at')->find($data['detail_rumah_id']);
            abort_if(! $house || (filled($data['perumahan_id'] ?? null) && (int) $house->perumahan_id !== (int) $data['perumahan_id']), 422, 'Unit rumah tidak sesuai dengan perumahan yang dipilih.');
            $data['perumahan_id'] = $house->perumahan_id;
        }
        if (filled($data['inventory_division_id'] ?? null)) {
            $data['division'] = DB::table('inventory_divisions')->where('id', $data['inventory_division_id'])->value('name');
        }

        $loanId = DB::transaction(function () use ($data): int {
            $items = $data['items'];
            unset($data['items']);
            $this->fillAutomaticCode('loans', $data);
            $data['status'] = match ($data['transaction_type']) {
                'placement' => 'assigned',
                'consumption' => 'consumed',
                default => 'borrowed',
            };
            if (blank($data['source_location_id'] ?? null)) {
                $firstLine = $items[0];
                $data['source_location_id'] = filled($firstLine['office_asset_id'] ?? null)
                    ? DB::table('office_assets')->whereNull('deleted_at')->where('id', $firstLine['office_asset_id'])->value('inventory_location_id')
                    : DB::table('inventory_location_stocks')->where('inventory_item_id', $firstLine['inventory_item_id'])->where('available_stock', '>=', (int) $firstLine['quantity'])->orderByDesc('available_stock')->value('inventory_location_id');
            }
            abort_if(blank($data['source_location_id'] ?? null), 422, 'Lokasi asal stok tidak dapat ditentukan. Pilih lokasi/gudang asal.');
            $data['handed_over_by'] = auth()->id();
            $data['handed_over_at'] = now();
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
            $data['created_at'] = now();
            $data['updated_at'] = now();

            $loanId = DB::table('inventory_loans')->insertGetId($data);
            $usedAssets = [];

            foreach ($items as $index => $line) {
                $item = DB::table('inventory_items')->lockForUpdate()->whereNull('deleted_at')->find($line['inventory_item_id']);
                abort_if(! $item, 422, 'Barang pada baris '.($index + 1).' tidak ditemukan.');
                $quantity = (int) $line['quantity'];
                $assetId = $line['office_asset_id'] ?? null;
                $fromLocationId = $data['source_location_id'] ?? null;

                if ($item->inventory_type === 'unit') {
                    abort_if($data['transaction_type'] === 'consumption', 422, $item->name.' merupakan Unit Aset dan tidak dapat diproses sebagai barang habis pakai.');
                    abort_if(! $assetId, 422, 'Pilih Unit Aset untuk '.$item->name.'.');
                    abort_if(in_array((int) $assetId, $usedAssets, true), 422, 'Unit Aset yang sama tidak boleh dimasukkan dua kali.');
                    $asset = DB::table('office_assets')->lockForUpdate()->whereNull('deleted_at')->find($assetId);
                    abort_if(! $asset || (int) $asset->inventory_item_id !== (int) $item->id || $asset->status !== 'available', 422, 'Unit Aset '.$item->name.' tidak tersedia atau tidak sesuai.');
                    abort_if(filled($fromLocationId) && (int) $asset->inventory_location_id !== (int) $fromLocationId, 422, 'Unit Aset '.$item->name.' tidak berada di lokasi asal transaksi.');
                    $usedAssets[] = (int) $assetId;
                    $quantity = 1;
                    $fromLocationId = $asset->inventory_location_id;
                    DB::table('office_assets')->where('id', $assetId)->update([
                        'status' => 'borrowed',
                        'inventory_location_id' => $data['inventory_location_id'],
                        'updated_by' => auth()->id(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $fromLocationId ??= DB::table('inventory_location_stocks')->where('inventory_item_id', $item->id)->where('available_stock', '>=', $quantity)->orderByDesc('available_stock')->value('inventory_location_id');
                    abort_if(! $fromLocationId, 422, 'Stok '.$item->name.' tidak tersedia pada lokasi asal yang dipilih.');
                    if ($data['transaction_type'] === 'consumption') {
                        $this->adjustLocationStock((int) $item->id, (int) $fromLocationId, ['total_stock' => -$quantity, 'available_stock' => -$quantity]);
                    } else {
                        $this->adjustLocationStock((int) $item->id, (int) $fromLocationId, ['total_stock' => -$quantity, 'available_stock' => -$quantity]);
                        $this->adjustLocationStock((int) $item->id, (int) $data['inventory_location_id'], ['total_stock' => $quantity, 'borrowed_stock' => $quantity]);
                    }
                    $this->syncQuantityItemStock((int) $item->id);
                }

                DB::table('inventory_loan_items')->insert([
                    'inventory_loan_id' => $loanId,
                    'inventory_item_id' => $item->id,
                    'office_asset_id' => $assetId,
                    'quantity' => $quantity,
                    'condition_out' => $line['condition_out'] ?? 'good',
                    'returned_quantity' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->recordMovement([
                    'movement_type' => $data['transaction_type'] === 'consumption' ? 'consumption' : 'issue',
                    'reference_type' => 'inventory_loan', 'reference_id' => $loanId, 'reference_no' => $data['transaction_no'],
                    'inventory_item_id' => $item->id, 'office_asset_id' => $assetId,
                    'from_location_id' => $fromLocationId, 'to_location_id' => $data['inventory_location_id'],
                    'quantity' => $quantity, 'condition_bucket' => $data['transaction_type'] === 'consumption' ? 'consumed' : 'borrowed',
                    'notes' => $line['notes'] ?? $data['purpose'],
                ]);

                if ($item->inventory_type === 'unit') {
                    $this->syncUnitItemStock((int) $item->id);
                }
            }

            return $loanId;
        });

        return redirect("/admin/inventaris-perusahaan/loans/records/{$loanId}")->with('success', 'Transaksi pengambilan multi-barang berhasil diposting dan stok telah diperbarui.');
    }

    private function storeReturn(Request $request)
    {
        $request->merge(['date' => $request->input('date', now()->toDateString())]);
        $data = $request->validate([
            'return_no' => 'nullable|string|max:100',
            'inventory_loan_id' => 'required|exists:inventory_loans,id',
            'date' => 'required|date',
            'return_location_id' => 'nullable|exists:inventory_locations,id',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.loan_item_id' => 'required|exists:inventory_loan_items,id',
            'items.*.good_quantity' => 'nullable|integer|min:0',
            'items.*.damaged_quantity' => 'nullable|integer|min:0',
            'items.*.lost_quantity' => 'nullable|integer|min:0',
            'items.*.condition_in' => ['required', Rule::in(['good', 'fair', 'needs_service', 'damaged', 'lost'])],
            'items.*.notes' => 'nullable|string',
            'items.*.responsible_person' => 'nullable|string|max:255',
            'items.*.estimated_cost' => 'nullable|numeric|min:0',
        ]);

        $returnId = DB::transaction(function () use ($data): int {
            $loan = DB::table('inventory_loans')->lockForUpdate()->whereNull('deleted_at')->find($data['inventory_loan_id']);
            abort_if(! $loan || ! in_array($loan->status, ['borrowed', 'assigned', 'partially_returned', 'overdue'], true), 422, 'Transaksi sudah selesai atau tidak dapat dikembalikan.');

            $submittedItems = collect($data['items'] ?? []);
            if ($submittedItems->isEmpty()) {
                // Kompatibilitas transaksi lama: tanpa rincian berarti seluruh sisa kembali dalam kondisi baik.
                $submittedItems = DB::table('inventory_loan_items')->where('inventory_loan_id', $loan->id)->get()->map(fn ($line) => [
                    'loan_item_id' => $line->id,
                    'good_quantity' => max(0, (int) $line->quantity - (int) $line->returned_quantity),
                    'damaged_quantity' => 0, 'lost_quantity' => 0, 'condition_in' => 'good',
                ]);
            }

            $processable = $submittedItems->filter(fn ($line) => ((int) ($line['good_quantity'] ?? 0) + (int) ($line['damaged_quantity'] ?? 0) + (int) ($line['lost_quantity'] ?? 0)) > 0);
            abort_if($processable->isEmpty(), 422, 'Isi minimal satu jumlah barang yang dikembalikan, rusak, atau hilang.');

            $returnNo = filled($data['return_no'] ?? null) ? $data['return_no'] : $this->nextAutomaticCode('inventory_returns', 'return_no', 'KMB');
            $returnId = DB::table('inventory_returns')->insertGetId([
                'return_no' => $returnNo, 'inventory_loan_id' => $loan->id, 'date' => $data['date'],
                'return_location_id' => $data['return_location_id'] ?? $loan->source_location_id,
                'received_by' => auth()->id(), 'received_at' => now(), 'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(), 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($processable as $index => $submitted) {
                $line = DB::table('inventory_loan_items')->lockForUpdate()->find($submitted['loan_item_id']);
                abort_if(! $line || (int) $line->inventory_loan_id !== (int) $loan->id, 422, 'Detail pengembalian baris '.($index + 1).' tidak sesuai transaksi.');
                $item = DB::table('inventory_items')->lockForUpdate()->find($line->inventory_item_id);
                $outstanding = (int) $line->quantity - (int) $line->returned_quantity;
                $good = (int) ($submitted['good_quantity'] ?? 0);
                $damaged = (int) ($submitted['damaged_quantity'] ?? 0);
                $lost = (int) ($submitted['lost_quantity'] ?? 0);
                $processed = $good + $damaged + $lost;
                abort_if($processed > $outstanding, 422, 'Jumlah penyelesaian '.$item->name.' melebihi sisa '.$outstanding.'.');
                abort_if($item->inventory_type === 'unit' && $processed !== 1, 422, 'Unit Aset '.$item->name.' harus diselesaikan tepat satu unit.');

                $outcome = $lost > 0 ? 'lost' : ($damaged > 0 ? 'damaged' : ($processed < $outstanding ? 'partial_good' : 'complete_good'));
                DB::table('inventory_return_items')->insert([
                    'inventory_return_id' => $returnId, 'inventory_loan_item_id' => $line->id,
                    'quantity' => $processed, 'good_quantity' => $good, 'condition_in' => $submitted['condition_in'] ?? ($damaged ? 'damaged' : 'good'),
                    'outcome' => $outcome, 'is_complete' => $processed === $outstanding,
                    'damaged_quantity' => $damaged, 'lost_quantity' => $lost,
                    'notes' => $submitted['notes'] ?? null, 'responsible_person' => $submitted['responsible_person'] ?? null,
                    'estimated_cost' => $submitted['estimated_cost'] ?? 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('inventory_loan_items')->where('id', $line->id)->update(['returned_quantity' => (int) $line->returned_quantity + $processed, 'updated_at' => now()]);

                $returnLocationId = $data['return_location_id'] ?? $loan->source_location_id;
                if ($line->office_asset_id) {
                    $assetStatus = $lost ? 'lost' : ($damaged ? 'damaged' : 'available');
                    DB::table('office_assets')->where('id', $line->office_asset_id)->update([
                        'status' => $assetStatus, 'inventory_location_id' => $lost ? null : $returnLocationId,
                        'current_user_id' => null, 'condition' => $submitted['condition_in'] ?? ($damaged ? 'damaged' : 'good'),
                        'updated_by' => auth()->id(), 'updated_at' => now(),
                    ]);
                    $this->syncUnitItemStock((int) $item->id);
                } else {
                    $borrowedLocationId = $this->borrowedLocation((int) $item->id, $loan->inventory_location_id ? (int) $loan->inventory_location_id : null);
                    abort_if(! $borrowedLocationId, 422, 'Lokasi saldo pinjaman '.$item->name.' tidak ditemukan.');
                    $movingBack = $good + $damaged;
                    $this->adjustLocationStock((int) $item->id, $borrowedLocationId, [
                        'total_stock' => -$movingBack,
                        'borrowed_stock' => -$processed,
                        'lost_stock' => $lost,
                    ]);
                    if ($movingBack > 0) {
                        abort_if(! $returnLocationId, 422, 'Lokasi pengembalian wajib dipilih untuk '.$item->name.'.');
                        $this->adjustLocationStock((int) $item->id, (int) $returnLocationId, [
                            'total_stock' => $movingBack,
                            'available_stock' => $good,
                            'damaged_stock' => $damaged,
                        ]);
                    }
                    $this->syncQuantityItemStock((int) $item->id);
                }

                foreach ([['return_good', $good, 'available'], ['return_damaged', $damaged, 'damaged'], ['return_lost', $lost, 'lost']] as [$movementType, $quantity, $bucket]) {
                    if ($quantity < 1) {
                        continue;
                    }
                    $this->recordMovement([
                        'movement_type' => $movementType, 'reference_type' => 'inventory_return', 'reference_id' => $returnId, 'reference_no' => $returnNo,
                        'inventory_item_id' => $item->id, 'office_asset_id' => $line->office_asset_id,
                        'from_location_id' => $loan->inventory_location_id, 'to_location_id' => $bucket === 'lost' ? null : $returnLocationId,
                        'quantity' => $quantity, 'condition_bucket' => $bucket, 'notes' => $submitted['notes'] ?? $data['notes'] ?? null,
                    ]);
                }
            }

            $hasOutstanding = DB::table('inventory_loan_items')->where('inventory_loan_id', $loan->id)->whereColumn('returned_quantity', '<', 'quantity')->exists();
            $hasLost = DB::table('inventory_return_items as return_lines')->join('inventory_returns as returns', 'returns.id', '=', 'return_lines.inventory_return_id')->where('returns.inventory_loan_id', $loan->id)->where('return_lines.lost_quantity', '>', 0)->exists();
            $hasDamage = DB::table('inventory_return_items as return_lines')->join('inventory_returns as returns', 'returns.id', '=', 'return_lines.inventory_return_id')->where('returns.inventory_loan_id', $loan->id)->where('return_lines.damaged_quantity', '>', 0)->exists();
            $status = $hasOutstanding ? 'partially_returned' : ($hasLost ? 'closed_with_loss' : ($hasDamage ? 'closed_with_damage' : 'returned'));
            DB::table('inventory_loans')->where('id', $loan->id)->update(['status' => $status, 'updated_by' => auth()->id(), 'updated_at' => now()]);

            return $returnId;
        });

        return redirect('/admin/inventaris-perusahaan/returns')->with('success', "Pengembalian {$returnId} berhasil diposting dan seluruh status stok diperbarui.");
    }

    private function recordMovement(array $data): void
    {
        DB::table('inventory_movements')->insert([
            ...$data,
            'occurred_at' => now(),
            'performed_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reportPage(Request $request)
    {
        $report = $this->reportDataset($request);
        $options = $this->options();

        return Inertia::render('Admin/Inventory/Reports', [
            'title' => 'Laporan Pengambilan Inventaris',
            'baseUrl' => '/admin/inventaris-perusahaan/reports',
            'menu' => $this->menu(),
            'filters' => $report['filters'],
            'transactions' => $report['transactions'],
            'summary' => $report['summary'],
            'options' => [
                'items' => $options['items'], 'locations' => $options['locations'], 'perumahans' => $options['perumahans'],
                'statuses' => [
                    ['value' => 'borrowed', 'label' => 'Sedang Dipinjam'], ['value' => 'assigned', 'label' => 'Ditempatkan'],
                    ['value' => 'partially_returned', 'label' => 'Kembali Sebagian'], ['value' => 'returned', 'label' => 'Selesai'],
                    ['value' => 'closed_with_damage', 'label' => 'Selesai dengan Kerusakan'], ['value' => 'closed_with_loss', 'label' => 'Selesai dengan Kehilangan'],
                    ['value' => 'consumed', 'label' => 'Pemakaian Habis'],
                ],
            ],
        ]);
    }

    private function ledgerPage(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'date_from' => $request->query('date_from', now()->startOfMonth()->toDateString()),
            'date_to' => $request->query('date_to', now()->endOfMonth()->toDateString()),
            'inventory_item_id' => $request->query('inventory_item_id', ''),
            'inventory_location_id' => $request->query('inventory_location_id', ''),
            'movement_type' => $request->query('movement_type', ''),
        ];
        $query = DB::table('inventory_movements as movements')
            ->join('inventory_items as items', 'items.id', '=', 'movements.inventory_item_id')
            ->leftJoin('office_assets as assets', 'assets.id', '=', 'movements.office_asset_id')
            ->leftJoin('inventory_locations as source', 'source.id', '=', 'movements.from_location_id')
            ->leftJoin('inventory_locations as destination', 'destination.id', '=', 'movements.to_location_id')
            ->leftJoin('users as user', 'user.id', '=', 'movements.performed_by');
        if ($filters['date_from']) {
            $query->whereDate('movements.occurred_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('movements.occurred_at', '<=', $filters['date_to']);
        }
        if ($filters['inventory_item_id']) {
            $query->where('movements.inventory_item_id', $filters['inventory_item_id']);
        }
        if ($filters['inventory_location_id']) {
            $query->where(fn ($nested) => $nested->where('movements.from_location_id', $filters['inventory_location_id'])->orWhere('movements.to_location_id', $filters['inventory_location_id']));
        }
        if ($filters['movement_type']) {
            $query->where('movements.movement_type', $filters['movement_type']);
        }
        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($nested) => $nested->where('items.name', 'like', $term)->orWhere('movements.reference_no', 'like', $term)->orWhere('assets.kode_aset', 'like', $term));
        }
        $rows = $query->latest('movements.occurred_at')->latest('movements.id')->paginate(30, [
            'movements.id', 'movements.occurred_at', 'movements.movement_type', 'movements.reference_no',
            'items.name as item_name', 'items.code as item_code', 'items.unit', 'assets.kode_aset',
            'source.name as source_location', 'destination.name as destination_location',
            'movements.quantity', 'movements.condition_bucket', 'movements.notes', 'user.name as performed_by_name',
        ])->withQueryString();
        $options = $this->options();

        return Inertia::render('Admin/Inventory/Ledger', [
            'title' => 'Kartu Pergerakan Inventaris', 'baseUrl' => '/admin/inventaris-perusahaan/ledger',
            'menu' => $this->menu(), 'filters' => $filters, 'rows' => $rows,
            'options' => ['items' => $options['items'], 'locations' => $options['locations']],
        ]);
    }

    private function reportDataset(Request $request): array
    {
        $preset = $request->query('preset', 'month');
        [$defaultFrom, $defaultTo] = match ($preset) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'year' => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            'all' => [null, null],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
        $filters = [
            'preset' => $preset,
            'date_from' => $request->filled('date_from') ? $request->query('date_from') : $defaultFrom,
            'date_to' => $request->filled('date_to') ? $request->query('date_to') : $defaultTo,
            'search' => trim((string) $request->query('search')),
            'transaction_type' => $request->query('transaction_type', ''),
            'status' => $request->query('status', ''),
            'inventory_item_id' => $request->query('inventory_item_id', ''),
            'source_location_id' => $request->query('source_location_id', ''),
            'perumahan_id' => $request->query('perumahan_id', ''),
            'overdue' => $request->boolean('overdue'),
            'group_by' => $request->query('group_by', 'transaction'),
        ];

        $query = DB::table('inventory_loans as loans')
            ->leftJoin('inventory_locations as source', 'source.id', '=', 'loans.source_location_id')
            ->leftJoin('inventory_locations as destination', 'destination.id', '=', 'loans.inventory_location_id')
            ->leftJoin('perumahans as proyek', 'proyek.id', '=', 'loans.perumahan_id')
            ->leftJoin('detail_rumahs as rumah', 'rumah.id', '=', 'loans.detail_rumah_id')
            ->leftJoin('users as officer', 'officer.id', '=', 'loans.handed_over_by')
            ->whereNull('loans.deleted_at');
        if ($filters['date_from']) {
            $query->whereDate('loans.date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('loans.date', '<=', $filters['date_to']);
        }
        if ($filters['transaction_type']) {
            $query->where('loans.transaction_type', $filters['transaction_type']);
        }
        if ($filters['status']) {
            $query->where('loans.status', $filters['status']);
        }
        if ($filters['source_location_id']) {
            $query->where('loans.source_location_id', $filters['source_location_id']);
        }
        if ($filters['perumahan_id']) {
            $query->where('loans.perumahan_id', $filters['perumahan_id']);
        }
        if ($filters['overdue']) {
            $query->whereDate('loans.planned_return_date', '<', today())->whereIn('loans.status', ['borrowed', 'assigned', 'partially_returned', 'overdue']);
        }
        if ($filters['inventory_item_id']) {
            $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('inventory_loan_items as filtered_lines')->whereColumn('filtered_lines.inventory_loan_id', 'loans.id')->where('filtered_lines.inventory_item_id', $filters['inventory_item_id']));
        }
        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($nested) use ($term): void {
                $nested->where('loans.transaction_no', 'like', $term)->orWhere('loans.borrower', 'like', $term)->orWhere('loans.taken_by_name', 'like', $term)->orWhere('loans.purpose', 'like', $term)
                    ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('inventory_loan_items as search_lines')->join('inventory_items as search_items', 'search_items.id', '=', 'search_lines.inventory_item_id')->whereColumn('search_lines.inventory_loan_id', 'loans.id')->where('search_items.name', 'like', $term));
            });
        }

        $transactions = $query->latest('loans.date')->latest('loans.id')->limit(500)->get([
            'loans.id', 'loans.transaction_no', 'loans.transaction_type', 'loans.date', 'loans.borrower', 'loans.taken_by_name', 'loans.taken_by_phone',
            'loans.division', 'loans.planned_return_date', 'loans.purpose', 'loans.status', 'loans.handed_over_at',
            'source.name as source_location', 'destination.name as destination_location', 'proyek.nama_perusahaan as project_name',
            'rumah.nomor_rumah as house_number', 'officer.name as officer_name',
        ])->map(function ($transaction): array {
            $lines = DB::table('inventory_loan_items as lines')
                ->join('inventory_items as items', 'items.id', '=', 'lines.inventory_item_id')
                ->leftJoin('office_assets as assets', 'assets.id', '=', 'lines.office_asset_id')
                ->where('lines.inventory_loan_id', $transaction->id)
                ->get(['lines.inventory_item_id', 'items.name as item_name', 'items.code as item_code', 'items.unit', 'items.inventory_type', 'assets.kode_aset', 'lines.quantity', 'lines.returned_quantity', 'lines.condition_out']);
            $isOverdue = filled($transaction->planned_return_date) && $transaction->planned_return_date < today()->toDateString() && in_array($transaction->status, ['borrowed', 'assigned', 'partially_returned', 'overdue'], true);

            return [...(array) $transaction, 'is_overdue' => $isOverdue, 'items' => $lines->map(fn ($line) => (array) $line)->all()];
        })->values();

        $allLines = $transactions->flatMap(fn ($transaction) => $transaction['items']);
        $summary = [
            ['label' => 'Total Transaksi', 'value' => $transactions->count()],
            ['label' => 'Jenis Barang', 'value' => $allLines->pluck('inventory_item_id')->unique()->count()],
            ['label' => 'Jumlah Keluar', 'value' => $allLines->sum('quantity')],
            ['label' => 'Sudah Kembali', 'value' => $allLines->sum('returned_quantity')],
            ['label' => 'Belum Kembali', 'value' => $allLines->sum(fn ($line) => (int) $line['quantity'] - (int) $line['returned_quantity'])],
            ['label' => 'Transaksi Terlambat', 'value' => $transactions->where('is_overdue', true)->count()],
        ];

        return ['filters' => $filters, 'transactions' => $transactions->all(), 'summary' => $summary];
    }

    private function exportReport(Request $request, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'excel'], true), 404);
        $report = $this->reportDataset($request);
        $data = [...$report, 'title' => 'Laporan Pengambilan Inventaris', 'printedAt' => now()->format('d/m/Y H:i')];
        if ($format === 'pdf') {
            return Pdf::loadView('reports.inventory-issues', $data)->setPaper('a4', 'landscape')->download('laporan-pengambilan-inventaris.pdf');
        }

        return response(view('reports.inventory-issues', $data)->render())
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="laporan-pengambilan-inventaris.xls"');
    }

    private function storeTransaction(string $section, string $table, array $data): void
    {
        if ($section === 'items') {
            $initialLocationId = $data['initial_location_id'] ?? DB::table('inventory_locations')->whereNull('deleted_at')->orderBy('id')->value('id');
            unset($data['initial_location_id']);
            if (($data['inventory_type'] ?? null) === 'unit') {
                $data['total_stock'] = 0;
                $data['available_stock'] = 0;
                $data['borrowed_stock'] = 0;
                $data['damaged_stock'] = 0;
                $data['lost_stock'] = 0;
            }

            $itemId = DB::table($table)->insertGetId($data);

            if (($data['inventory_type'] ?? null) === 'quantity' && $initialLocationId) {
                DB::table('inventory_location_stocks')->insert([
                    'inventory_item_id' => $itemId,
                    'inventory_location_id' => $initialLocationId,
                    'total_stock' => $data['total_stock'],
                    'available_stock' => $data['available_stock'],
                    'borrowed_stock' => 0,
                    'damaged_stock' => 0,
                    'lost_stock' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        if ($section === 'units') {
            $data['status'] = 'available';
            DB::table($table)->insert($data);
            $this->syncUnitItemStock((int) $data['inventory_item_id']);

            return;
        }

        if ($section === 'receipts') {
            $item = DB::table('inventory_items')->lockForUpdate()->find($data['inventory_item_id']);
            $receiptId = DB::table($table)->insertGetId($data);
            if ($data['office_asset_id'] ?? null) {
                DB::table('office_assets')->where('id', $data['office_asset_id'])->update(['inventory_location_id' => $data['inventory_location_id'], 'status' => 'available', 'updated_by' => auth()->id(), 'updated_at' => now()]);
                $this->syncUnitItemStock((int) $item->id);
            } else {
                $this->adjustLocationStock((int) $item->id, (int) $data['inventory_location_id'], ['total_stock' => (int) $data['quantity'], 'available_stock' => (int) $data['quantity']]);
                $this->syncQuantityItemStock((int) $item->id);
            }
            $this->recordMovement(['movement_type' => 'receipt', 'reference_type' => 'inventory_receipt', 'reference_id' => $receiptId, 'reference_no' => $data['receipt_no'], 'inventory_item_id' => $item->id, 'office_asset_id' => $data['office_asset_id'] ?? null, 'from_location_id' => null, 'to_location_id' => $data['inventory_location_id'], 'quantity' => (int) $data['quantity'], 'condition_bucket' => 'available', 'notes' => $data['notes'] ?? null]);

            return;
        }

        if ($section === 'transfers') {
            $item = DB::table('inventory_items')->lockForUpdate()->find($data['inventory_item_id']);
            $transferId = DB::table($table)->insertGetId($data);
            if ($data['office_asset_id'] ?? null) {
                $asset = DB::table('office_assets')->lockForUpdate()->find($data['office_asset_id']);
                $data['from_location_id'] = $asset->inventory_location_id;
                DB::table('office_assets')->where('id', $asset->id)->update(['inventory_location_id' => $data['to_location_id'], 'updated_by' => auth()->id(), 'updated_at' => now()]);
            } else {
                $this->adjustLocationStock((int) $item->id, (int) $data['from_location_id'], ['total_stock' => -(int) $data['quantity'], 'available_stock' => -(int) $data['quantity']]);
                $this->adjustLocationStock((int) $item->id, (int) $data['to_location_id'], ['total_stock' => (int) $data['quantity'], 'available_stock' => (int) $data['quantity']]);
                $this->syncQuantityItemStock((int) $item->id);
            }
            $this->recordMovement([
                'movement_type' => 'transfer', 'reference_type' => 'inventory_transfer', 'reference_id' => $transferId, 'reference_no' => $data['transaction_no'],
                'inventory_item_id' => $item->id, 'office_asset_id' => $data['office_asset_id'] ?? null,
                'from_location_id' => $data['from_location_id'], 'to_location_id' => $data['to_location_id'],
                'quantity' => (int) $data['quantity'], 'condition_bucket' => 'available', 'notes' => $data['reason'] ?? null,
            ]);

            return;
        }
        if (in_array($section, ['damages', 'losses'], true)) {
            $qty = $section === 'losses' ? (int) $data['quantity'] : 1;
            $item = DB::table('inventory_items')->lockForUpdate()->find($data['inventory_item_id']);
            abort_if(! $item || $item->available_stock < $qty, 422, 'Stok tersedia tidak mencukupi untuk transaksi ini.');
            if ($data['office_asset_id'] ?? null) {
                $asset = DB::table('office_assets')->lockForUpdate()->find($data['office_asset_id']);
                abort_if(! $asset || (int) $asset->inventory_item_id !== (int) $item->id || $asset->status !== 'available', 422, 'Unit aset tidak tersedia atau tidak sesuai barang.');
                $qty = 1;
                DB::table('office_assets')->where('id', $asset->id)->update(['status' => $section === 'damages' ? 'damaged' : 'lost', 'updated_by' => auth()->id(), 'updated_at' => now()]);
            }
            $reportId = DB::table($table)->insertGetId($data);
            $counter = $section === 'damages' ? 'damaged_stock' : 'lost_stock';
            if ($data['office_asset_id'] ?? null) {
                $this->syncUnitItemStock((int) $item->id);
            } else {
                $locationId = $section === 'damages' ? ($data['inventory_location_id'] ?? null) : ($data['last_location_id'] ?? null);
                abort_if(! $locationId, 422, 'Lokasi barang wajib dipilih.');
                $this->adjustLocationStock((int) $item->id, (int) $locationId, ['available_stock' => -$qty, $counter => $qty]);
                $this->syncQuantityItemStock((int) $item->id);
            }
            $this->recordMovement([
                'movement_type' => $section === 'damages' ? 'damage' : 'loss', 'reference_type' => $section === 'damages' ? 'inventory_damage' : 'inventory_loss', 'reference_id' => $reportId, 'reference_no' => null,
                'inventory_item_id' => $item->id, 'office_asset_id' => $data['office_asset_id'] ?? null,
                'from_location_id' => $section === 'damages' ? ($data['inventory_location_id'] ?? null) : ($data['last_location_id'] ?? null), 'to_location_id' => null,
                'quantity' => $qty, 'condition_bucket' => $section === 'damages' ? 'damaged' : 'lost', 'notes' => $section === 'damages' ? $data['damage'] : $data['chronology'],
            ]);

            return;
        }
        if ($section === 'stock-opname') {
            $itemId = $data['inventory_item_id'];
            $physical = (int) $data['physical_quantity'];
            unset($data['inventory_item_id'],$data['physical_quantity']);
            $data['status'] = 'draft';
            $item = DB::table('inventory_items')->find($itemId);
            $systemQuantity = $item->inventory_type === 'quantity' ? (int) (DB::table('inventory_location_stocks')->where('inventory_item_id', $itemId)->where('inventory_location_id', $data['inventory_location_id'])->value('total_stock') ?? 0) : (int) DB::table('office_assets')->whereNull('deleted_at')->where('inventory_item_id', $itemId)->where('inventory_location_id', $data['inventory_location_id'])->count();
            $opnameId = DB::table($table)->insertGetId($data);
            DB::table('inventory_stock_opname_items')->insert(['inventory_stock_opname_id' => $opnameId, 'inventory_item_id' => $itemId, 'system_quantity' => $systemQuantity, 'physical_quantity' => $physical, 'difference' => $physical - $systemQuantity, 'created_at' => now(), 'updated_at' => now()]);

            return;
        }
        DB::table($table)->insert($data);
    }

    private function fillAutomaticCode(string $section, array &$data): void
    {
        $definition = $this->automaticCodeDefinition($section);

        if (! $definition || filled($data[$definition['column']] ?? null)) {
            return;
        }

        $data[$definition['column']] = $this->nextAutomaticCode(
            $definition['table'],
            $definition['column'],
            $definition['prefix'],
        );
    }

    private function validateRelatedData(string $section, array &$data): void
    {
        if ($section === 'locations') {
            if (($data['owner_type'] ?? 'company') !== 'branch') {
                $data['branch_id'] = null;
            }
            if (($data['owner_type'] ?? 'company') !== 'housing') {
                $data['perumahan_id'] = null;
            }

            return;
        }
        if (! in_array($section, ['units', 'receipts', 'transfers', 'damages', 'losses'], true)) {
            return;
        }

        $item = DB::table('inventory_items')->whereNull('deleted_at')->find($data['inventory_item_id'] ?? null);
        abort_if(! $item, 422, 'Barang inventaris tidak ditemukan.');

        if ($section === 'units') {
            abort_if($item->inventory_type !== 'unit', 422, 'Unit aset hanya dapat dibuat untuk barang bertipe unit.');

            return;
        }

        if ($section === 'receipts') {
            if ($item->inventory_type === 'unit') {
                abort_if(empty($data['office_asset_id']), 422, 'Unit aset wajib dipilih untuk barang berbasis unit.');
                $asset = DB::table('office_assets')->whereNull('deleted_at')->find($data['office_asset_id']);
                abort_if(! $asset || (int) $asset->inventory_item_id !== (int) $item->id, 422, 'Unit aset tidak sesuai barang.');
                $data['quantity'] = 1;
            } else {
                $data['office_asset_id'] = null;
            }

            return;
        }

        if ($item->inventory_type !== 'unit') {
            return;
        }

        abort_if(empty($data['office_asset_id']), 422, 'Unit aset wajib dipilih untuk barang bertipe unit.');
        $asset = DB::table('office_assets')->whereNull('deleted_at')->find($data['office_asset_id']);
        abort_if(! $asset || (int) $asset->inventory_item_id !== (int) $item->id || $asset->status !== 'available', 422, 'Unit aset tidak tersedia atau tidak sesuai barang.');

        if (array_key_exists('quantity', $data)) {
            $data['quantity'] = 1;
        }
    }

    private function adjustLocationStock(int $itemId, ?int $locationId, array $deltas): void
    {
        if (! $locationId) {
            return;
        }

        DB::table('inventory_location_stocks')->insertOrIgnore([
            'inventory_item_id' => $itemId, 'inventory_location_id' => $locationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $balance = DB::table('inventory_location_stocks')
            ->lockForUpdate()
            ->where('inventory_item_id', $itemId)
            ->where('inventory_location_id', $locationId)
            ->first();
        $updates = ['updated_at' => now()];
        foreach (['total_stock', 'available_stock', 'borrowed_stock', 'damaged_stock', 'lost_stock'] as $column) {
            $next = (int) $balance->{$column} + (int) ($deltas[$column] ?? 0);
            abort_if($next < 0, 422, 'Saldo stok lokasi tidak mencukupi untuk transaksi ini.');
            $updates[$column] = $next;
        }
        DB::table('inventory_location_stocks')->where('id', $balance->id)->update($updates);
    }

    private function syncQuantityItemStock(int $itemId): void
    {
        $item = DB::table('inventory_items')->whereNull('deleted_at')->find($itemId);
        if (! $item || $item->inventory_type !== 'quantity') {
            return;
        }
        $totals = DB::table('inventory_location_stocks')->where('inventory_item_id', $itemId)->selectRaw('SUM(total_stock) as total_stock, SUM(available_stock) as available_stock, SUM(borrowed_stock) as borrowed_stock, SUM(damaged_stock) as damaged_stock, SUM(lost_stock) as lost_stock')->first();
        DB::table('inventory_items')->where('id', $itemId)->update([
            'total_stock' => (int) ($totals->total_stock ?? 0),
            'available_stock' => (int) ($totals->available_stock ?? 0),
            'borrowed_stock' => (int) ($totals->borrowed_stock ?? 0),
            'damaged_stock' => (int) ($totals->damaged_stock ?? 0),
            'lost_stock' => (int) ($totals->lost_stock ?? 0),
            'updated_at' => now(),
        ]);
    }

    private function borrowedLocation(int $itemId, ?int $preferredLocationId): ?int
    {
        if ($preferredLocationId && DB::table('inventory_location_stocks')->where('inventory_item_id', $itemId)->where('inventory_location_id', $preferredLocationId)->where('borrowed_stock', '>', 0)->exists()) {
            return $preferredLocationId;
        }

        return DB::table('inventory_location_stocks')->where('inventory_item_id', $itemId)->where('borrowed_stock', '>', 0)->orderByDesc('borrowed_stock')->value('inventory_location_id');
    }

    private function syncUnitItemStock(int $itemId): void
    {
        $item = DB::table('inventory_items')->whereNull('deleted_at')->find($itemId);
        if (! $item || $item->inventory_type !== 'unit') {
            return;
        }

        $counts = DB::table('office_assets')
            ->whereNull('deleted_at')
            ->where('inventory_item_id', $itemId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available")
            ->selectRaw("SUM(CASE WHEN status = 'borrowed' THEN 1 ELSE 0 END) as borrowed")
            ->selectRaw("SUM(CASE WHEN status = 'damaged' THEN 1 ELSE 0 END) as damaged")
            ->selectRaw("SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost")
            ->first();

        DB::table('inventory_items')->where('id', $itemId)->update([
            'total_stock' => (int) ($counts->total ?? 0),
            'available_stock' => (int) ($counts->available ?? 0),
            'borrowed_stock' => (int) ($counts->borrowed ?? 0),
            'damaged_stock' => (int) ($counts->damaged ?? 0),
            'lost_stock' => (int) ($counts->lost ?? 0),
            'updated_at' => now(),
        ]);
    }

    private function automaticCodeDefinition(string $section): ?array
    {
        return match ($section) {
            'locations' => ['table' => 'inventory_locations', 'column' => 'code', 'prefix' => 'LOK'],
            'items' => ['table' => 'inventory_items', 'column' => 'code', 'prefix' => 'BRG'],
            'units' => ['table' => 'office_assets', 'column' => 'kode_aset', 'prefix' => 'AST'],
            'receipts' => ['table' => 'inventory_asset_receipts', 'column' => 'receipt_no', 'prefix' => 'TRM'],
            'loans' => ['table' => 'inventory_loans', 'column' => 'transaction_no', 'prefix' => 'PJM'],
            'returns' => ['table' => 'inventory_returns', 'column' => 'return_no', 'prefix' => 'KMB'],
            'transfers' => ['table' => 'inventory_transfers', 'column' => 'transaction_no', 'prefix' => 'MUT'],
            'stock-opname' => ['table' => 'inventory_stock_opnames', 'column' => 'opname_no', 'prefix' => 'SO'],
            default => null,
        };
    }

    private function nextAutomaticCode(string $table, string $column, string $prefix): string
    {
        $number = ((int) DB::table($table)->lockForUpdate()->max('id')) + 1;

        do {
            $code = $prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $number++;
        } while (DB::table($table)->where($column, $code)->exists());

        return $code;
    }

    private function formPage(string $section, ?string $id = null)
    {
        $config = $this->config($section);
        abort_unless($config['table'], 404);
        $row = $id ? DB::table($config['table'])->where('id', $id)->whereNull('deleted_at')->first() : null;
        if ($id) {
            abort_if(! $row, 404);
        }

        return Inertia::render('Admin/OperationsModule/Form', [
            'title' => 'Inventaris Perusahaan', 'module' => 'inventory', 'section' => $section, 'sectionTitle' => $config['title'],
            'baseUrl' => '/admin/inventaris-perusahaan', 'indexUrl' => "/admin/inventaris-perusahaan/{$section}",
            'actionUrl' => $id ? "/admin/inventaris-perusahaan/{$section}/records/{$id}" : "/admin/inventaris-perusahaan/{$section}/records",
            'method' => $id ? 'put' : 'post', 'fields' => $config['fields'], 'options' => $this->options(), 'row' => $row ? (array) $row : null,
        ]);
    }

    private function detailData(string $section, string $id): array
    {
        $loanHistoryQuery = DB::table('inventory_loan_items as lines')
            ->join('inventory_loans as loans', 'loans.id', '=', 'lines.inventory_loan_id')
            ->join('inventory_items as items', 'items.id', '=', 'lines.inventory_item_id')
            ->leftJoin('office_assets as assets', 'assets.id', '=', 'lines.office_asset_id')
            ->leftJoin('inventory_locations as locations', 'locations.id', '=', 'loans.inventory_location_id')
            ->leftJoin('perumahans as proyek', 'proyek.id', '=', 'loans.perumahan_id')
            ->leftJoin('detail_rumahs as rumah', 'rumah.id', '=', 'loans.detail_rumah_id')
            ->whereNull('loans.deleted_at')
            ->select([
                'loans.id', 'loans.transaction_no', 'loans.date', 'loans.borrower', 'loans.division',
                'loans.planned_return_date', 'loans.status', 'lines.quantity', 'lines.returned_quantity',
                'items.name as item_name', 'assets.kode_aset', 'locations.name as location_name',
                'proyek.nama_perusahaan as project_name', 'rumah.nomor_rumah as house_number',
            ])
            ->latest('loans.date');

        if ($section === 'items') {
            $record = DB::table('inventory_items as items')
                ->join('inventory_categories as categories', 'categories.id', '=', 'items.inventory_category_id')
                ->whereNull('items.deleted_at')
                ->where('items.id', $id)
                ->select('items.*', 'categories.name as category_name')
                ->first();
            abort_if(! $record, 404);

            $units = DB::table('office_assets as assets')
                ->leftJoin('inventory_locations as locations', 'locations.id', '=', 'assets.inventory_location_id')
                ->whereNull('assets.deleted_at')
                ->where('assets.inventory_item_id', $id)
                ->orderBy('assets.kode_aset')
                ->get(['assets.id', 'assets.kode_aset', 'assets.nomor_seri', 'assets.status', 'assets.condition', 'locations.name as location_name'])
                ->map(function ($unit): array {
                    $assignment = DB::table('inventory_loan_items as lines')
                        ->join('inventory_loans as loans', 'loans.id', '=', 'lines.inventory_loan_id')
                        ->leftJoin('perumahans as proyek', 'proyek.id', '=', 'loans.perumahan_id')
                        ->leftJoin('detail_rumahs as rumah', 'rumah.id', '=', 'loans.detail_rumah_id')
                        ->where('lines.office_asset_id', $unit->id)
                        ->where('loans.status', 'borrowed')
                        ->whereNull('loans.deleted_at')
                        ->latest('loans.date')
                        ->first(['loans.borrower', 'proyek.nama_perusahaan as project_name', 'rumah.nomor_rumah as house_number']);

                    return [...(array) $unit, 'borrower' => $assignment?->borrower, 'project_name' => $assignment?->project_name, 'house_number' => $assignment?->house_number];
                });
            $locationStocks = $record->inventory_type === 'quantity'
                ? DB::table('inventory_location_stocks as stocks')->join('inventory_locations as locations', 'locations.id', '=', 'stocks.inventory_location_id')->where('stocks.inventory_item_id', $id)->orderBy('locations.name')->get(['locations.name as location_name', 'stocks.total_stock', 'stocks.available_stock', 'stocks.borrowed_stock', 'stocks.damaged_stock', 'stocks.lost_stock'])
                : DB::table('office_assets as assets')->leftJoin('inventory_locations as locations', 'locations.id', '=', 'assets.inventory_location_id')->whereNull('assets.deleted_at')->where('assets.inventory_item_id', $id)->groupBy('locations.id', 'locations.name')->orderBy('locations.name')->get(['locations.name as location_name', DB::raw('COUNT(*) as total_stock'), DB::raw("SUM(CASE WHEN assets.status = 'available' THEN 1 ELSE 0 END) as available_stock"), DB::raw("SUM(CASE WHEN assets.status = 'borrowed' THEN 1 ELSE 0 END) as borrowed_stock"), DB::raw("SUM(CASE WHEN assets.status = 'damaged' THEN 1 ELSE 0 END) as damaged_stock"), DB::raw("SUM(CASE WHEN assets.status = 'lost' THEN 1 ELSE 0 END) as lost_stock")]);

            return [
                'title' => 'Detail Barang Inventaris', 'kind' => 'item', 'record' => (array) $record,
                'metrics' => [
                    ['label' => 'Total Stok', 'value' => $record->total_stock],
                    ['label' => 'Tersedia', 'value' => $record->available_stock],
                    ['label' => 'Dipinjam', 'value' => $record->borrowed_stock],
                    ['label' => 'Rusak / Hilang', 'value' => $record->damaged_stock.' / '.$record->lost_stock],
                ],
                'units' => $units,
                'locationStocks' => $locationStocks,
                'loans' => (clone $loanHistoryQuery)->where('lines.inventory_item_id', $id)->get(),
                'returns' => [],
                'indexUrl' => '/admin/inventaris-perusahaan/items',
            ];
        }

        if ($section === 'units') {
            $record = DB::table('office_assets as assets')
                ->join('inventory_items as items', 'items.id', '=', 'assets.inventory_item_id')
                ->leftJoin('inventory_locations as locations', 'locations.id', '=', 'assets.inventory_location_id')
                ->whereNull('assets.deleted_at')
                ->where('assets.id', $id)
                ->select('assets.*', 'items.name as item_name', 'items.code as item_code', 'locations.name as location_name')
                ->first();
            abort_if(! $record, 404);
            $loans = (clone $loanHistoryQuery)->where('lines.office_asset_id', $id)->get();
            $active = $loans->firstWhere('status', 'borrowed');

            return [
                'title' => 'Detail Unit Aset', 'kind' => 'unit', 'record' => (array) $record,
                'metrics' => [
                    ['label' => 'Status', 'value' => $record->status],
                    ['label' => 'Lokasi Saat Ini', 'value' => $record->location_name ?? '-'],
                    ['label' => 'Pemakai Saat Ini', 'value' => $active?->borrower ?? '-'],
                    ['label' => 'Proyek / Unit', 'value' => collect([$active?->project_name, $active?->house_number ? 'Unit '.$active->house_number : null])->filter()->join(' · ') ?: '-'],
                ],
                'units' => [], 'locationStocks' => [], 'loans' => $loans, 'returns' => [],
                'indexUrl' => '/admin/inventaris-perusahaan/units',
            ];
        }

        $record = DB::table('inventory_loans as loans')
            ->leftJoin('inventory_locations as locations', 'locations.id', '=', 'loans.inventory_location_id')
            ->leftJoin('perumahans as proyek', 'proyek.id', '=', 'loans.perumahan_id')
            ->leftJoin('detail_rumahs as rumah', 'rumah.id', '=', 'loans.detail_rumah_id')
            ->whereNull('loans.deleted_at')
            ->where('loans.id', $id)
            ->select('loans.*', 'locations.name as location_name', 'proyek.nama_perusahaan as project_name', 'rumah.nomor_rumah as house_number')
            ->first();
        abort_if(! $record, 404);

        return [
            'title' => 'Detail Peminjaman Inventaris', 'kind' => 'loan', 'record' => (array) $record,
            'metrics' => [
                ['label' => 'Status', 'value' => $record->status],
                ['label' => 'Peminjam', 'value' => $record->borrower],
                ['label' => 'Lokasi Pemakaian', 'value' => $record->location_name ?? '-'],
                ['label' => 'Proyek / Unit', 'value' => collect([$record->project_name, $record->house_number ? 'Unit '.$record->house_number : null])->filter()->join(' · ') ?: '-'],
            ],
            'units' => [], 'locationStocks' => [],
            'loans' => (clone $loanHistoryQuery)->where('loans.id', $id)->get(),
            'returns' => DB::table('inventory_returns as returns')
                ->join('inventory_return_items as return_lines', 'return_lines.inventory_return_id', '=', 'returns.id')
                ->join('inventory_loan_items as loan_lines', 'loan_lines.id', '=', 'return_lines.inventory_loan_item_id')
                ->join('inventory_items as items', 'items.id', '=', 'loan_lines.inventory_item_id')
                ->leftJoin('office_assets as assets', 'assets.id', '=', 'loan_lines.office_asset_id')
                ->leftJoin('inventory_locations as locations', 'locations.id', '=', 'returns.return_location_id')
                ->whereNull('returns.deleted_at')->where('returns.inventory_loan_id', $id)
                ->get(['return_lines.id', 'returns.return_no', 'returns.date', 'items.name as item_name', 'assets.kode_aset', 'return_lines.good_quantity', 'return_lines.damaged_quantity', 'return_lines.lost_quantity', 'return_lines.outcome', 'locations.name as return_location', 'return_lines.notes']),
            'indexUrl' => '/admin/inventaris-perusahaan/loans',
        ];
    }

    private function summary(): array
    {
        return [
            ['label' => 'Total Barang', 'value' => DB::table('inventory_items')->whereNull('deleted_at')->count()],
            ['label' => 'Total Unit Inventaris', 'value' => DB::table('office_assets')->whereNull('deleted_at')->count()],
            ['label' => 'Barang Tersedia', 'value' => DB::table('inventory_items')->whereNull('deleted_at')->sum('available_stock')],
            ['label' => 'Barang Dipinjam', 'value' => DB::table('inventory_items')->whereNull('deleted_at')->sum('borrowed_stock')],
            ['label' => 'Barang Rusak', 'value' => DB::table('inventory_items')->whereNull('deleted_at')->sum('damaged_stock')],
            ['label' => 'Barang Hilang', 'value' => DB::table('inventory_items')->whereNull('deleted_at')->sum('lost_stock')],
            ['label' => 'Terlambat Dikembalikan', 'value' => DB::table('inventory_loans')->whereNull('deleted_at')->where('status', 'borrowed')->whereDate('planned_return_date', '<', now())->count()],
            ['label' => 'Stok Menipis', 'value' => DB::table('inventory_items')->whereNull('deleted_at')->whereColumn('available_stock', '<=', 'minimum_stock')->count()],
        ];
    }

    private function dashboardData(): array
    {
        $activeIssues = DB::table('inventory_loans as loans')
            ->leftJoin('perumahans as proyek', 'proyek.id', '=', 'loans.perumahan_id')
            ->leftJoin('detail_rumahs as rumah', 'rumah.id', '=', 'loans.detail_rumah_id')
            ->leftJoin('inventory_locations as locations', 'locations.id', '=', 'loans.inventory_location_id')
            ->whereNull('loans.deleted_at')
            ->whereIn('loans.status', ['borrowed', 'assigned', 'partially_returned', 'overdue'])
            ->latest('loans.date')->limit(8)
            ->get(['loans.id', 'loans.transaction_no', 'loans.date', 'loans.borrower', 'loans.taken_by_name', 'loans.planned_return_date', 'loans.status', 'proyek.nama_perusahaan as project_name', 'rumah.nomor_rumah as house_number', 'locations.name as location_name'])
            ->map(function ($loan): array {
                $lines = DB::table('inventory_loan_items as lines')->join('inventory_items as items', 'items.id', '=', 'lines.inventory_item_id')->where('lines.inventory_loan_id', $loan->id)->get(['items.name', 'lines.quantity', 'lines.returned_quantity']);

                return [...(array) $loan, 'item_summary' => $lines->map(fn ($line) => $line->name.' ('.((int) $line->quantity - (int) $line->returned_quantity).')')->join(', '), 'is_overdue' => filled($loan->planned_return_date) && $loan->planned_return_date < today()->toDateString()];
            });
        $locationBalances = DB::table('inventory_locations as locations')
            ->leftJoin('inventory_location_stocks as stocks', 'stocks.inventory_location_id', '=', 'locations.id')
            ->whereNull('locations.deleted_at')->groupBy('locations.id', 'locations.name')->orderBy('locations.name')
            ->get(['locations.id', 'locations.name', DB::raw('COALESCE(SUM(stocks.total_stock), 0) as quantity_stock'), DB::raw('COALESCE(SUM(stocks.available_stock), 0) as quantity_available')])
            ->map(function ($location): array {
                $unitCounts = DB::table('office_assets')->whereNull('deleted_at')->where('inventory_location_id', $location->id)->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = \'available\' THEN 1 ELSE 0 END) as available')->first();

                return ['location_name' => $location->name, 'total_stock' => (int) $location->quantity_stock + (int) ($unitCounts->total ?? 0), 'available_stock' => (int) $location->quantity_available + (int) ($unitCounts->available ?? 0)];
            });

        return [
            'activeIssues' => $activeIssues,
            'locationBalances' => $locationBalances,
            'lowStock' => DB::table('inventory_items')->whereNull('deleted_at')->whereColumn('available_stock', '<=', 'minimum_stock')->orderBy('available_stock')->limit(8)->get(['id', 'name', 'available_stock', 'minimum_stock', 'unit']),
        ];
    }

    private function menu(): array
    {
        return [['key' => 'dashboard', 'label' => 'Dashboard'], ['key' => 'categories', 'label' => 'Kategori Barang'], ['key' => 'items', 'label' => 'Data Barang'], ['key' => 'units', 'label' => 'Unit Aset'], ['key' => 'locations', 'label' => 'Lokasi Inventaris'], ['key' => 'receipts', 'label' => 'Penerimaan / Penambahan Aset'], ['key' => 'loans', 'label' => 'Pengambilan & Penyerahan'], ['key' => 'returns', 'label' => 'Pengembalian & Pemeriksaan'], ['key' => 'transfers', 'label' => 'Mutasi Barang'], ['key' => 'damages', 'label' => 'Barang Rusak'], ['key' => 'losses', 'label' => 'Barang Hilang'], ['key' => 'stock-opname', 'label' => 'Stock Opname'], ['key' => 'ledger', 'label' => 'Kartu Pergerakan'], ['key' => 'reports', 'label' => 'Laporan Pengambilan']];
    }

    private function options(): array
    {
        return [
            'categories' => DB::table('inventory_categories')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name, 'display_label' => $row->name])->values()->all(),
            'locations' => DB::table('inventory_locations as locations')->leftJoin('cabang_perusahaans as branches', 'branches.id', '=', 'locations.branch_id')->leftJoin('perumahans as housing', 'housing.id', '=', 'locations.perumahan_id')->whereNull('locations.deleted_at')->orderBy('locations.name')->get(['locations.id', 'locations.code', 'locations.name', 'locations.owner_type', 'branches.nama_cabang', 'housing.nama_perusahaan'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name.' ('.$row->code.') · '.match ($row->owner_type) {
                    'branch' => 'Cabang '.($row->nama_cabang ?? '-'),'housing' => 'Perumahan '.($row->nama_perusahaan ?? '-'),default => 'Perusahaan'
                }, 'display_label' => $row->name])->values()->all(),
            'divisions' => DB::table('inventory_divisions')->where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name])->values()->all(),
            'branches' => DB::table('cabang_perusahaans')->whereNull('deleted_at')->orderBy('nama_cabang')->get(['id', 'nama_cabang'])->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_cabang])->values()->all(),
            'items' => DB::table('inventory_items')->whereNull('deleted_at')->orderBy('name')->get(['id', 'code', 'name', 'inventory_type', 'total_stock', 'available_stock'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name.' ('.$row->code.')', 'display_label' => $row->name, 'inventory_type' => $row->inventory_type, 'total_stock' => $row->total_stock, 'available_stock' => $row->available_stock])->values()->all(),
            'units' => DB::table('office_assets as a')->join('inventory_items as i', 'i.id', '=', 'a.inventory_item_id')->whereNull('a.deleted_at')->orderBy('a.kode_aset')
                ->get(['a.id', 'a.kode_aset', 'a.inventory_item_id', 'a.inventory_location_id', 'a.status', 'i.name'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->name.' · Unit '.$row->kode_aset, 'display_label' => $row->name.' · Unit '.$row->kode_aset, 'inventory_item_id' => (string) $row->inventory_item_id, 'inventory_location_id' => $row->inventory_location_id ? (string) $row->inventory_location_id : '', 'status' => $row->status])->values()->all(),
            'loans' => DB::table('inventory_loans as loans')
                ->leftJoin('inventory_loan_items as lines', 'lines.inventory_loan_id', '=', 'loans.id')
                ->leftJoin('inventory_items as items', 'items.id', '=', 'lines.inventory_item_id')
                ->leftJoin('office_assets as assets', 'assets.id', '=', 'lines.office_asset_id')
                ->whereNull('loans.deleted_at')->latest('loans.date')
                ->get(['loans.id', 'loans.transaction_no', 'loans.borrower', 'loans.status', 'loans.source_location_id', 'lines.inventory_item_id', 'lines.office_asset_id', 'lines.quantity', 'items.code as item_code', 'items.name as item_name', 'assets.kode_aset'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => collect([$row->borrower, $row->item_name, $row->kode_aset ? 'Unit '.$row->kode_aset : null, $row->transaction_no])->filter()->join(' · '), 'display_label' => collect([$row->borrower, $row->item_name, $row->transaction_no])->filter()->join(' · '), 'status' => $row->status, 'source_location_id' => $row->source_location_id ? (string) $row->source_location_id : '', 'inventory_item_id' => $row->inventory_item_id ? (string) $row->inventory_item_id : '', 'office_asset_id' => $row->office_asset_id ? (string) $row->office_asset_id : '', 'quantity' => $row->quantity])->values()->all(),
            'perumahans' => DB::table('perumahans')->whereNull('deleted_at')->orderBy('nama_perusahaan')->get(['id', 'kode_proyek', 'nama_perusahaan'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan.($row->kode_proyek ? ' ('.$row->kode_proyek.')' : ''), 'display_label' => $row->nama_perusahaan])->values()->all(),
            'houseUnits' => DB::table('detail_rumahs as rumah')->join('perumahans as proyek', 'proyek.id', '=', 'rumah.perumahan_id')->whereNull('rumah.deleted_at')->orderBy('rumah.nomor_rumah')
                ->get(['rumah.id', 'rumah.perumahan_id', 'rumah.kode_nlok', 'rumah.nomor_rumah', 'proyek.nama_perusahaan'])
                ->map(fn ($row) => ['value' => (string) $row->id, 'label' => 'Unit '.$row->nomor_rumah.($row->kode_nlok ? ' ('.$row->kode_nlok.')' : ''), 'display_label' => $row->nama_perusahaan.' · Unit '.$row->nomor_rumah, 'perumahan_id' => (string) $row->perumahan_id])->values()->all(),
        ];
    }

    private function formatListRow(string $section, array $row, array $options): array
    {
        if ($section === 'items') {
            $row['unit_count'] = $row['inventory_type'] === 'unit'
                ? DB::table('office_assets')->whereNull('deleted_at')->where('inventory_item_id', $row['id'])->count()
                : null;
        }

        if ($section === 'units') {
            $assignment = DB::table('inventory_loan_items as lines')
                ->join('inventory_loans as loans', 'loans.id', '=', 'lines.inventory_loan_id')
                ->leftJoin('perumahans as proyek', 'proyek.id', '=', 'loans.perumahan_id')
                ->leftJoin('detail_rumahs as rumah', 'rumah.id', '=', 'loans.detail_rumah_id')
                ->where('lines.office_asset_id', $row['id'])
                ->where('loans.status', 'borrowed')
                ->whereNull('loans.deleted_at')
                ->latest('loans.date')
                ->first(['loans.borrower', 'proyek.nama_perusahaan', 'rumah.nomor_rumah']);
            $row['current_assignment'] = $assignment
                ? collect([$assignment->borrower, $assignment->nama_perusahaan, $assignment->nomor_rumah ? 'Unit '.$assignment->nomor_rumah : null])->filter()->join(' · ')
                : 'Tidak sedang dipakai';
        }

        if ($section === 'loans') {
            $lines = DB::table('inventory_loan_items as lines')
                ->join('inventory_items as items', 'items.id', '=', 'lines.inventory_item_id')
                ->leftJoin('office_assets as assets', 'assets.id', '=', 'lines.office_asset_id')
                ->where('lines.inventory_loan_id', $row['id'])
                ->get(['items.name', 'assets.kode_aset', 'lines.quantity', 'lines.returned_quantity']);
            $row['item_summary'] = $lines->map(fn ($line) => $line->name.($line->kode_aset ? ' · Unit '.$line->kode_aset : ' · '.$line->quantity))->join(', ');
            $row['quantity'] = $lines->sum('quantity');
            $row['return_progress'] = $lines->sum('returned_quantity').' / '.$lines->sum('quantity');
            $project = filled($row['perumahan_id'] ?? null) ? $this->optionLabel($options['perumahans'], $row['perumahan_id']) : null;
            $house = filled($row['detail_rumah_id'] ?? null) ? $this->optionLabel($options['houseUnits'], $row['detail_rumah_id']) : null;
            $location = filled($row['inventory_location_id'] ?? null) ? $this->optionLabel($options['locations'], $row['inventory_location_id']) : null;
            $row['usage_destination'] = collect([$project, $house, $location])->filter()->unique()->join(' · ') ?: '-';
        }

        if ($section === 'returns') {
            $lines = DB::table('inventory_return_items as return_lines')
                ->join('inventory_loan_items as loan_lines', 'loan_lines.id', '=', 'return_lines.inventory_loan_item_id')
                ->join('inventory_items as items', 'items.id', '=', 'loan_lines.inventory_item_id')
                ->where('return_lines.inventory_return_id', $row['id'])
                ->get(['items.name', 'return_lines.good_quantity', 'return_lines.damaged_quantity', 'return_lines.lost_quantity']);
            $row['item_summary'] = $lines->map(fn ($line) => $line->name.' · Baik '.$line->good_quantity.' / Rusak '.$line->damaged_quantity.' / Hilang '.$line->lost_quantity)->join(', ');
        }

        if ($section === 'stock-opname') {
            $line = DB::table('inventory_stock_opname_items')->where('inventory_stock_opname_id', $row['id'])->first();
            $row['inventory_item_id'] = $line?->inventory_item_id;
            $row['physical_quantity'] = $line?->physical_quantity;
        }

        $relations = match ($section) {
            'items' => ['inventory_category_id' => 'categories'],
            'units' => ['inventory_item_id' => 'items', 'inventory_location_id' => 'locations'],
            'receipts' => ['inventory_item_id' => 'items', 'office_asset_id' => 'units', 'inventory_location_id' => 'locations'],
            'loans' => ['source_location_id' => 'locations', 'inventory_location_id' => 'locations', 'perumahan_id' => 'perumahans', 'detail_rumah_id' => 'houseUnits', 'inventory_item_id' => 'items', 'office_asset_id' => 'units'],
            'returns' => ['inventory_loan_id' => 'loans', 'return_location_id' => 'locations'],
            'transfers' => ['inventory_item_id' => 'items', 'office_asset_id' => 'units', 'from_location_id' => 'locations', 'to_location_id' => 'locations'],
            'damages' => ['inventory_item_id' => 'items', 'office_asset_id' => 'units', 'inventory_location_id' => 'locations'],
            'losses' => ['inventory_item_id' => 'items', 'office_asset_id' => 'units', 'last_location_id' => 'locations'],
            'stock-opname' => ['inventory_location_id' => 'locations', 'inventory_item_id' => 'items'],
            default => [],
        };

        foreach ($relations as $column => $optionKey) {
            if (filled($row[$column] ?? null)) {
                $row[$column] = $this->optionLabel($options[$optionKey] ?? [], $row[$column]);
            }
        }

        return $row;
    }

    private function optionLabel(array $options, mixed $value): string
    {
        $option = collect($options)->firstWhere('value', (string) $value);

        return $option['display_label'] ?? $option['label'] ?? (string) $value;
    }

    private function config(string $s): array
    {
        $text = fn ($name, $label, $required = true) => compact('name', 'label', 'required') + ['type' => 'text'];
        $auto = fn ($name, $label) => ['name' => $name, 'label' => $label, 'type' => 'auto-code', 'required' => false];
        $num = fn ($name, $label) => ['name' => $name, 'label' => $label, 'type' => 'number', 'required' => true];
        $select = fn ($name, $label, $options) => ['name' => $name, 'label' => $label, 'type' => 'select', 'optionsKey' => $options, 'required' => true];
        $columns = fn (...$definitions) => collect($definitions)->map(fn ($definition) => ['name' => $definition[0], 'label' => $definition[1], 'sortable' => $definition[2] ?? true])->all();

        return match ($s) {
            'dashboard','reports' => ['title' => $s === 'dashboard' ? 'Dashboard' : 'Laporan Inventaris', 'table' => null, 'fields' => [], 'rules' => [], 'search' => []],
            'categories' => ['title' => 'Kategori Barang', 'table' => 'inventory_categories', 'fields' => [$text('name', 'Nama Kategori'), $text('description', 'Keterangan', false), ['name' => 'is_active', 'label' => 'Status Aktif', 'type' => 'boolean', 'required' => false]], 'rules' => ['name' => 'required|string|max:255', 'description' => 'nullable|string', 'is_active' => 'boolean'], 'search' => ['name', 'description']],
            'locations' => ['title' => 'Lokasi Inventaris', 'table' => 'inventory_locations', 'fields' => [$auto('code', 'Kode Lokasi'), $text('name', 'Nama Lokasi'), ['name' => 'type', 'label' => 'Jenis Lokasi', 'type' => 'select', 'options' => ['warehouse' => 'Gudang', 'office' => 'Kantor', 'project' => 'Proyek', 'other' => 'Lainnya'], 'required' => true], ['name' => 'owner_type', 'label' => 'Pemilik Lokasi', 'type' => 'select', 'options' => ['company' => 'Perusahaan', 'branch' => 'Cabang', 'housing' => 'Perumahan'], 'required' => true], ['name' => 'branch_id', 'label' => 'Cabang', 'type' => 'select', 'optionsKey' => 'branches', 'required' => false], ['name' => 'perumahan_id', 'label' => 'Perumahan', 'type' => 'select', 'optionsKey' => 'perumahans', 'required' => false], $text('address', 'Alamat', false)], 'rules' => ['code' => 'nullable|string|max:50', 'name' => 'required|string|max:255', 'type' => ['required', Rule::in(['warehouse', 'office', 'project', 'other'])], 'owner_type' => ['nullable', Rule::in(['company', 'branch', 'housing'])], 'branch_id' => 'nullable|required_if:owner_type,branch|exists:cabang_perusahaans,id', 'perumahan_id' => 'nullable|required_if:owner_type,housing|exists:perumahans,id', 'address' => 'nullable|string'], 'search' => ['code', 'name', 'type', 'owner_type']],
            'items' => ['title' => 'Data Barang', 'table' => 'inventory_items', 'columns' => $columns(['code', 'Kode Barang'], ['name', 'Nama Barang'], ['inventory_category_id', 'Kategori'], ['inventory_type', 'Jenis'], ['total_stock', 'Total Stok'], ['available_stock', 'Tersedia'], ['borrowed_stock', 'Dipinjam'], ['damaged_stock', 'Rusak'], ['lost_stock', 'Hilang']), 'fields' => [$auto('code', 'Kode Barang'), $text('name', 'Nama Barang'), $select('inventory_category_id', 'Kategori', 'categories'), $text('brand', 'Merk', false), $text('model', 'Model', false), $text('unit', 'Satuan'), $text('photo', 'Foto / Path Dokumen', false), ['name' => 'inventory_type', 'label' => 'Jenis Inventaris', 'type' => 'select', 'options' => ['quantity' => 'Berdasarkan Jumlah', 'unit' => 'Berdasarkan Unit'], 'required' => true], $num('minimum_stock', 'Minimum Stok'), $num('total_stock', 'Total Stok'), $num('available_stock', 'Stok Tersedia'), ['name' => 'initial_location_id', 'label' => 'Lokasi Stok Awal', 'type' => 'select', 'optionsKey' => 'locations', 'required' => false, 'createOnly' => true]], 'rules' => ['code' => 'nullable|string|max:50', 'name' => 'required|string|max:255', 'inventory_category_id' => 'required|exists:inventory_categories,id', 'brand' => 'nullable|string', 'model' => 'nullable|string', 'unit' => 'required|string', 'photo' => 'nullable|string|max:2048', 'inventory_type' => ['required', Rule::in(['quantity', 'unit'])], 'minimum_stock' => 'required|integer|min:0', 'total_stock' => 'required|integer|min:0', 'available_stock' => 'required|integer|min:0|lte:total_stock', 'initial_location_id' => 'nullable|exists:inventory_locations,id'], 'search' => ['code', 'name', 'brand', 'model']],
            'units' => ['title' => 'Unit Aset', 'table' => 'office_assets', 'columns' => $columns(['kode_aset', 'Kode Aset'], ['inventory_item_id', 'Nama Barang'], ['nomor_seri', 'Nomor Seri'], ['status', 'Status'], ['inventory_location_id', 'Lokasi Saat Ini'], ['current_assignment', 'Pemakai / Penempatan', false], ['condition', 'Kondisi']), 'fields' => [$select('inventory_item_id', 'Barang Berbasis Unit', 'items'), $auto('kode_aset', 'Kode Aset'), $text('nomor_seri', 'Nomor Seri'), $select('inventory_location_id', 'Lokasi Awal / Pemilik', 'locations'), ['name' => 'status', 'label' => 'Status', 'type' => 'asset-status', 'required' => false], ['name' => 'condition', 'label' => 'Kondisi', 'type' => 'select', 'options' => ['good' => 'Baik', 'fair' => 'Cukup Baik', 'needs_service' => 'Perlu Perawatan', 'damaged' => 'Rusak'], 'required' => true], $text('notes', 'Catatan', false)], 'rules' => ['inventory_item_id' => 'required|exists:inventory_items,id', 'kode_aset' => 'nullable|string|max:100', 'nomor_seri' => 'required|string|max:255', 'inventory_location_id' => 'required|exists:inventory_locations,id', 'status' => 'nullable|string', 'condition' => ['required', Rule::in(['good', 'fair', 'needs_service', 'damaged'])], 'notes' => 'nullable|string'], 'search' => ['kode_aset', 'nomor_seri', 'status', 'condition']],
            'receipts' => ['title' => 'Penerimaan / Penambahan Aset', 'table' => 'inventory_asset_receipts', 'columns' => $columns(['receipt_no', 'Nomor Penerimaan'], ['date', 'Tanggal'], ['inventory_item_id', 'Barang / Aset'], ['office_asset_id', 'Unit Aset'], ['quantity', 'Jumlah'], ['inventory_location_id', 'Lokasi Tujuan'], ['source', 'Sumber / Vendor'], ['reference_no', 'Nomor Referensi']), 'fields' => [$auto('receipt_no', 'Nomor Penerimaan'), ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true], $select('inventory_item_id', 'Barang / Aset', 'items'), ['name' => 'office_asset_id', 'label' => 'Unit Aset (untuk barang berbasis unit)', 'type' => 'select', 'optionsKey' => 'units', 'required' => false], $num('quantity', 'Jumlah'), $select('inventory_location_id', 'Tujuan Perusahaan / Cabang / Perumahan', 'locations'), $text('source', 'Sumber / Vendor', false), $text('reference_no', 'Nomor Referensi', false), $text('notes', 'Catatan', false)], 'rules' => ['receipt_no' => 'nullable|string|max:100', 'date' => 'required|date', 'inventory_item_id' => 'required|exists:inventory_items,id', 'office_asset_id' => 'nullable|exists:office_assets,id', 'quantity' => 'required|integer|min:1', 'inventory_location_id' => 'required|exists:inventory_locations,id', 'source' => 'nullable|string|max:255', 'reference_no' => 'nullable|string|max:100', 'notes' => 'nullable|string'], 'search' => ['receipt_no', 'source', 'reference_no']],
            'loans' => ['title' => 'Pengambilan Barang', 'table' => 'inventory_loans', 'columns' => $columns(['transaction_no', 'Nomor'], ['date', 'Tanggal'], ['borrower', 'Penanggung Jawab'], ['taken_by_name', 'Yang Mengambil'], ['item_summary', 'Daftar Barang', false], ['return_progress', 'Kembali', false], ['usage_destination', 'Perumahan / Unit / Lokasi', false], ['status', 'Status']), 'fields' => [$auto('transaction_no', 'Nomor Transaksi'), ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true], $text('borrower', 'Nama Peminjam / Penanggung Jawab'), $text('taken_by_name', 'Nama yang Mengambil'), $text('division', 'Divisi', false), ['name' => 'source_location_id', 'label' => 'Lokasi Asal', 'type' => 'select', 'optionsKey' => 'locations', 'required' => false], $select('inventory_location_id', 'Lokasi Pemakaian / Penempatan', 'locations'), ['name' => 'perumahan_id', 'label' => 'Perumahan / Proyek', 'type' => 'select', 'optionsKey' => 'perumahans', 'required' => false], ['name' => 'detail_rumah_id', 'label' => 'Unit Rumah', 'type' => 'select', 'optionsKey' => 'houseUnits', 'required' => false], ['name' => 'planned_return_date', 'label' => 'Rencana Kembali', 'type' => 'date', 'required' => false], $text('purpose', 'Keperluan')], 'rules' => [], 'search' => ['transaction_no', 'borrower', 'taken_by_name', 'division', 'status']],
            'returns' => ['title' => 'Pengembalian Barang', 'table' => 'inventory_returns', 'columns' => $columns(['return_no', 'Nomor'], ['inventory_loan_id', 'Peminjaman', false], ['date', 'Tanggal'], ['return_location_id', 'Lokasi Pengembalian', false], ['item_summary', 'Hasil Pemeriksaan', false], ['notes', 'Catatan']), 'fields' => [$auto('return_no', 'Nomor Pengembalian'), $select('inventory_loan_id', 'Transaksi Peminjaman', 'loans'), ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true], ['name' => 'return_location_id', 'label' => 'Lokasi Pengembalian', 'type' => 'select', 'optionsKey' => 'locations', 'required' => false], $text('notes', 'Catatan', false)], 'rules' => [], 'search' => ['return_no', 'notes']],
            'transfers' => ['title' => 'Mutasi Barang', 'table' => 'inventory_transfers', 'fields' => [$auto('transaction_no', 'Nomor Transaksi'), ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true], $select('inventory_item_id', 'Barang', 'items'), ['name' => 'office_asset_id', 'label' => 'Unit Aset (opsional)', 'type' => 'select', 'optionsKey' => 'units', 'required' => false], $num('quantity', 'Jumlah'), $select('from_location_id', 'Lokasi Asal', 'locations'), $select('to_location_id', 'Lokasi Tujuan', 'locations'), $text('reason', 'Alasan', false)], 'rules' => ['transaction_no' => 'nullable|string', 'date' => 'required|date', 'inventory_item_id' => 'required|exists:inventory_items,id', 'office_asset_id' => 'nullable|exists:office_assets,id', 'quantity' => 'required|integer|min:1', 'from_location_id' => 'required|exists:inventory_locations,id', 'to_location_id' => 'required|different:from_location_id|exists:inventory_locations,id', 'reason' => 'nullable|string'], 'search' => ['transaction_no', 'reason']],
            'damages' => ['title' => 'Barang Rusak', 'table' => 'inventory_damage_reports', 'fields' => [$select('inventory_item_id', 'Barang', 'items'), ['name' => 'office_asset_id', 'label' => 'Unit Aset (opsional)', 'type' => 'select', 'optionsKey' => 'units', 'required' => false], $select('inventory_location_id', 'Lokasi', 'locations'), ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true], $text('damage', 'Kerusakan'), ['name' => 'severity', 'label' => 'Tingkat Kerusakan', 'type' => 'select', 'options' => ['light' => 'Ringan', 'medium' => 'Sedang', 'heavy' => 'Berat', 'total' => 'Rusak Total'], 'required' => true], ['name' => 'repair_status', 'label' => 'Status Perbaikan', 'type' => 'select', 'options' => ['waiting_inspection' => 'Menunggu Pemeriksaan', 'waiting_repair' => 'Menunggu Perbaikan', 'in_repair' => 'Sedang Diperbaiki', 'repaired' => 'Selesai Diperbaiki', 'unrepairable' => 'Tidak Dapat Diperbaiki'], 'required' => true]], 'rules' => ['inventory_item_id' => 'required|exists:inventory_items,id', 'office_asset_id' => 'nullable|exists:office_assets,id', 'inventory_location_id' => 'required|exists:inventory_locations,id', 'date' => 'required|date', 'damage' => 'required|string', 'severity' => ['required', Rule::in(['light', 'medium', 'heavy', 'total'])], 'repair_status' => ['required', Rule::in(['waiting_inspection', 'waiting_repair', 'in_repair', 'repaired', 'unrepairable'])]], 'search' => ['damage', 'severity', 'repair_status']],
            'losses' => ['title' => 'Barang Hilang', 'table' => 'inventory_loss_reports', 'fields' => [$select('inventory_item_id', 'Barang', 'items'), ['name' => 'office_asset_id', 'label' => 'Unit Aset (opsional)', 'type' => 'select', 'optionsKey' => 'units', 'required' => false], $num('quantity', 'Jumlah'), $select('last_location_id', 'Lokasi Terakhir', 'locations'), ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true], $text('chronology', 'Kronologi'), $text('responsible_person', 'Penanggung Jawab', false), $text('status', 'Status')], 'rules' => ['inventory_item_id' => 'required|exists:inventory_items,id', 'office_asset_id' => 'nullable|exists:office_assets,id', 'quantity' => 'required|integer|min:1', 'last_location_id' => 'required|exists:inventory_locations,id', 'date' => 'required|date', 'chronology' => 'required|string', 'responsible_person' => 'nullable|string', 'status' => 'required|string'], 'search' => ['chronology', 'responsible_person', 'status']],
            'stock-opname' => ['title' => 'Stock Opname', 'table' => 'inventory_stock_opnames', 'fields' => [$auto('opname_no', 'Nomor Opname'), ['name' => 'date', 'label' => 'Tanggal', 'type' => 'date', 'required' => true], $select('inventory_location_id', 'Lokasi', 'locations'), $select('inventory_item_id', 'Barang Diperiksa', 'items'), $num('physical_quantity', 'Jumlah Fisik'), $text('notes', 'Catatan', false)], 'rules' => ['opname_no' => 'nullable|string', 'date' => 'required|date', 'inventory_location_id' => 'required|exists:inventory_locations,id', 'inventory_item_id' => 'required|exists:inventory_items,id', 'physical_quantity' => 'required|integer|min:0', 'notes' => 'nullable|string'], 'search' => ['opname_no', 'status', 'notes']],
            default => abort(404),
        };
    }

    private function can(string $action, string $section): bool
    {
        return (bool) (auth()->user()?->can("company-inventory.{$section}.{$action}") || auth()->user()?->hasRole('super_admin'));
    }

    private function authorizeModule(string $action, string $section): void
    {
        abort_unless($this->can($action,$section),403);
    }
}
