<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'title' => 'Login Area Internal',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'has_login_access' => true], $request->boolean('remember'))) {
            return back()
                ->withErrors([
                    'email' => 'Email atau password tidak cocok.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = $request->user()?->loadMissing('roles');
        $redirectTo = match (true) {
            $user?->hasAnyRole(['user_area_gudang', 'admin_gudang']) => route('admin.gudang.index'),
            $user?->hasAnyRole(['keuangan', 'admin_keuangan']) => route('admin.dashboard'),
            default => route('admin.dashboard'),
        };

        return redirect()->intended($redirectTo);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
