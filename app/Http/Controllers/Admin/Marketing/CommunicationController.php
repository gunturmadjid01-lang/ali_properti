<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CrmCommunicationThread;
use App\Services\CrmCommunicationHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing-lead.view'), 403);
        $search = trim((string) $request->query('search', ''));
        $threads = CrmCommunicationThread::query()->with(['assignedUser:id,name', 'contactable'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('thread_no', 'like', "%{$search}%")->orWhere('contact_name', 'like', "%{$search}%")->orWhere('contact_address', 'like', "%{$search}%")))
            ->latest('last_message_at')->paginate(25)->withQueryString()->through(fn ($thread) => [
                'id' => $thread->id, 'thread_no' => $thread->thread_no, 'channel' => $thread->channel,
                'contact_name' => $thread->contact_name ?: 'Kontak belum dikenali', 'contact_address' => $thread->contact_address,
                'status' => $thread->status, 'last_message_at' => $thread->last_message_at?->format('d/m/Y H:i'),
                'assigned_to' => $thread->assignedUser?->name,
            ]);
        $selected = null;
        if ($request->integer('thread')) {
            $thread = CrmCommunicationThread::query()->with(['messages' => fn ($query) => $query->oldest(), 'contactable'])->find($request->integer('thread'));
            if ($thread) {
                $selected = ['id' => $thread->id, 'thread_no' => $thread->thread_no, 'channel' => $thread->channel, 'contact_name' => $thread->contact_name, 'contact_address' => $thread->contact_address, 'messages' => $thread->messages->map(fn ($message) => ['id' => $message->id, 'direction' => $message->direction, 'body' => $message->body, 'status' => $message->status, 'at' => $message->created_at?->format('d/m/Y H:i')])->values()];
            }
        }

        return Inertia::render('Admin/Marketing/Communication/Index', ['title' => 'Inbox Komunikasi CRM', 'threads' => $threads, 'selected' => $selected, 'filters' => ['search' => $search]]);
    }

    public function send(Request $request, CrmCommunicationThread $thread, CrmCommunicationHubService $hub): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin') || $request->user()?->can('marketing-lead.update'), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000'], 'template_code' => ['nullable', 'string', 'max:100']]);
        abort_unless($thread->contactable, 422, 'Kontak pada percakapan ini belum terhubung ke Lead atau Customer.');
        $hub->outgoing(['channel' => $thread->channel, 'body' => $data['body'], 'template_code' => $data['template_code'] ?? null, 'external_key' => $thread->external_key], $thread->contactable);
        return back()->with('success', 'Pesan dimasukkan ke antrean pengiriman.');
    }

    public function webhook(Request $request, CrmCommunicationHubService $hub): JsonResponse
    {
        $key = (string) config('services.crm_communication.webhook_key');
        abort_unless($key !== '' && hash_equals($key, (string) $request->header('X-Komunikasi-Key')), 401);
        $data = $request->validate(['channel' => ['required', 'in:whatsapp,email,sms'], 'external_key' => ['required', 'string', 'max:255'], 'sender_address' => ['required', 'string', 'max:255'], 'recipient_address' => ['nullable', 'string', 'max:255'], 'body' => ['nullable', 'string'], 'provider' => ['nullable', 'string', 'max:100'], 'provider_message_id' => ['nullable', 'string', 'max:255'], 'metadata' => ['nullable', 'array']]);
        $message = $hub->incoming($data);
        return response()->json(['ok' => true, 'message_id' => $message->id], 201);
    }
}
