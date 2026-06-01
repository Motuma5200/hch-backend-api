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
            // Drops the redundant flat schema columns safely
            $table->dropColumn(['specialization', 'bio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Re-creates the columns as fallback points if you rollback
            $table->string('specialization')->nullable();
            $table->text('bio')->nullable();
        });
    }
};