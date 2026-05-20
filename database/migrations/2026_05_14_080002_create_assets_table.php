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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('nama_barang');
            $table->string('merk');
            $table->boolean('has_identifier')->default(false);
            $table->string('identifier')->nullable();
            $table->integer('tahun_perolehan');
            $table->enum('kondisi_perolehan', ['Baru', 'Bekas']);
            $table->bigInteger('harga_perolehan');
            $table->boolean('has_penyusutan')->default(false);
            $table->bigInteger('nilai_penyusutan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
