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
        Schema::table('hospitals', function (Blueprint $table) {
            if (!Schema::hasColumn('hospitals', 'phone')) {
                $table->string('phone')->nullable()->after('address');
            }
            if (!Schema::hasColumn('hospitals', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('hospitals', 'city')) {
                $table->string('city')->nullable()->after('email');
            }
            if (!Schema::hasColumn('hospitals', 'state')) {
                $table->string('state')->nullable()->after('city');
            }
            if (!Schema::hasColumn('hospitals', 'country')) {
                $table->string('country')->nullable()->after('state');
            }
            if (!Schema::hasColumn('hospitals', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('country');
            }
            if (!Schema::hasColumn('hospitals', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('hospitals', 'specialties')) {
                $table->json('specialties')->nullable()->after('longitude');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hospitals', function (Blueprint $table) {
            foreach (['specialties','longitude','latitude','country','state','city','email','phone'] as $col) {
                if (Schema::hasColumn('hospitals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
