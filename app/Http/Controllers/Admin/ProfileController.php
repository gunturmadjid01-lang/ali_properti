<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
                'employee_number' => $user?->employee_number,
                'job_title' => $user?->job_title,
                'join_date' => $user?->join_date?->format('Y-m-d'),
                'employment_type' => $user?->employment_type,
                'employment_status' => $user?->employment_status,
                'tax_number' => $user?->tax_number,
                'bpjs_health_number' => $user?->bpjs_health_number,
                'bpjs_employment_number' => $user?->bpjs_employment_number,
                'payroll_bank_name' => $user?->payroll_bank_name,
                'payroll_bank_account' => $user?->payroll_bank_account,
                'payroll_bank_holder' => $user?->payroll_bank_holder,
                'avatar' => $user?->avatar,
                'avatar_url' => $user?->avatar ? route('media', ['path' => $user->avatar], false) : null,
                'roles' => $user?->roles->pluck('name')->values()->all() ?? ['guest_admin'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:30'],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_number')->ignore($user->id)],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'bpjs_health_number' => ['nullable', 'string', 'max:50'],
            'bpjs_employment_number' => ['nullable', 'string', 'max:50'],
            'payroll_bank_name' => ['nullable', 'string', 'max:100'],
            'payroll_bank_account' => ['nullable', 'string', 'max:100'],
            'payroll_bank_holder' => ['nullable', 'string', 'max:100'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ], [
            'avatar.max' => 'Ukuran foto profil maksimal 2 MB.',
            'avatar.mimes' => 'Foto profil harus berformat JPG, PNG, atau WEBP.',
        ]);

        $oldAvatar = $user->avatar;
        $newAvatar = $oldAvatar;

        if ($request->boolean('remove_avatar')) {
            $newAvatar = null;
        }

        if ($request->hasFile('avatar')) {
            $newAvatar = $request->file('avatar')->store('avatars', 'public');
        }

        $emailChanged = $user->email !== $validated['email'];
        $user->fill([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => trim($validated['phone']),
            'employee_number' => filled($validated['employee_number'] ?? null) ? trim($validated['employee_number']) : null,
            'tax_number' => filled($validated['tax_number'] ?? null) ? trim($validated['tax_number']) : null,
            'bpjs_health_number' => filled($validated['bpjs_health_number'] ?? null) ? trim($validated['bpjs_health_number']) : null,
            'bpjs_employment_number' => filled($validated['bpjs_employment_number'] ?? null) ? trim($validated['bpjs_employment_number']) : null,
            'payroll_bank_name' => filled($validated['payroll_bank_name'] ?? null) ? trim($validated['payroll_bank_name']) : null,
            'payroll_bank_account' => filled($validated['payroll_bank_account'] ?? null) ? trim($validated['payroll_bank_account']) : null,
            'payroll_bank_holder' => filled($validated['payroll_bank_holder'] ?? null) ? trim($validated['payroll_bank_holder']) : null,
            'avatar' => $newAvatar,
        ]);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        if ($oldAvatar && $oldAvatar !== $newAvatar) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.confirmed' => 'Konfirmasi password baru tidak sesuai.',
        ]);

        $user->update(['password' => $validated['password']]);
        $request->session()->regenerate();

        return back()->with('success', 'Password berhasil diperbarui.');
    }
}
