<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Costumer;
use App\Models\CustomerDocument;
use App\Models\DokumenCostumer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CustomerDocumentRepositoryController extends Controller
{
    public function index(Request $r): Response
    {
        abort_unless($r->user()?->can('customer.view') || $r->user()?->can('booking.view'), 403);
        $customerId = $r->integer('customer');
        $customers = Costumer::orderBy('nama')->get(['id', 'kode_costumer', 'nama', 'no_identitas'])->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->kode_costumer.' — '.$x->nama, 'identity' => $x->no_identitas]);
        $documents = CustomerDocument::with(['customer:id,nama,kode_costumer', 'documentType:id,kode_dokumen,nama_dokumen'])->when($customerId, fn ($q) => $q->where('costumer_id', $customerId))->latest()->paginate(20)->withQueryString()->through(fn ($x) => ['id' => $x->id, 'customer' => $x->customer?->nama, 'customer_code' => $x->customer?->kode_costumer, 'document_type' => $x->documentType?->nama_dokumen ?: $x->label, 'document_code' => $x->documentType?->kode_dokumen, 'file_name' => $x->nama_file, 'path' => $x->path_file, 'party_scope' => $x->party_scope, 'version' => $x->version, 'status' => $x->status, 'expires_at' => $x->expires_at?->format('d/m/Y'), 'notes' => $x->keterangan]);

        return Inertia::render('Admin/CustomerDocuments/Index', ['title' => 'Repository Dokumen Customer', 'baseUrl' => route('admin.customer-documents.index', absolute: false), 'rows' => $documents, 'customers' => $customers, 'types' => DokumenCostumer::query()->finalized()->where('status', 'aktif')->orderBy('nama_dokumen')->get()->map(fn ($x) => ['value' => (string) $x->id, 'label' => $x->nama_dokumen.' ('.$x->kode_dokumen.')']), 'filters' => ['customer' => $customerId ? (string) $customerId : ''], 'canManage' => $r->user()?->can('customer.update') || $r->user()?->can('booking.update')]);
    }

    public function store(Request $r): RedirectResponse
    {
        abort_unless($r->user()?->can('customer.update') || $r->user()?->can('booking.update'), 403);
        $v = $r->validate(['costumer_id' => 'required|exists:costumers,id', 'dokumen_costumer_id' => 'nullable|exists:dokumen_costumers,id', 'label' => 'nullable|string|max:150', 'party_scope' => 'required|in:customer,spouse,both', 'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'document_date' => 'nullable|date', 'expires_at' => 'nullable|date', 'keterangan' => 'nullable|string']);
        $file = $v['file'];
        $path = null;
        try {
            $path = $file->store('customer-repository/'.$v['costumer_id'], 'public');
            CustomerDocument::create([...collect($v)->except('file')->all(), 'nama_file' => $file->getClientOriginalName(), 'path_file' => $path, 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'uploaded_by' => $r->user()->id]);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            report($exception);

            return back()->withInput()->with('error', 'Dokumen gagal disimpan. Silakan coba kembali.');
        }

        return back()->with('success', 'Dokumen disimpan ke repository customer.');
    }

    public function replace(Request $r, CustomerDocument $document): RedirectResponse
    {
        abort_unless($r->user()?->can('customer.update') || $r->user()?->can('booking.update'), 403);
        $v = $r->validate(['file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx', 'keterangan' => 'nullable|string']);
        $file = $v['file'];
        $path = null;
        try {
            $path = $file->store('customer-repository/'.$document->costumer_id, 'public');
            $next = CustomerDocument::create([...$document->only(['costumer_id', 'dokumen_costumer_id', 'label', 'party_scope', 'document_date', 'expires_at']), 'nama_file' => $file->getClientOriginalName(), 'path_file' => $path, 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'version' => $document->version + 1, 'replaces_document_id' => $document->id, 'keterangan' => $v['keterangan'] ?? $document->keterangan, 'uploaded_by' => $r->user()->id]);
            $document->update(['status' => 'replaced']);
            $document->selections()->where('is_selected', true)->update(['customer_document_id' => $next->id, 'nama_file' => $next->nama_file, 'path_file' => $next->path_file, 'mime_type' => $next->mime_type, 'file_size' => $next->file_size]);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            report($exception);

            return back()->withInput()->with('error', 'Versi dokumen gagal disimpan. Silakan coba kembali.');
        }

        return back()->with('success', 'Versi dokumen repository berhasil diganti.');
    }
}
