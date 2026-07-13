<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function chatUser(string $email): User
{
    return User::factory()->create(['email' => $email, 'phone' => '0800000000']);
}

test('authenticated user can open global chat and send a message', function () {
    Queue::fake();
    $user = chatUser('one@example.test');

    $bootstrap = $this->actingAs($user)->getJson('/admin/chat/bootstrap')->assertOk();
    $globalId = collect($bootstrap->json('conversations'))->firstWhere('type', 'global')['id'];

    $this->postJson("/admin/chat/conversations/{$globalId}/messages", ['body' => 'Halo tim'])
        ->assertCreated()
        ->assertJsonPath('body', 'Halo tim');

    $this->assertDatabaseHas('chat_messages', ['conversation_id' => $globalId, 'sender_id' => $user->id, 'body' => 'Halo tim']);
    Queue::assertPushedOn('chat', BroadcastEvent::class);
});

test('user picker lists every other account and excludes current user', function () {
    $current = chatUser('current@example.test');
    $second = chatUser('picker-two@example.test');
    $third = chatUser('picker-three@example.test');

    $response = $this->actingAs($current)->getJson('/admin/chat/bootstrap')->assertOk();

    $response->assertJsonCount(2, 'users');
    expect(collect($response->json('users'))->pluck('id')->all())
        ->toContain($second->id, $third->id)
        ->not->toContain($current->id);
});

test('online users are detected from active login sessions even when chat panel is closed', function () {
    config()->set('session.driver', 'database');
    config()->set('session.table', 'sessions');
    $current = chatUser('online-current@example.test');
    $online = chatUser('online-other@example.test');
    $offline = chatUser('offline-other@example.test');

    DB::table('sessions')->insert([
        'id' => 'active-user-session',
        'user_id' => $online->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Feature test',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $response = $this->actingAs($current)->getJson('/admin/chat/bootstrap')->assertOk();
    expect($response->json('online_user_ids'))
        ->toContain($current->id, $online->id)
        ->not->toContain($offline->id);
});

test('private conversation can only be read by its participants', function () {
    $first = chatUser('first@example.test');
    $second = chatUser('second@example.test');
    $outsider = chatUser('outsider@example.test');

    $conversationId = $this->actingAs($first)->postJson('/admin/chat/direct', ['user_id' => $second->id])->assertOk()->json('id');

    $this->actingAs($second)->getJson("/admin/chat/conversations/{$conversationId}/messages")->assertOk();
    $this->actingAs($outsider)->getJson("/admin/chat/conversations/{$conversationId}/messages")->assertForbidden();
});

test('private conversation never contains messages from global chat', function () {
    $first = chatUser('isolated-first@example.test');
    $second = chatUser('isolated-second@example.test');
    $globalId = collect($this->actingAs($first)->getJson('/admin/chat/bootstrap')->json('conversations'))->firstWhere('type', 'global')['id'];
    $this->postJson("/admin/chat/conversations/{$globalId}/messages", ['body' => 'Hanya global'])->assertCreated();
    $directId = $this->postJson('/admin/chat/direct', ['user_id' => $second->id])->assertOk()->json('id');
    $directMessageId = $this->postJson("/admin/chat/conversations/{$directId}/messages", ['body' => 'Hanya private'])->assertCreated()->json('id');

    $messages = $this->getJson("/admin/chat/conversations/{$directId}/messages")->assertOk();
    $messages->assertJsonFragment(['id' => $directMessageId, 'body' => 'Hanya private']);
    $messages->assertJsonMissing(['body' => 'Hanya global']);
    expect(collect($messages->json('messages'))->pluck('conversation_id')->unique()->all())->toBe([$directId]);
});

test('unread badge identifies its conversation and disappears after read', function () {
    $sender = chatUser('badge-sender@example.test');
    $recipient = chatUser('badge-recipient@example.test');
    $conversationId = $this->actingAs($sender)->postJson('/admin/chat/direct', ['user_id' => $recipient->id])->json('id');
    $this->postJson("/admin/chat/conversations/{$conversationId}/messages", ['body' => 'Pesan baru'])->assertCreated();

    $beforeRead = $this->actingAs($recipient)->getJson('/admin/chat/bootstrap')->assertOk();
    $conversation = collect($beforeRead->json('conversations'))->firstWhere('id', $conversationId);
    expect($conversation['name'])->toBe($sender->name)
        ->and($conversation['type'])->toBe('direct')
        ->and($conversation['unread_count'])->toBe(1);

    $this->postJson("/admin/chat/conversations/{$conversationId}/read")->assertOk();
    $afterRead = $this->getJson('/admin/chat/bootstrap')->assertOk();
    expect(collect($afterRead->json('conversations'))->firstWhere('id', $conversationId)['unread_count'])->toBe(0);
});

test('sender can recall unread message but not one already read', function () {
    $sender = chatUser('sender@example.test');
    $recipient = chatUser('recipient@example.test');
    $conversationId = $this->actingAs($sender)->postJson('/admin/chat/direct', ['user_id' => $recipient->id])->json('id');

    $unreadId = $this->postJson("/admin/chat/conversations/{$conversationId}/messages", ['body' => 'Belum dibaca'])->json('id');
    $this->postJson("/admin/chat/messages/{$unreadId}/recall")->assertOk()->assertJsonPath('body', null);

    $readId = $this->postJson("/admin/chat/conversations/{$conversationId}/messages", ['body' => 'Sudah dibaca'])->json('id');
    $this->actingAs($recipient)->postJson("/admin/chat/conversations/{$conversationId}/read")->assertOk();
    $this->actingAs($sender)->postJson("/admin/chat/messages/{$readId}/recall")->assertStatus(422);
});

test('participant can upload and open attachment while outsider cannot', function () {
    Storage::fake('local');
    $sender = chatUser('media-sender@example.test');
    $recipient = chatUser('media-recipient@example.test');
    $outsider = chatUser('media-outsider@example.test');
    $conversationId = $this->actingAs($sender)->postJson('/admin/chat/direct', ['user_id' => $recipient->id])->json('id');

    $message = $this->post('/admin/chat/conversations/'.$conversationId.'/messages', [
        'body' => 'Foto progres',
        'attachment' => UploadedFile::fake()->createWithContent(
            'progres.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
        ),
    ])->assertCreated()->assertJsonPath('attachment.is_image', true)->json();

    Storage::disk('local')->assertExists(ChatMessage::findOrFail($message['id'])->attachment_path);
    $this->actingAs($recipient)->get($message['attachment']['url'])->assertOk();
    $this->actingAs($outsider)->get($message['attachment']['url'])->assertForbidden();
});

test('deleting one message and clearing chat only affects current user view', function () {
    $sender = chatUser('delete-sender@example.test');
    $recipient = chatUser('delete-recipient@example.test');
    $conversationId = $this->actingAs($sender)->postJson('/admin/chat/direct', ['user_id' => $recipient->id])->json('id');
    $messageId = $this->postJson("/admin/chat/conversations/{$conversationId}/messages", ['body' => 'Rahasia'])->json('id');

    $this->deleteJson("/admin/chat/messages/{$messageId}")->assertOk();
    $this->getJson("/admin/chat/conversations/{$conversationId}/messages")->assertJsonMissing(['id' => $messageId]);
    $this->actingAs($recipient)->getJson("/admin/chat/conversations/{$conversationId}/messages")->assertJsonFragment(['id' => $messageId]);

    $this->deleteJson("/admin/chat/conversations/{$conversationId}")->assertOk();
    $this->getJson("/admin/chat/conversations/{$conversationId}/messages")->assertJsonCount(0, 'messages');
    expect(ChatMessage::find($messageId))->not->toBeNull();
});

test('clearing global chat hides it only for the user who deleted it', function () {
    $first = chatUser('global-clear-first@example.test');
    $second = chatUser('global-clear-second@example.test');
    $globalId = collect($this->actingAs($first)->getJson('/admin/chat/bootstrap')->json('conversations'))->firstWhere('type', 'global')['id'];
    $messageId = $this->postJson("/admin/chat/conversations/{$globalId}/messages", ['body' => 'Pengumuman global'])->assertCreated()->json('id');

    $this->deleteJson("/admin/chat/conversations/{$globalId}")->assertOk();
    $this->getJson("/admin/chat/conversations/{$globalId}/messages")->assertJsonMissing(['id' => $messageId]);
    $this->actingAs($second)->getJson("/admin/chat/conversations/{$globalId}/messages")->assertJsonFragment(['id' => $messageId]);
    $this->assertDatabaseHas('chat_messages', ['id' => $messageId, 'body' => 'Pengumuman global']);
});

test('muting global chat is private to each user and can be removed', function () {
    $first = chatUser('mute-first@example.test');
    $second = chatUser('mute-second@example.test');
    $globalId = collect($this->actingAs($first)->getJson('/admin/chat/bootstrap')->json('conversations'))->firstWhere('type', 'global')['id'];
    $this->actingAs($second)->getJson('/admin/chat/bootstrap')->assertOk();

    $muted = $this->actingAs($first)->postJson("/admin/chat/conversations/{$globalId}/mute", ['duration' => '8_hours'])
        ->assertOk()
        ->assertJsonPath('is_muted', true);
    expect($muted->json('muted_until'))->not->toBeNull();

    $firstConversation = collect($this->getJson('/admin/chat/bootstrap')->json('conversations'))->firstWhere('id', $globalId);
    $secondConversation = collect($this->actingAs($second)->getJson('/admin/chat/bootstrap')->json('conversations'))->firstWhere('id', $globalId);
    expect($firstConversation['is_muted'])->toBeTrue()
        ->and($secondConversation['is_muted'])->toBeFalse();

    $this->actingAs($first)->postJson("/admin/chat/conversations/{$globalId}/mute", ['duration' => 'unmute'])
        ->assertOk()
        ->assertJsonPath('is_muted', false);
});
