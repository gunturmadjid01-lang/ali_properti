<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Notifications/Index', [
            'title' => 'Notifikasi',
            'rows' => $this->query($request)->latest('id')->paginate(20)->withQueryString(),
            'baseUrl' => route('admin.notifications.index', absolute: false),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $this->query($request)->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return back();
    }

    protected function query(Request $request): Builder
    {
        $user = $request->user();
        $roles = $user?->roles?->pluck('name')->all() ?? [];

        return AppNotification::query()
            ->where(function (Builder $query) use ($user, $roles) {
                $query->where('user_id', $user?->id);

                if (! empty($roles)) {
                    $query->orWhereIn('role', $roles);
                }
            });
    }
}
