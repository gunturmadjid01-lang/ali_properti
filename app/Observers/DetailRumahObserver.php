<?php

namespace App\Observers;

use App\Models\DetailRumah;
use App\Services\HppTemplateService;

class DetailRumahObserver
{
    public function __construct(private readonly HppTemplateService $hppTemplates)
    {
    }

    public function created(DetailRumah $rumah): void
    {
        $this->hppTemplates->initializeUnit($rumah);
    }

    public function updated(DetailRumah $rumah): void
    {
        if ($rumah->wasChanged(['tipe_rumah', 'perumahan_id'])) {
            $this->hppTemplates->syncBuildingTypeSummary((int) $rumah->perumahan_id);

            if ($rumah->wasChanged('perumahan_id')) {
                $this->hppTemplates->syncBuildingTypeSummary((int) $rumah->getOriginal('perumahan_id'));
            }
        }
    }

    public function deleted(DetailRumah $rumah): void
    {
        $this->hppTemplates->syncBuildingTypeSummary((int) $rumah->perumahan_id);
    }

    public function restored(DetailRumah $rumah): void
    {
        $this->hppTemplates->initializeUnit($rumah);
    }
}
