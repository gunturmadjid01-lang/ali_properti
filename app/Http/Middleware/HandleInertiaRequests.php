<?php

namespace App\Http\Middleware;

use App\Models\Perumahan;
use App\Models\AppNotification;
use App\Models\MarketingReminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing(['roles', 'permissions', 'kantorCabang', 'perumahans']);
        $assignedPerumahans = $this->assignedPerumahans($request, $user);
        $needsActivePerumahanSelection = $this->needsActivePerumahanSelection($request, $user, $assignedPerumahans);
        $activePerumahan = $this->activePerumahan($request, $assignedPerumahans);

        return [
            ...parent::share($request),
            'appName' => config('app.name', 'Sidratul Muntaha'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'avatar_url' => $user->avatar ? route('media', ['path' => $user->avatar], false) : null,
                    'roles' => $user->roles->pluck('name')->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                    'kantor_cabang' => $user->kantorCabang ? [
                        'id' => $user->kantorCabang->id,
                        'nama_cabang' => $user->kantorCabang->nama_cabang,
                    ] : null,
                    'assigned_perumahans' => $assignedPerumahans,
                    'active_perumahan' => $activePerumahan,
                    'needs_active_perumahan_selection' => $needsActivePerumahanSelection,
                ] : null,
                'assigned_perumahans' => $assignedPerumahans,
                'active_perumahan' => $activePerumahan,
                'needs_active_perumahan_selection' => $needsActivePerumahanSelection,
            ],
            'notifications' => fn () => $this->notifications($request, $user),
            'sidebar_badges' => fn () => [
                'reminder_follow_up' => $this->reminderFollowUpCount($user),
            ],
            'flash' => [
                'id' => fn () => $request->session()->has('success') || $request->session()->has('error') ? uniqid('flash_', true) : null,
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    protected function reminderFollowUpCount($user): int
    {
        if (! $user || ! Schema::hasTable('marketing_reminders')) {
            return 0;
        }

        return MarketingReminder::query()
            ->where('status', 'menunggu')
            ->when($this->shouldScopeToActivePerumahan($user), fn (Builder $query) => $query->whereHas('costumer', fn (Builder $query) => $query->where('perumahan_id', $this->activePerumahanIdForUser($user))))
            ->when($user->hasAnyRole(['marketing', 'area_marketing']), fn (Builder $query) => $query->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('costumer', fn (Builder $query) => $query->where('created_by', $user->id));
            }))
            ->count();
    }

    protected function shouldScopeToActivePerumahan($user): bool
    {
        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing', 'supervisor_marketing'])
            && ! $user->hasAnyRole(['owner', 'super_admin']);
    }

    protected function activePerumahanIdForUser($user): ?int
    {
        if (! $user) {
            return null;
        }

        $allowedIds = $user->perumahans->pluck('id')->map(fn ($id) => (int) $id)->all();
        $activeId = (int) request()->session()->get('active_perumahan_id');

        return in_array($activeId, $allowedIds, true) ? $activeId : ($allowedIds[0] ?? null);
    }

    protected function needsActivePerumahanSelection(Request $request, $user, array $assignedPerumahans): bool
    {
        return (bool) $user?->hasAnyRole(['marketing', 'area_marketing', 'supervisor_marketing'])
            && ! $user->hasAnyRole(['owner', 'super_admin'])
            && count($assignedPerumahans) > 1
            && ! $request->session()->has('active_perumahan_id');
    }

    protected function notifications(Request $request, $user): array
    {
        if (! $user || ! Schema::hasTable('app_notifications')) {
            return ['unread_count' => 0, 'latest' => []];
        }

        $roles = $user->roles->pluck('name')->all();
        $query = AppNotification::query()
            ->where(function (Builder $query) use ($user, $roles) {
                $query->where('user_id', $user->id);

                if (! empty($roles)) {
                    $query->orWhereIn('role', $roles);
                }
            });

        return [
            'unread_count' => (clone $query)->whereNull('read_at')->count(),
            'latest' => (clone $query)->latest('id')->limit(5)->get(['id', 'title', 'message', 'url', 'read_at'])->toArray(),
        ];
    }

    protected function assignedPerumahans(Request $request, $user): array
    {
        if (! Schema::hasTable('perumahans')) {
            return [];
        }

        $query = Perumahan::query()->orderBy('nama_perusahaan');

        if ($user && ! $user->hasAnyRole(['owner', 'super_admin'])) {
            $ids = $user->perumahans->pluck('id');
            $query->whereIn('id', $ids);
        }

        return $query
            ->get(['id', 'nama_perusahaan', 'cabang_id'])
            ->map(fn (Perumahan $perumahan) => [
                'id' => $perumahan->id,
                'nama_perusahaan' => $perumahan->nama_perusahaan,
                'value' => (string) $perumahan->id,
                'label' => $perumahan->nama_perusahaan,
            ])
            ->values()
            ->all();
    }

    protected function activePerumahan(Request $request, array $assignedPerumahans): ?array
    {
        if (empty($assignedPerumahans)) {
            $request->session()->forget('active_perumahan_id');

            return null;
        }

        $activeId = (int) $request->session()->get('active_perumahan_id');
        $active = collect($assignedPerumahans)->firstWhere('id', $activeId);

        if (! $active) {
            $active = $assignedPerumahans[0];
            $request->session()->put('active_perumahan_id', $active['id']);
        }

        return $active;
    }
}
