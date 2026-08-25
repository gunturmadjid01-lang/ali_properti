<?php

namespace App\Services;

use App\Models\CrmCommunicationMessage;
use App\Models\CrmCommunicationThread;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

/** Fondasi komunikasi CRM; pengiriman nyata ditangani adapter provider terpisah. */
class CrmCommunicationHubService
{
    public function incoming(array $data, ?Model $contact = null): CrmCommunicationMessage
    {
        $thread = $this->thread($data, $contact);

        return $thread->messages()->create([
            'message_key' => 'MSG-'.Str::upper(Str::random(24)),
            'direction' => 'masuk',
            'sender_address' => $data['sender_address'] ?? null,
            'recipient_address' => $data['recipient_address'] ?? null,
            'body' => $data['body'] ?? null,
            'provider' => $data['provider'] ?? null,
            'provider_message_id' => $data['provider_message_id'] ?? null,
            'status' => 'terkirim',
            'metadata' => $data['metadata'] ?? [],
            'sent_at' => now(),
        ]);
    }

    public function outgoing(array $data, Model $contact): CrmCommunicationMessage
    {
        if ((bool) ($contact->do_not_contact ?? false)) {
            throw ValidationException::withMessages(['komunikasi' => 'Customer meminta agar tidak dihubungi.']);
        }

        $channel = (string) ($data['channel'] ?? '');
        $allowed = $contact->consent_channels ?? [];
        if ($channel && $contact->consent_status === 'denied') {
            throw ValidationException::withMessages(['komunikasi' => 'Kanal komunikasi ini tidak disetujui customer.']);
        }
        if ($channel && $contact->consent_status === 'granted' && $allowed && ! in_array($channel, $allowed, true)) {
            throw ValidationException::withMessages(['komunikasi' => 'Kanal komunikasi ini tidak termasuk consent customer.']);
        }

        $thread = $this->thread($data + ['channel' => $channel], $contact);

        return $thread->messages()->create([
            'message_key' => 'MSG-'.Str::upper(Str::random(24)),
            'direction' => 'keluar',
            'sender_address' => $data['sender_address'] ?? null,
            'recipient_address' => $data['recipient_address'] ?? ($contact->phone ?? $contact->email ?? null),
            'body' => $data['body'] ?? null,
            'template_code' => $data['template_code'] ?? null,
            'status' => 'antre',
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    private function thread(array $data, ?Model $contact): CrmCommunicationThread
    {
        $query = CrmCommunicationThread::query()->where('channel', $data['channel'] ?? 'whatsapp')
            ->when($data['external_key'] ?? null, fn ($q, $key) => $q->where('external_key', $key));
        $thread = $query->first();
        if (! $thread) {
            $thread = new CrmCommunicationThread([
                'thread_no' => 'COM-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5)),
                'channel' => $data['channel'] ?? 'whatsapp',
                'external_key' => $data['external_key'] ?? null,
                'contact_name' => $data['contact_name'] ?? $contact?->nama ?? $contact?->name,
                'contact_address' => $data['sender_address'] ?? $contact?->telepon ?? $contact?->phone ?? $contact?->email,
                'status' => 'open',
                'last_message_at' => now(),
            ]);
            if ($contact) {
                $thread->contactable()->associate($contact);
            }
            $thread->save();
        } else {
            $thread->update(['last_message_at' => now(), 'contact_name' => $thread->contact_name ?: ($contact?->nama ?? $contact?->name)]);
        }

        return $thread;
    }
}
