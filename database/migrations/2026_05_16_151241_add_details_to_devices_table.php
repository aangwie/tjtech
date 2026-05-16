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
        Schema::table('devices', function (Blueprint $table) {
            $table->string('rasio')->nullable()->after('keterangan');
            $table->string('redaman')->nullable()->after('rasio');
            $table->string('latitude')->nullable()->after('redaman');
            $table->string('longitude')->nullable()->after('latitude');
            $table->string('foto')->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['rasio', 'redaman', 'latitude', 'longitude', 'foto']);
        });
    }
};
