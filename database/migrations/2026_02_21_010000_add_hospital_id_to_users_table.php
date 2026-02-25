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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'hospital_id')) {
                $table->unsignedBigInteger('hospital_id')->nullable()->after('organisation');
                $table->foreign('hospital_id')->references('id')->on('hospitals')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'hospital_id')) {
                $table->dropForeign(["hospital_id"]);
                $table->dropColumn('hospital_id');
            }
        });
    }
};
