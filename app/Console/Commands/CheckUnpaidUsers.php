<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Services\MikrotikService;
use Carbon\Carbon;

class CheckUnpaidUsers extends Command
{
    // Nama perintah yang nanti diketik di terminal
    protected $signature = 'billing:check-unpaid';
    protected $description = 'Cek user telat bayar (hanya logging, tidak mematikan koneksi)';

    public function handle(MikrotikService $mikrotik)
    {
        $today = Carbon::now()->toDateString();

        $this->info("Memulai pengecekan tagihan jatuh tempo per: $today");

        // 1. Cari tagihan yang STATUS 'unpaid' DAN DUE_DATE < Hari Ini
        $overdueInvoices = Invoice::where('status', 'unpaid')
            ->where('due_date', '<', Carbon::now()->toDateString())
            ->with('customer')
            ->get();

        if ($overdueInvoices->count() == 0) {
            $this->info("Tidak ada user yang menunggak.");
            return;
        }

        // 2. Loop user yang nunggak (hanya logging, TIDAK mematikan secret)
        foreach ($overdueInvoices as $invoice) {
            $user = $invoice->customer->pppoe_username ?? 'unknown';
            $dueDate = $invoice->due_date;
            $nominal = number_format($invoice->total ?? $invoice->amount ?? 0, 0, ',', '.');

            $this->warn("⚠️  User TELAT: $user | Jatuh tempo: $dueDate | Tagihan: Rp $nominal");

            // Update status customer di database lokal (untuk keperluan informasi)
            $invoice->customer->update(['is_active' => false]);
        }

        $this->info("Selesai. Total {$overdueInvoices->count()} user menunggak (tidak ada yang diputus koneksinya).");
    }
}
