<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoleDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, RoleDashboardService $dashboard): Response
    {
        return Inertia::render('Admin/Dashboard', $dashboard->build(
            $request->user(),
            (int) $request->session()->get('active_perumahan_id') ?: null,
            in_array($request->query('period'), ['day','month','year'], true) ? $request->query('period') : 'month',
            $request->query('value'),
        ));
    }
}
