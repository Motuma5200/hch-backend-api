<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('health_metrics', function (Blueprint $table) {
            if (!Schema::hasColumn('health_metrics', 'user_id')) {
                $table->foreignId('user_id')->constrained()->onDelete('cascade')->after('id');
            }
            if (!Schema::hasColumn('health_metrics', 'metric_type')) {
                $table->string('metric_type')->index()->after('user_id');
            }
            if (!Schema::hasColumn('health_metrics', 'value')) {
                $table->decimal('value', 8, 2)->after('metric_type');
            }
            if (!Schema::hasColumn('health_metrics', 'unit')) {
                $table->string('unit')->nullable()->after('value');
            }
            if (!Schema::hasColumn('health_metrics', 'additional_data')) {
                $table->json('additional_data')->nullable()->after('unit');
            }
            if (!Schema::hasColumn('health_metrics', 'recorded_at')) {
                $table->timestamp('recorded_at')->nullable()->after('additional_data');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('health_metrics', function (Blueprint $table) {
            if (Schema::hasColumn('health_metrics', 'user_id')) {
                $table->dropForeign(['user_id']);
            }
            $table->dropColumn(array_filter([
                Schema::hasColumn('health_metrics', 'user_id') ? 'user_id' : null,
                Schema::hasColumn('health_metrics', 'metric_type') ? 'metric_type' : null,
                Schema::hasColumn('health_metrics', 'value') ? 'value' : null,
                Schema::hasColumn('health_metrics', 'unit') ? 'unit' : null,
                Schema::hasColumn('health_metrics', 'additional_data') ? 'additional_data' : null,
                Schema::hasColumn('health_metrics', 'recorded_at') ? 'recorded_at' : null,
            ]));
        });
    }
};
