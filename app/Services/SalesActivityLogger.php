<?php

namespace App\Services;

use App\Models\SalesActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SalesActivityLogger
{
    public function record(Request $request, Model $subject, string $event, ?string $oldStatus, ?string $newStatus, ?string $reason = null, array $old = [], array $new = []): SalesActivityLog
    {
        return SalesActivityLog::query()->create([
            'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'event' => $event,
            'old_status' => $oldStatus, 'new_status' => $newStatus, 'reason' => $reason,
            'old_values' => $old ?: null, 'new_values' => $new ?: null, 'user_id' => $request->user()?->id,
            'role_name' => $request->user()?->roles()->pluck('name')->join(','), 'source' => 'web',
            'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
