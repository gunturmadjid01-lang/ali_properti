<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Perumahan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ScopesActivePerumahan
{
    protected function shouldScopeToActivePerumahan(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing', 'supervisor_marketing'])
            && ! $user->hasAnyRole(['owner', 'super_admin']);
    }

    protected function activePerumahanId(Request $request): ?int
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        if ($user->hasAnyRole(['owner', 'super_admin'])) {
            return (int) ($request->session()->get('active_perumahan_id') ?: Perumahan::query()->value('id'));
        }

        $allowedIds = $user->perumahans()->pluck('perumahans.id')->map(fn ($id) => (int) $id)->all();

        if (empty($allowedIds)) {
            return null;
        }

        $activeId = (int) $request->session()->get('active_perumahan_id');

        return in_array($activeId, $allowedIds, true) ? $activeId : $allowedIds[0];
    }

    protected function assignedPerumahanIds(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        if ($user->hasAnyRole(['owner', 'super_admin'])) {
            return Perumahan::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $user->perumahans()->pluck('perumahans.id')->map(fn ($id) => (int) $id)->all();
    }

    protected function scopeToActivePerumahan(Builder $query, Request $request, string $column = 'perumahan_id'): Builder
    {
        if (! $this->shouldScopeToActivePerumahan($request)) {
            return $query;
        }

        $activeId = $this->activePerumahanId($request);

        return $activeId ? $query->where($column, $activeId) : $query->whereRaw('1 = 0');
    }

    protected function ensureActivePerumahan(Request $request): int
    {
        $activeId = $this->activePerumahanId($request);

        if (! $activeId) {
            throw ValidationException::withMessages([
                'perumahan_id' => 'User ini belum ditugaskan ke perumahan mana pun.',
            ]);
        }

        return $activeId;
    }

    protected function ensurePerumahanAllowed(Request $request, int $perumahanId): void
    {
        if (! $this->shouldScopeToActivePerumahan($request)) {
            return;
        }

        abort_unless($perumahanId === $this->ensureActivePerumahan($request), 403);
    }
}
