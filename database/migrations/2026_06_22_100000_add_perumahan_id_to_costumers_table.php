<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costumers', function (Blueprint $table): void {
            if (! Schema::hasColumn('costumers', 'perumahan_id')) {
                $table->foreignId('perumahan_id')->nullable()->after('updated_by')->constrained('perumahans')->nullOnDelete();
            }
        });

        if (Schema::hasTable('sprs') && Schema::hasTable('detail_rumahs')) {
            DB::statement("
                update costumers
                set perumahan_id = (
                    select detail_rumahs.perumahan_id
                    from sprs
                    join detail_rumahs on detail_rumahs.id = sprs.detail_rumah_id
                    where sprs.costumer_id = costumers.id
                    order by sprs.id desc
                    limit 1
                )
                where perumahan_id is null
            ");
        }

        if (Schema::hasTable('marketing_survey_schedules')) {
            DB::statement("
                update costumers
                set perumahan_id = (
                    select marketing_survey_schedules.perumahan_id
                    from marketing_survey_schedules
                    where marketing_survey_schedules.costumer_id = costumers.id
                        and marketing_survey_schedules.perumahan_id is not null
                    order by marketing_survey_schedules.id desc
                    limit 1
                )
                where perumahan_id is null
            ");
        }
    }

    public function down(): void
    {
        Schema::table('costumers', function (Blueprint $table): void {
            if (Schema::hasColumn('costumers', 'perumahan_id')) {
                $table->dropConstrainedForeignId('perumahan_id');
            }
        });
    }
};
