<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tabel_komisi', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operator_id');
            $table->unsignedInteger('month'); // 1..12
            $table->unsignedInteger('year');  // YYYY
            $table->unsignedInteger('komisi_percent'); // 0..100
            $table->decimal('komisi_value', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['operator_id', 'month', 'year']);

            // FK (best-effort: users table exists)
            $table->foreign('operator_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabel_komisi');
    }
};

