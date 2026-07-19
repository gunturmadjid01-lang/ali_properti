<?php

namespace App\Http\Middleware;

use App\Models\AppNotification;
use App\Models\MarketingReminder;
use App\Models\Perumahan;
use App\Models\PettyCashAccount;
use App\Support\SchemaMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $user = $request->user();

        return [
            ...parent::share($request),
            'appName' => config('app.name', 'Sidratul Muntaha'),
            // Do not hydrate hundreds of permission models for JSON, upload, chat,
            // and other non-Inertia requests that pass through the web middleware.
            'auth' => fn () => $this->authPayload($request, $user),
            'notifications' => fn () => $this->notifications($request, $user),
            'sidebar_badges' => fn () => [
                'reminder_follow_up' => $this->reminderFollowUpCount($user),
            ],
            'flash' => [
                // Nested closures are not resolved as lazy Inertia props. Send the
                // actual scalar values so the global response modal can read them.
                'id' => $request->session()->has('success') || $request->session()->has('error') ? uniqid('flash_', true) : null,
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'validation_id' => $request->session()->has('errors') ? uniqid('validation_', true) : null,
            ],
        ];
    }

    protected function authPayload(Request $request, $user): array
    {
        $user?->loadMissing(['roles', 'kantorCabang', 'perumahans']);
        $assignedPerumahans = $this->assignedPerumahans($request, $user);
        $needsActivePerumahanSelection = $this->needsActivePerumahanSelection($request, $user, $assignedPerumahans);
        $activePerumahan = $this->activePerumahan($request, $assignedPerumahans);
        $permissions = $this->permissionNames($user);

        if ($user && SchemaMetadata::hasTable('petty_cash_accounts') && PettyCashAccount::query()->where('assigned_user_id', $user->id)->exists()) {
            $permissions = array_values(array_unique([...$permissions, 'petty-cash.view']));
        }

        return [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'avatar_url' => $user->avatar ? route('media', ['path' => $user->avatar], false) : null,
                'roles' => $user->roles->pluck('name')->values()->all(),
                'permissions' => $permissions,
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
        ];
    }

    protected function permissionNames($user): array
    {
        if (! $user) {
            return [];
        }

        $modelType = $user->getMorphClass();

        return DB::table('permissions')
            ->where(function ($query) use ($user, $modelType): void {
                $query->whereExists(function ($direct) use ($user, $modelType): void {
                    $direct->selectRaw('1')
                        ->from('model_has_permissions')
                        ->whereColumn('model_has_permissions.permission_id', 'permissions.id')
                        ->where('model_has_permissions.model_type', $modelType)
                        ->where('model_has_permissions.model_id', $user->getKey());
                })->orWhereExists(function ($viaRole) use ($user, $modelType): void {
                    $viaRole->selectRaw('1')
                        ->from('role_has_permissions')
                        ->join('model_has_roles', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
                        ->whereColumn('role_has_permissions.permission_id', 'permissions.id')
                        ->where('model_has_roles.model_type', $modelType)
                        ->where('model_has_roles.model_id', $user->getKey());
                });
            })
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->values()
            ->all();
    }

    protected function reminderFollowUpCount($user): int
    {
        if (! $user || ! SchemaMetadata::hasTable('marketing_reminders')) {
            return 0;
        }

        $cacheKey = 'sidebar-reminders:'.$user->id.':'.request()->session()->get('active_perumahan_id', 'none');

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($user) {
            return MarketingReminder::query()
                ->where('status', 'menunggu')
                ->when($this->shouldScopeToActivePerumahan($user), fn (Builder $query) => $query->whereHas('costumer', fn (Builder $query) => $query->where('perumahan_id', $this->activePerumahanIdForUser($user))))
                ->when($user->hasAnyRole(['marketing', 'area_marketing']), fn (Builder $query) => $query->where(function (Builder $query) use ($user): void {
                    $query->where('user_id', $user->id)
                        ->orWhereHas('costumer', fn (Builder $query) => $query->where('created_by', $user->id));
                }))
                ->count();
        });
    }

    protected function shouldScopeToActivePerumahan($user): bool
    {
        return (bool) $user && ! $user->hasAnyRole(['owner', 'super_admin']);
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
        return (bool) $user
            && ! $user->hasAnyRole(['owner', 'super_admin'])
            && count($assignedPerumahans) > 1
            && ! $request->session()->has('active_perumahan_id');
    }

    protected function notifications(Request $request, $user): array
    {
        if (! $user || ! SchemaMetadata::hasTable('app_notifications')) {
            return ['unread_count' => 0, 'latest' => []];
        }

        $roles = $user->roles->pluck('name')->sort()->values()->all();
        $cacheKey = 'sidebar-notifications:'.$user->id.':'.sha1(json_encode($roles));

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($user, $roles) {
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
        });
    }

    protected function assignedPerumahans(Request $request, $user): array
    {
        if (! SchemaMetadata::hasTable('perumahans')) {
            return [];
        }

        $cacheKey = 'assigned-perumahans:'.($user?->id ?? 0).':'.(int) ($user?->updated_at?->timestamp ?? 0);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($user) {
            $query = Perumahan::query()->finalized()->orderBy('nama_perusahaan');

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
        });
    }

    protected function activePerumahan(Request $request, array $assignedPerumahans): ?array
    {
        if ($request->user()?->hasAnyRole(['owner', 'super_admin'])) {
            $request->session()->forget('active_perumahan_id');

            return null;
        }

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
