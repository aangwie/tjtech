<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add balance to customers
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->after('monthly_price');
        });

        // Add payment detail columns to invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('amount_paid', 15, 2)->default(0)->after('price');
            $table->decimal('underpayment', 15, 2)->default(0)->after('amount_paid');
            $table->decimal('carried_underpayment', 15, 2)->default(0)->after('underpayment');
            $table->string('payment_method')->nullable()->after('status');
            $table->timestamp('paid_at')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('balance');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'underpayment', 'carried_underpayment', 'payment_method', 'paid_at']);
        });
    }
};
