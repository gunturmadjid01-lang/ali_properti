<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InventoryCompanyController extends Controller
{
    public function index(Request $request, string $section = 'dashboard')
    {
        $this->authorizeModule('view');
        $config = $this->config($section);
        $search = trim((string) $request->query('search'));
        $sortable = array_merge(['id'], array_column($config['fields'], 'name'));
        $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : 'id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $query = $config['table'] ? DB::table($config['table'])->whereNull('deleted_at') : null;
        if ($query && $search !== '') $query->where(fn (Builder $q) => collect($config['search'])->each(fn ($col, $i) => $i ? $q->orWhere($col, 'like', "%{$search}%") : $q->where($col, 'like', "%{$search}%")));

        return Inertia::render('Admin/OperationsModule/Index', [
            'title' => 'Inventaris Perusahaan', 'module' => 'inventory', 'section' => $section, 'sectionTitle' => $config['title'],
            'baseUrl' => '/admin/inventaris-perusahaan', 'menu' => $this->menu(), 'fields' => $config['fields'], 'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
            'rows' => $query ? $query->orderBy($sort, $direction)->paginate(15)->withQueryString()->through(fn ($row) => (array) $row) : ['data' => [], 'links' => []],
            'summary' => $this->summary(), 'options' => $this->options(),
            'permissions' => ['create' => $this->can('create'), 'update' => $this->can('update'), 'delete' => $this->can('delete'), 'export' => $this->can('export'), 'verify' => $this->can('verify')],
        ]);
    }

    public function store(Request $request, string $section)
    {
        $this->authorizeModule('create'); $config = $this->config($section); $data = $request->validate($config['rules']);
        DB::transaction(function () use ($section, $config, $data) { $payload = [...$data, 'created_by' => auth()->id(), 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]; $this->storeTransaction($section, $config['table'], $payload); });
        return back()->with('success', $config['title'].' berhasil disimpan.');
    }

    public function update(Request $request, string $section, string $id)
    {
        $this->authorizeModule('update'); $config = $this->config($section); $data = $request->validate($config['rules']);
        DB::table($config['table'])->where('id', $id)->whereNull('deleted_at')->update([...$data, 'updated_by' => auth()->id(), 'updated_at' => now()]);
        return back()->with('success', $config['title'].' berhasil diperbarui.');
    }

    public function destroy(string $section, string $id)
    {
        $this->authorizeModule('delete'); $config = $this->config($section); DB::table($config['table'])->where('id', $id)->update(['deleted_at' => now(), 'updated_by' => auth()->id(), 'updated_at' => now()]);
        return back()->with('success', 'Data diarsipkan dan histori tetap tersimpan.');
    }

    public function verifyStockOpname(string $id)
    {
        $this->authorizeModule('verify');
        DB::transaction(function () use ($id) {
            $opname = DB::table('inventory_stock_opnames')->lockForUpdate()->where('id', $id)->where('status', 'draft')->first();
            abort_if(! $opname, 422, 'Stock opname sudah diverifikasi atau tidak ditemukan.');
            foreach (DB::table('inventory_stock_opname_items')->where('inventory_stock_opname_id', $id)->get() as $line) {
                $item = DB::table('inventory_items')->lockForUpdate()->find($line->inventory_item_id);
                DB::table('inventory_items')->where('id', $item->id)->update(['total_stock' => max(0, $item->total_stock + $line->difference), 'available_stock' => max(0, $item->available_stock + $line->difference), 'updated_by' => auth()->id(), 'updated_at' => now()]);
            }
            DB::table('inventory_stock_opnames')->where('id', $id)->update(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now(), 'updated_by' => auth()->id(), 'updated_at' => now()]);
        });
        return back()->with('success', 'Stock opname diverifikasi dan selisih stok diterapkan.');
    }

    public function export(Request $request, string $section, string $format)
    {
        $this->authorizeModule('export'); $config = $this->config($section); $rows = DB::table($config['table'])->whereNull('deleted_at')->get();
        if ($format === 'pdf') return Pdf::loadView('reports.module-table', ['title' => $config['title'], 'rows' => $rows, 'printedAt' => now()->format('d/m/Y H:i')])->setPaper('a4', 'landscape')->download('inventaris-'.$section.'.pdf');
        $html = view('reports.module-table', ['title' => $config['title'], 'rows' => $rows, 'printedAt' => now()->format('d/m/Y H:i')])->render();
        return response($html)->header('Content-Type', 'application/vnd.ms-excel')->header('Content-Disposition', 'attachment; filename="inventaris-'.$section.'.xls"');
    }

    private function storeTransaction(string $section, string $table, array $data): void
    {
        if ($section === 'loans') {
            $itemId = $data['inventory_item_id']; $assetId = $data['office_asset_id'] ?? null; $qty = (int) $data['quantity']; unset($data['inventory_item_id'], $data['office_asset_id'], $data['quantity'], $data['condition_out']);
            $item = DB::table('inventory_items')->lockForUpdate()->find($itemId); abort_if(!$item || $item->available_stock < $qty, 422, 'Stok tersedia tidak mencukupi.');
            if ($item->inventory_type === 'unit') {
                abort_if(! $assetId, 422, 'Unit aset wajib dipilih untuk barang berbasis unit.');
                $asset = DB::table('office_assets')->lockForUpdate()->find($assetId);
                abort_if(! $asset || (int) $asset->inventory_item_id !== (int) $itemId || $asset->status !== 'available', 422, 'Unit aset tidak tersedia atau tidak sesuai barang.');
                $qty = 1;
                DB::table('office_assets')->where('id', $assetId)->update(['status'=>'borrowed','inventory_location_id'=>$data['inventory_location_id'] ?? $asset->inventory_location_id,'updated_by'=>auth()->id(),'updated_at'=>now()]);
            }
            $loanId = DB::table($table)->insertGetId($data); DB::table('inventory_loan_items')->insert(['inventory_loan_id'=>$loanId,'inventory_item_id'=>$itemId,'office_asset_id'=>$assetId,'quantity'=>$qty,'condition_out'=>'good','created_at'=>now(),'updated_at'=>now()]);
            DB::table('inventory_items')->where('id',$itemId)->update(['available_stock'=>$item->available_stock-$qty,'borrowed_stock'=>$item->borrowed_stock+$qty,'updated_at'=>now()]); return;
        }
        if ($section === 'returns') {
            $loanId = $data['inventory_loan_id']; $lines = DB::table('inventory_loan_items')->where('inventory_loan_id', $loanId)->get(); abort_if($lines->isEmpty(), 422, 'Detail peminjaman tidak ditemukan.');
            $returnId = DB::table($table)->insertGetId($data);
            foreach ($lines as $line) { $qty = max(0, $line->quantity - $line->returned_quantity); if (! $qty) continue; $item = DB::table('inventory_items')->lockForUpdate()->find($line->inventory_item_id); DB::table('inventory_return_items')->insert(['inventory_return_id'=>$returnId,'inventory_loan_item_id'=>$line->id,'quantity'=>$qty,'condition_in'=>'good','is_complete'=>true,'damaged_quantity'=>0,'lost_quantity'=>0,'created_at'=>now(),'updated_at'=>now()]); DB::table('inventory_loan_items')->where('id',$line->id)->update(['returned_quantity'=>$line->quantity,'updated_at'=>now()]); DB::table('inventory_items')->where('id',$item->id)->update(['available_stock'=>$item->available_stock+$qty,'borrowed_stock'=>max(0,$item->borrowed_stock-$qty),'updated_by'=>auth()->id(),'updated_at'=>now()]); if ($line->office_asset_id) DB::table('office_assets')->where('id',$line->office_asset_id)->update(['status'=>'available','current_user_id'=>null,'updated_by'=>auth()->id(),'updated_at'=>now()]); }
            DB::table('inventory_loans')->where('id',$loanId)->update(['status'=>'returned','updated_by'=>auth()->id(),'updated_at'=>now()]); return;
        }
        if ($section === 'transfers') {
            DB::table($table)->insert($data);
            if ($data['office_asset_id'] ?? null) DB::table('office_assets')->where('id',$data['office_asset_id'])->where('inventory_item_id',$data['inventory_item_id'])->update(['inventory_location_id'=>$data['to_location_id'],'updated_by'=>auth()->id(),'updated_at'=>now()]);
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
                DB::table('office_assets')->where('id',$asset->id)->update(['status'=>$section === 'damages' ? 'damaged' : 'lost','updated_by'=>auth()->id(),'updated_at'=>now()]);
            }
            DB::table($table)->insert($data);
            $counter = $section === 'damages' ? 'damaged_stock' : 'lost_stock';
            DB::table('inventory_items')->where('id',$item->id)->update(['available_stock'=>$item->available_stock-$qty,$counter=>$item->{$counter}+$qty,'updated_by'=>auth()->id(),'updated_at'=>now()]);
            return;
        }
        if ($section === 'stock-opname') { $itemId=$data['inventory_item_id']; $physical=(int)$data['physical_quantity']; unset($data['inventory_item_id'],$data['physical_quantity']); $data['status']='draft'; $item=DB::table('inventory_items')->find($itemId); $opnameId=DB::table($table)->insertGetId($data); DB::table('inventory_stock_opname_items')->insert(['inventory_stock_opname_id'=>$opnameId,'inventory_item_id'=>$itemId,'system_quantity'=>$item->total_stock,'physical_quantity'=>$physical,'difference'=>$physical-$item->total_stock,'created_at'=>now(),'updated_at'=>now()]); return; }
        DB::table($table)->insert($data);
    }

    private function summary(): array
    {
        return [
            ['label'=>'Total Barang','value'=>DB::table('inventory_items')->whereNull('deleted_at')->count()],
            ['label'=>'Total Unit Inventaris','value'=>DB::table('office_assets')->whereNull('deleted_at')->count()],
            ['label'=>'Barang Tersedia','value'=>DB::table('inventory_items')->whereNull('deleted_at')->sum('available_stock')],
            ['label'=>'Barang Dipinjam','value'=>DB::table('inventory_items')->whereNull('deleted_at')->sum('borrowed_stock')],
            ['label'=>'Barang Rusak','value'=>DB::table('inventory_items')->whereNull('deleted_at')->sum('damaged_stock')],
            ['label'=>'Barang Hilang','value'=>DB::table('inventory_items')->whereNull('deleted_at')->sum('lost_stock')],
            ['label'=>'Terlambat Dikembalikan','value'=>DB::table('inventory_loans')->whereNull('deleted_at')->where('status','borrowed')->whereDate('planned_return_date','<',now())->count()],
            ['label'=>'Stok Menipis','value'=>DB::table('inventory_items')->whereNull('deleted_at')->whereColumn('available_stock','<=','minimum_stock')->count()],
        ];
    }

    private function menu(): array { return [['key'=>'dashboard','label'=>'Dashboard'],['key'=>'categories','label'=>'Kategori Barang'],['key'=>'items','label'=>'Data Barang'],['key'=>'units','label'=>'Unit Aset'],['key'=>'locations','label'=>'Lokasi Inventaris'],['key'=>'loans','label'=>'Peminjaman Barang'],['key'=>'returns','label'=>'Pengembalian Barang'],['key'=>'transfers','label'=>'Mutasi Barang'],['key'=>'damages','label'=>'Barang Rusak'],['key'=>'losses','label'=>'Barang Hilang'],['key'=>'stock-opname','label'=>'Stock Opname'],['key'=>'reports','label'=>'Laporan']]; }
    private function options(): array { return ['categories'=>DB::table('inventory_categories')->whereNull('deleted_at')->pluck('name','id'),'locations'=>DB::table('inventory_locations')->whereNull('deleted_at')->pluck('name','id'),'items'=>DB::table('inventory_items')->whereNull('deleted_at')->pluck('name','id'),'units'=>DB::table('office_assets as a')->join('inventory_items as i','i.id','=','a.inventory_item_id')->whereNull('a.deleted_at')->where('a.status','available')->get(['a.id','a.kode_aset','i.name'])->mapWithKeys(fn($row)=>[$row->id=>$row->kode_aset.' - '.$row->name]),'loans'=>DB::table('inventory_loans')->whereNull('deleted_at')->where('status','borrowed')->pluck('transaction_no','id')]; }
    private function config(string $s): array
    {
        $text=fn($name,$label,$required=true)=>compact('name','label','required')+['type'=>'text']; $num=fn($name,$label)=>['name'=>$name,'label'=>$label,'type'=>'number','required'=>true]; $select=fn($name,$label,$options)=>['name'=>$name,'label'=>$label,'type'=>'select','optionsKey'=>$options,'required'=>true];
        return match($s) {
            'dashboard','reports'=>['title'=>$s==='dashboard'?'Dashboard':'Laporan Inventaris','table'=>null,'fields'=>[],'rules'=>[],'search'=>[]],
            'categories'=>['title'=>'Kategori Barang','table'=>'inventory_categories','fields'=>[$text('name','Nama Kategori'),$text('description','Keterangan',false),['name'=>'is_active','label'=>'Status Aktif','type'=>'boolean','required'=>false]],'rules'=>['name'=>'required|string|max:255','description'=>'nullable|string','is_active'=>'boolean'],'search'=>['name','description']],
            'locations'=>['title'=>'Lokasi Inventaris','table'=>'inventory_locations','fields'=>[$text('code','Kode Lokasi'),$text('name','Nama Lokasi'),$text('type','Jenis Lokasi'),$text('address','Alamat',false)],'rules'=>['code'=>'required|string|max:50','name'=>'required|string|max:255','type'=>'required|string|max:50','address'=>'nullable|string'],'search'=>['code','name','type']],
            'items'=>['title'=>'Data Barang','table'=>'inventory_items','fields'=>[$text('code','Kode Barang'),$text('name','Nama Barang'),$select('inventory_category_id','Kategori','categories'),$text('brand','Merk',false),$text('model','Model',false),$text('unit','Satuan'),$text('photo','Foto / Path Dokumen',false),['name'=>'inventory_type','label'=>'Jenis Inventaris','type'=>'select','options'=>['quantity'=>'Berdasarkan Jumlah','unit'=>'Berdasarkan Unit'],'required'=>true],$num('minimum_stock','Minimum Stok'),$num('total_stock','Total Stok'),$num('available_stock','Stok Tersedia')],'rules'=>['code'=>'required|string|max:50','name'=>'required|string|max:255','inventory_category_id'=>'required|exists:inventory_categories,id','brand'=>'nullable|string','model'=>'nullable|string','unit'=>'required|string','photo'=>'nullable|string|max:2048','inventory_type'=>['required',Rule::in(['quantity','unit'])],'minimum_stock'=>'required|integer|min:0','total_stock'=>'required|integer|min:0','available_stock'=>'required|integer|min:0|lte:total_stock'],'search'=>['code','name','brand','model']],
            'units'=>['title'=>'Unit Aset','table'=>'office_assets','fields'=>[$select('inventory_item_id','Barang','items'),$text('kode_aset','Kode Aset'),$text('nomor_seri','Nomor Seri'),$select('inventory_location_id','Lokasi','locations'),['name'=>'status','label'=>'Status','type'=>'select','options'=>['available'=>'Tersedia','borrowed'=>'Dipinjam','damaged'=>'Rusak','lost'=>'Hilang'],'required'=>true],$text('condition','Kondisi'),$text('notes','Catatan',false)],'rules'=>['inventory_item_id'=>'required|exists:inventory_items,id','kode_aset'=>'required|string|max:100','nomor_seri'=>'required|string|max:255','inventory_location_id'=>'nullable|exists:inventory_locations,id','status'=>'required|string','condition'=>'required|string','notes'=>'nullable|string'],'search'=>['kode_aset','nomor_seri','status','condition']],
            'loans'=>['title'=>'Peminjaman Barang','table'=>'inventory_loans','fields'=>[$text('transaction_no','Nomor Transaksi'),['name'=>'date','label'=>'Tanggal','type'=>'date','required'=>true],$text('borrower','Peminjam'),$text('division','Divisi',false),$select('inventory_location_id','Lokasi Penggunaan','locations'),$select('inventory_item_id','Barang','items'),['name'=>'office_asset_id','label'=>'Unit Aset (untuk barang unit)','type'=>'select','optionsKey'=>'units','required'=>false],$num('quantity','Jumlah'),['name'=>'planned_return_date','label'=>'Rencana Kembali','type'=>'date','required'=>false],$text('purpose','Keperluan')],'rules'=>['transaction_no'=>'required|string','date'=>'required|date','borrower'=>'required|string','division'=>'nullable|string','inventory_location_id'=>'required|exists:inventory_locations,id','inventory_item_id'=>'required|exists:inventory_items,id','office_asset_id'=>'nullable|exists:office_assets,id','quantity'=>'required|integer|min:1','planned_return_date'=>'nullable|date','purpose'=>'required|string'],'search'=>['transaction_no','borrower','division','status']],
            'returns'=>['title'=>'Pengembalian Barang','table'=>'inventory_returns','fields'=>[$text('return_no','Nomor Pengembalian'),$select('inventory_loan_id','Transaksi Peminjaman','loans'),['name'=>'date','label'=>'Tanggal','type'=>'date','required'=>true],$text('notes','Catatan',false)],'rules'=>['return_no'=>'required|string','inventory_loan_id'=>'required|exists:inventory_loans,id','date'=>'required|date','notes'=>'nullable|string'],'search'=>['return_no','notes']],
            'transfers'=>['title'=>'Mutasi Barang','table'=>'inventory_transfers','fields'=>[$text('transaction_no','Nomor Transaksi'),['name'=>'date','label'=>'Tanggal','type'=>'date','required'=>true],$select('inventory_item_id','Barang','items'),['name'=>'office_asset_id','label'=>'Unit Aset (opsional)','type'=>'select','optionsKey'=>'units','required'=>false],$num('quantity','Jumlah'),$select('from_location_id','Lokasi Asal','locations'),$select('to_location_id','Lokasi Tujuan','locations'),$text('reason','Alasan',false)],'rules'=>['transaction_no'=>'required|string','date'=>'required|date','inventory_item_id'=>'required|exists:inventory_items,id','office_asset_id'=>'nullable|exists:office_assets,id','quantity'=>'required|integer|min:1','from_location_id'=>'nullable|exists:inventory_locations,id','to_location_id'=>'required|different:from_location_id|exists:inventory_locations,id','reason'=>'nullable|string'],'search'=>['transaction_no','reason']],
            'damages'=>['title'=>'Barang Rusak','table'=>'inventory_damage_reports','fields'=>[$select('inventory_item_id','Barang','items'),['name'=>'office_asset_id','label'=>'Unit Aset (opsional)','type'=>'select','optionsKey'=>'units','required'=>false],$select('inventory_location_id','Lokasi','locations'),['name'=>'date','label'=>'Tanggal','type'=>'date','required'=>true],$text('damage','Kerusakan'),$text('severity','Tingkat Kerusakan'),$text('repair_status','Status Perbaikan')],'rules'=>['inventory_item_id'=>'required|exists:inventory_items,id','office_asset_id'=>'nullable|exists:office_assets,id','inventory_location_id'=>'nullable|exists:inventory_locations,id','date'=>'required|date','damage'=>'required|string','severity'=>'required|string','repair_status'=>'required|string'],'search'=>['damage','severity','repair_status']],
            'losses'=>['title'=>'Barang Hilang','table'=>'inventory_loss_reports','fields'=>[$select('inventory_item_id','Barang','items'),['name'=>'office_asset_id','label'=>'Unit Aset (opsional)','type'=>'select','optionsKey'=>'units','required'=>false],$num('quantity','Jumlah'),$select('last_location_id','Lokasi Terakhir','locations'),['name'=>'date','label'=>'Tanggal','type'=>'date','required'=>true],$text('chronology','Kronologi'),$text('responsible_person','Penanggung Jawab',false),$text('status','Status')],'rules'=>['inventory_item_id'=>'required|exists:inventory_items,id','office_asset_id'=>'nullable|exists:office_assets,id','quantity'=>'required|integer|min:1','last_location_id'=>'nullable|exists:inventory_locations,id','date'=>'required|date','chronology'=>'required|string','responsible_person'=>'nullable|string','status'=>'required|string'],'search'=>['chronology','responsible_person','status']],
            'stock-opname'=>['title'=>'Stock Opname','table'=>'inventory_stock_opnames','fields'=>[$text('opname_no','Nomor Opname'),['name'=>'date','label'=>'Tanggal','type'=>'date','required'=>true],$select('inventory_location_id','Lokasi','locations'),$select('inventory_item_id','Barang Diperiksa','items'),$num('physical_quantity','Jumlah Fisik'),$text('notes','Catatan',false)],'rules'=>['opname_no'=>'required|string','date'=>'required|date','inventory_location_id'=>'nullable|exists:inventory_locations,id','inventory_item_id'=>'required|exists:inventory_items,id','physical_quantity'=>'required|integer|min:0','notes'=>'nullable|string'],'search'=>['opname_no','status','notes']],
            default=>abort(404),
        };
    }
    private function can(string $action): bool { return auth()->user()?->can("company-inventory.{$action}") || auth()->user()?->hasAnyRole(['super_admin','manajer_pimpro','user_area_gudang']); }
    private function authorizeModule(string $action): void { abort_unless($this->can($action),403); }
}
