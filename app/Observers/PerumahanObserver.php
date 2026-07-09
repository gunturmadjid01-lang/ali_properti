<?php

namespace App\Observers;

use App\Models\Perumahan;
use App\Services\HppTemplateService;

class PerumahanObserver
{
    public function __construct(private readonly HppTemplateService $hppTemplates)
    {
    }

    public function created(Perumahan $perumahan): void
    {
        $this->hppTemplates->initializePerumahan($perumahan);
    }
}
