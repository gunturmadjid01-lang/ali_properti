<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAreaPages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $user = $request->user()?->loadMissing('roles');
        $roles = $user?->roles->pluck('name')->values()->all() ?? $this->guestRoles();
        $page = AdminAreaPages::find($slug);

        abort_if($page === [], 404);
        abort_unless(AdminAreaPages::allowed($slug, $roles), 403);

        return Inertia::render('Admin/Area/Index', [
            ...$page,
            'slug' => $slug,
            'roles' => $roles,
        ]);
    }

    protected function guestRoles(): array
    {
        return [
            'owner',
            'admin',
            'admin_keuangan',
            'marketing',
            'supervisor_marketing',
            'area_marketing',
            'manajer_pimpro',
            'teknik',
            'pengawas',
            'admin_konsumen',
            'user_area_gudang',
            'bag_legal',
            'admin_kpr',
        ];
    }
}
