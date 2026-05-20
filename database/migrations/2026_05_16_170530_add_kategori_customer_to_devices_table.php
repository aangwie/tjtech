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
            $table->string('kategori')->default('ODP')->after('nama');
            $table->unsignedBigInteger('customer_id')->nullable()->after('kategori');
            $table->string('ip_address')->nullable()->after('customer_id');

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['kategori', 'customer_id', 'ip_address']);
        });
    }
};
