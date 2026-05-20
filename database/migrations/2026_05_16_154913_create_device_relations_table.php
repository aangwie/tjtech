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
        Schema::create('device_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->foreign('source_id')->references('id')->on('devices')->onDelete('cascade');
            $table->foreign('target_id')->references('id')->on('devices')->onDelete('cascade');
            
            // Prevent duplicate relations
            $table->unique(['source_id', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_relations');
    }
};
