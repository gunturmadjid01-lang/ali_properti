<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()?->loadMissing('roles');

        return Inertia::render('Admin/Profile/Index', [
            'title' => 'Profil User',
            'user' => [
                'id' => $user?->id,
                'name' => $user?->name ?? 'Guest Admin',
                'email' => $user?->email ?? 'guest@ptali.com',
                'phone' => $user?->phone,
                'avatar' => $user?->avatar,
                'avatar_url' => $user?->avatar ? route('media', ['path' => $user->avatar], false) : null,
                'roles' => $user?->roles->pluck('name')->values()->all() ?? ['guest_admin'],
            ],
        ]);
    }
}
