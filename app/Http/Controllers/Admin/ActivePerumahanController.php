<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perumahan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivePerumahanController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $allowedIds = $this->allowedPerumahanIds($request);

        $validated = $request->validate([
            'perumahan_id' => ['required', 'integer', Rule::in($allowedIds)],
        ]);

        $request->session()->put('active_perumahan_id', (int) $validated['perumahan_id']);

        return back()->with('success', 'Properti aktif berhasil diganti.');
    }

    protected function allowedPerumahanIds(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return Perumahan::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($user->hasAnyRole(['owner', 'super_admin'])) {
            return Perumahan::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $user->perumahans()->pluck('perumahans.id')->map(fn ($id) => (int) $id)->all();
    }
}
