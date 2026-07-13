<?php

namespace App\Http\Controllers\Admin;

use App\Events\ChatUpdated;
use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

class ChatController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $global = ChatConversation::firstOrCreate(
            ['type' => 'global'],
            ['name' => 'Chat Global', 'created_by' => $user->id],
        );
        $this->ensureParticipant($global, $user->id);

        $conversations = ChatConversation::query()
            ->where(fn (Builder $query) => $query
                ->where('type', 'global')
                ->orWhereHas('participants', fn (Builder $participants) => $participants->where('users.id', $user->id)))
            ->with(['participants:id,name,email,avatar'])
            ->get()
            ->map(fn (ChatConversation $conversation) => $this->conversationPayload($conversation, $user->id))
            ->sortByDesc('last_message.created_at')
            ->values();

        return response()->json([
            'conversations' => $conversations,
            'users' => User::query()->whereKeyNot($user->id)->orderBy('name')->get(['id', 'name', 'email', 'avatar'])
                ->map(fn (User $item) => $this->userPayload($item)),
            'online_user_ids' => $this->onlineUserIds($user->id),
            'pusher_enabled' => config('broadcasting.default') === 'pusher' && filled(config('broadcasting.connections.pusher.key')),
        ]);
    }

    public function direct(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::notIn([$request->user()->id])]]);
        $ids = collect([$request->user()->id, (int) $data['user_id']])->sort()->values();
        $key = $ids->join(':');

        $conversation = DB::transaction(function () use ($ids, $key, $request) {
            $conversation = ChatConversation::firstOrCreate(
                ['direct_key' => $key],
                ['type' => 'direct', 'created_by' => $request->user()->id],
            );
            $conversation->participants()->syncWithoutDetaching($ids->all());
            return $conversation;
        });

        return response()->json($this->conversationPayload($conversation->load('participants'), $request->user()->id));
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($conversation, $request->user()->id);
        $participant = $this->ensureParticipant($conversation, $request->user()->id);
        $before = $request->integer('before');

        $messages = ChatMessage::query()
            ->where('conversation_id', $conversation->id)
            ->when($participant->cleared_at, fn (Builder $query, $cleared) => $query->where('created_at', '>', $cleared))
            ->when($before, fn (Builder $query, $id) => $query->where('id', '<', $id))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('chat_message_deletions')
                ->whereColumn('chat_message_deletions.message_id', 'chat_messages.id')
                ->where('chat_message_deletions.user_id', $request->user()->id))
            ->with('sender:id,name,avatar')
            ->latest('id')->limit(40)->get()->reverse()->values();

        return response()->json([
            'messages' => $messages->map(fn (ChatMessage $message) => $this->messagePayload($message)),
            'has_more' => $messages->count() === 40,
        ]);
    }

    public function store(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($conversation, $request->user()->id);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:15360', 'required_without:body'],
        ]);

        $attachment = $request->file('attachment');
        $attachmentPath = $attachment?->store('chat-attachments', 'local');
        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => filled($data['body'] ?? null) ? trim($data['body']) : null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachment?->getClientOriginalName(),
            'attachment_mime' => $attachment?->getMimeType(),
            'attachment_size' => $attachment?->getSize(),
        ])->load('sender:id,name,avatar');
        $this->ensureParticipant($conversation, $request->user()->id);
        $payload = $this->messagePayload($message);
        $this->broadcastSafely(new ChatUpdated($conversation, 'message.created', $payload));

        return response()->json($payload, 201);
    }

    public function read(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($conversation, $request->user()->id);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);
        $this->broadcastSafely(new ChatUpdated($conversation, 'conversation.read'));
        return response()->json(['ok' => true]);
    }

    public function mute(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($conversation, $request->user()->id);
        $data = $request->validate([
            'duration' => ['required', Rule::in(['8_hours', '12_hours', 'forever', 'unmute'])],
        ]);
        $this->ensureParticipant($conversation, $request->user()->id);

        $settings = match ($data['duration']) {
            '8_hours' => ['muted_until' => now()->addHours(8), 'muted_forever' => false],
            '12_hours' => ['muted_until' => now()->addHours(12), 'muted_forever' => false],
            'forever' => ['muted_until' => null, 'muted_forever' => true],
            default => ['muted_until' => null, 'muted_forever' => false],
        };
        $conversation->participants()->updateExistingPivot($request->user()->id, $settings);

        return response()->json($this->mutePayload((object) $settings));
    }

    public function deleteMessage(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeConversation($message->conversation, $request->user()->id);
        DB::table('chat_message_deletions')->updateOrInsert(
            ['message_id' => $message->id, 'user_id' => $request->user()->id],
            ['created_at' => now(), 'updated_at' => now()],
        );
        return response()->json(['ok' => true]);
    }

    public function recall(Request $request, ChatMessage $message): JsonResponse
    {
        abort_unless((int) $message->sender_id === (int) $request->user()->id, 403);
        abort_if($message->recalled_at, 422, 'Pesan sudah ditarik.');

        $hasBeenRead = DB::table('chat_participants')
            ->where('conversation_id', $message->conversation_id)
            ->where('user_id', '!=', $request->user()->id)
            ->where('last_read_at', '>=', $message->created_at)
            ->exists();
        abort_if($hasBeenRead, 422, 'Pesan sudah dibaca dan tidak dapat ditarik.');

        $message->update(['body' => null, 'recalled_at' => now()]);
        $payload = $this->messagePayload($message->load('sender:id,name,avatar'));
        $this->broadcastSafely(new ChatUpdated($message->conversation, 'message.recalled', $payload));
        return response()->json($payload);
    }

    public function clear(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($conversation, $request->user()->id);
        $this->ensureParticipant($conversation, $request->user()->id);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['cleared_at' => now(), 'last_read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function attachment(Request $request, ChatMessage $message)
    {
        $this->authorizeConversation($message->conversation, $request->user()->id);
        abort_if($message->recalled_at || ! $message->attachment_path || ! Storage::disk('local')->exists($message->attachment_path), 404);

        $path = Storage::disk('local')->path($message->attachment_path);
        $name = str_replace(["\r", "\n", '"'], '', basename($message->attachment_name ?: $path));

        if (str_starts_with((string) $message->attachment_mime, 'image/') || $message->attachment_mime === 'application/pdf') {
            return response()->file($path, [
                'Content-Type' => $message->attachment_mime,
                'Content-Disposition' => 'inline; filename="'.$name.'"',
            ]);
        }

        return response()->download($path, $name, ['Content-Type' => $message->attachment_mime ?: 'application/octet-stream']);
    }

    private function authorizeConversation(ChatConversation $conversation, int $userId): void
    {
        abort_unless($conversation->type === 'global' || $conversation->participants()->where('users.id', $userId)->exists(), 403);
    }

    private function broadcastSafely(ChatUpdated $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function onlineUserIds(int $currentUserId): array
    {
        if (config('session.driver') !== 'database' || ! DB::getSchemaBuilder()->hasTable(config('session.table', 'sessions'))) {
            return [$currentUserId];
        }

        return DB::table(config('session.table', 'sessions'))
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(2)->timestamp)
            ->pluck('user_id')
            ->push($currentUserId)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function ensureParticipant(ChatConversation $conversation, int $userId): object
    {
        $conversation->participants()->syncWithoutDetaching([$userId]);
        return DB::table('chat_participants')->where('conversation_id', $conversation->id)->where('user_id', $userId)->first();
    }

    private function conversationPayload(ChatConversation $conversation, int $userId): array
    {
        $conversation->loadMissing('participants:id,name,email,avatar');
        $participant = $this->ensureParticipant($conversation, $userId);
        $other = $conversation->participants->firstWhere('id', '!=', $userId);
        $visible = $conversation->messages()->when($participant->cleared_at, fn (Builder $query, $date) => $query->where('created_at', '>', $date));
        $last = (clone $visible)->with('sender:id,name,avatar')->latest('id')->first();
        $unread = (clone $visible)->where('sender_id', '!=', $userId)
            ->when($participant->last_read_at, fn (Builder $query, $date) => $query->where('created_at', '>', $date))
            ->count();

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'name' => $conversation->type === 'global' ? 'Chat Global' : ($other?->name ?? 'Pengguna'),
            'avatar_url' => $other ? $this->userPayload($other)['avatar_url'] : null,
            'other_user_id' => $other?->id,
            'unread_count' => $unread,
            ...$this->mutePayload($participant),
            'last_message' => $last ? $this->messagePayload($last) : null,
        ];
    }

    private function mutePayload(object $participant): array
    {
        $mutedUntil = filled($participant->muted_until ?? null)
            ? Carbon::parse($participant->muted_until)
            : null;
        $forever = (bool) ($participant->muted_forever ?? false);
        $isMuted = $forever || ($mutedUntil?->isFuture() ?? false);

        return [
            'is_muted' => $isMuted,
            'muted_forever' => $forever,
            'muted_until' => $mutedUntil?->toISOString(),
            'mute_label' => ! $isMuted
                ? null
                : ($forever ? 'Sampai dinyalakan kembali' : 'Sampai '.$mutedUntil->timezone(config('app.timezone'))->format('d M Y H:i')),
        ];
    }

    private function messagePayload(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender' => $message->sender ? $this->userPayload($message->sender) : null,
            'body' => $message->recalled_at ? null : $message->body,
            'attachment' => $message->recalled_at || ! $message->attachment_path ? null : [
                'name' => $message->attachment_name,
                'mime' => $message->attachment_mime,
                'size' => $message->attachment_size,
                'url' => route('admin.chat.message.attachment', ['message' => $message->id], false),
                'is_image' => str_starts_with((string) $message->attachment_mime, 'image/'),
            ],
            'recalled_at' => $message->recalled_at?->toISOString(),
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar ? route('media', ['path' => $user->avatar], false) : null,
        ];
    }
}
