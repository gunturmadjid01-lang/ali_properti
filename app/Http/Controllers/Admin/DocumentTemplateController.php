<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spr;
use App\Services\FixedSalesDocumentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTemplateController extends Controller
{
    public function index(Request $request, FixedSalesDocumentService $service): Response
    {
        $this->authorizeView($request);

        return Inertia::render('Admin/DocumentTemplates/Index', ['title' => 'Dokumen Baku Penjualan', 'templates' => $service->catalog()]);
    }

    public function preview(Request $request, string $type, FixedSalesDocumentService $service)
    {
        $this->authorizeView($request);

        return $service->original($type);
    }

    public function printSpr(Request $request, Spr $spr, string $type, FixedSalesDocumentService $service)
    {
        abort_unless($request->user()?->can('booking.view'), 403);

        return $service->forSpr($spr, $type);
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->can('document-template.view'), 403);
    }
}
