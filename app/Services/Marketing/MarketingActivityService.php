<?php

namespace App\Services\Marketing;

use App\Models\MarketingLeadActivity;
use Illuminate\Database\Eloquent\Model;

class MarketingActivityService
{
    public function record(?int $customerId, string $type, string $title, ?Model $source = null, ?string $note = null, array $metadata = [], ?string $statusFrom = null, ?string $statusTo = null): ?MarketingLeadActivity
    {
        if (! $customerId) {
            return null;
        }

        $auditMetadata = [
            ...$metadata,
            'actor_role' => auth()->user()?->roles?->pluck('name')->values()->all(),
            'source_table' => $source?->getTable(),
            'after' => $metadata['after'] ?? $source?->getAttributes(),
        ];

        return MarketingLeadActivity::query()->create([
            'costumer_id' => $customerId,
            'user_id' => auth()->id(),
            'activity_type' => $type,
            'title' => $title,
            'status_from' => $statusFrom,
            'status_to' => $statusTo ?: ($statusFrom ?: 'unchanged'),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'activity_at' => now(),
            'note' => $note,
            'metadata' => $auditMetadata,
            'source_url' => request()?->fullUrl(),
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 1000),
        ]);
    }
}
