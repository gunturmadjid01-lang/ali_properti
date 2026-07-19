<?php

namespace App\Providers;

use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\User;
use App\Observers\DetailRumahObserver;
use App\Observers\PerumahanObserver;
use App\Observers\UserObserver;
use App\Support\SchemaMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Builder::macro('finalized', function (): Builder {
            /** @var Builder $this */
            $model = $this->getModel();
            $table = $model->getTable();
            if (SchemaMetadata::hasColumn($table, 'record_status')) {
                return $this->where($model->qualifyColumn('record_status'), 'locked');
            }
            if (SchemaMetadata::hasColumn($table, 'status')) {
                return $this->where($model->qualifyColumn('status'), '!=', 'draft');
            }

            return $this;
        });
        Perumahan::observe(PerumahanObserver::class);
        DetailRumah::observe(DetailRumahObserver::class);
        User::observe(UserObserver::class);

        Event::listen('eloquent.saving: *', function (string $eventName, array $data): void {
            $model = $data[0] ?? null;

            if (! $model instanceof Model) {
                return;
            }

            $userId = auth()->id();

            if (! $userId) {
                return;
            }

            $table = $model->getTable();

            if (! SchemaMetadata::hasTable($table)) {
                return;
            }

            if (! $model->exists && SchemaMetadata::hasColumn($table, 'created_by') && empty($model->created_by)) {
                $model->created_by = $userId;
            }

            if (SchemaMetadata::hasColumn($table, 'updated_by')) {
                $model->updated_by = $userId;
            }
        });
    }
}
