<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Perumahan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PropertyWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('marketing-lead.view') || $request->user()?->hasRole('super_admin'), 403);
        $projects = Perumahan::query()->finalized()->withCount([
            'detailRumah as total_unit',
            'detailRumah as unit_tersedia' => fn ($q) => $q->whereIn('status_penjualan', ['tersedia', 'available']),
            'detailRumah as unit_terjual' => fn ($q) => $q->whereIn('status_penjualan', ['terjual', 'sold']),
        ])->when($request->filled('search'), fn ($q) => $q->where('nama_perusahaan', 'like', '%'.$request->string('search').'%'))
            ->orderBy('nama_perusahaan')->paginate(20)->withQueryString();
        return Inertia::render('Admin/Marketing/PropertyWorkspace/Index', ['projects' => $projects, 'filters' => ['search' => $request->string('search')->toString()]]);
    }
}
