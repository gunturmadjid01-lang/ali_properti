<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
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

            if (! Schema::hasTable($table)) {
                return;
            }

            if (! $model->exists && Schema::hasColumn($table, 'created_by') && empty($model->created_by)) {
                $model->created_by = $userId;
            }

            if (Schema::hasColumn($table, 'updated_by')) {
                $model->updated_by = $userId;
            }
        });
    }
}
