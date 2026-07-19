<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSalary;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeSalaryMasterController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizePayroll($request); $search=trim((string)$request->query('search','')); $status=(string)$request->query('status','all');
        $rows=EmployeeSalary::with(['user.jobPosition','user.kantorCabang'])->when($search!=='',fn(Builder $q)=>$q->whereHas('user',fn(Builder $q)=>$q->where('name','like',"%{$search}%")->orWhere('employee_number','like',"%{$search}%")->orWhere('job_title','like',"%{$search}%")))->when($status==='active',fn($q)=>$q->where('is_active',true))->when($status==='inactive',fn($q)=>$q->where('is_active',false))->latest('effective_from')->paginate(15)->withQueryString()->through(fn(EmployeeSalary $s)=>['id'=>$s->id,'employee_name'=>$s->user?->name,'employee_number'=>$s->user?->employee_number,'job_title'=>$s->user?->jobPosition?->name??$s->user?->job_title,'branch'=>$s->user?->kantorCabang?->nama_cabang,'basic_salary'=>(float)$s->basic_salary,'fixed_allowance'=>(float)$s->fixed_allowance,'total_salary'=>(float)$s->basic_salary+(float)$s->fixed_allowance,'effective_from'=>$s->effective_from->format('Y-m-d'),'effective_until'=>$s->effective_until?->format('Y-m-d'),'is_active'=>$s->is_active,'edit_url'=>route('admin.salary-master.edit',$s,false)]);
        return Inertia::render('Admin/EmployeeSalaryMaster/Index',['title'=>'Daftar Gaji Pegawai','baseUrl'=>route('admin.salary-master.index',absolute:false),'createUrl'=>route('admin.salary-master.create',absolute:false),'filters'=>compact('search','status'),'rows'=>$rows]);
    }
    public function create(Request $r): Response { $this->authorizePayroll($r); return $this->form(); }
    public function edit(Request $r, EmployeeSalary $salary): Response { $this->authorizePayroll($r); return $this->form($salary); }
    public function store(Request $r): RedirectResponse { $this->authorizePayroll($r); $d=$this->validateData($r); DB::transaction(function()use($d,$r){EmployeeSalary::create([...$d,'created_by'=>$r->user()->id,'updated_by'=>$r->user()->id]);$this->recalculate((int)$d['user_id']);}); return to_route('admin.salary-master.index')->with('success','Daftar gaji pegawai ditambahkan.'); }
    public function update(Request $r, EmployeeSalary $salary): RedirectResponse { $this->authorizePayroll($r); $old=(int)$salary->user_id;$d=$this->validateData($r,$salary);DB::transaction(function()use($salary,$d,$old,$r){$salary->update([...$d,'updated_by'=>$r->user()->id]);$this->recalculate($old);$this->recalculate((int)$d['user_id']);});return to_route('admin.salary-master.index')->with('success','Daftar gaji diperbarui.'); }
    public function toggle(Request $r, EmployeeSalary $salary): RedirectResponse { $this->authorizePayroll($r);$salary->update(['is_active'=>$r->boolean('is_active'),'updated_by'=>$r->user()->id]);$this->recalculate((int)$salary->user_id);return back()->with('success','Status daftar gaji diperbarui.'); }
    private function validateData(Request $r,?EmployeeSalary $s=null):array{return $r->validate(['user_id'=>['required',Rule::exists('users','id')->whereNotNull('job_position_id')],'basic_salary'=>['required','numeric','min:0'],'fixed_allowance'=>['nullable','numeric','min:0'],'effective_from'=>['required','date',Rule::unique('employee_salaries','effective_from')->where(fn($q)=>$q->where('user_id',$r->user_id))->ignore($s?->id)],'is_active'=>['required','boolean'],'notes'=>['nullable','string','max:1000']]);}
    private function recalculate(int $id):void{$rows=EmployeeSalary::where('user_id',$id)->where('is_active',true)->orderBy('effective_from')->get();foreach($rows as $i=>$s){$next=$rows->get($i+1);$s->updateQuietly(['effective_until'=>$next?CarbonImmutable::parse($next->effective_from)->subDay():null]);}EmployeeSalary::where('user_id',$id)->where('is_active',false)->update(['effective_until'=>null]);}
    private function form(?EmployeeSalary $s=null):Response{return Inertia::render('Admin/EmployeeSalaryMaster/FormPage',['title'=>$s?'Edit Daftar Gaji':'Tambah Daftar Gaji','baseUrl'=>route('admin.salary-master.index',absolute:false),'actionUrl'=>$s?route('admin.salary-master.update',$s,false):route('admin.salary-master.store',absolute:false),'method'=>$s?'put':'post','initialData'=>['user_id'=>(string)($s?->user_id??''),'basic_salary'=>(float)($s?->basic_salary??0),'fixed_allowance'=>(float)($s?->fixed_allowance??0),'effective_from'=>$s?->effective_from?->format('Y-m-d')??now()->toDateString(),'is_active'=>$s?->is_active??true,'notes'=>$s?->notes??''],'employees'=>User::with('jobPosition')->where('employment_status','aktif')->whereNotNull('job_position_id')->orderBy('name')->get()->map(fn($u)=>['value'=>(string)$u->id,'label'=>trim(($u->employee_number?"{$u->employee_number} · ":'').$u->name.' · '.$u->jobPosition?->name)])->values()]);}
    private function authorizePayroll(Request $r):void{abort_unless($r->user()?->can('payroll.manage')||$r->user()?->hasAnyRole(['super_admin','owner','manager']),403);}
}
