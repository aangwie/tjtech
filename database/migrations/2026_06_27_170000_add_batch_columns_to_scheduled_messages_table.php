<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table) {
            $table->integer('batch_number')->nullable()->after('total_count');
            $table->integer('total_batches')->nullable()->after('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table) {
            $table->dropColumn(['batch_number', 'total_batches']);
        });
    }
};