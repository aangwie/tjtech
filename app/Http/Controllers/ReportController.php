<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\BillingPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // 1. Ambil User yang Login
        $user = Auth::user();

        // 2. Filter Bulan & Tahun
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        // 3. Mulai Query
        $query = Invoice::with('customer')
            ->whereMonth('due_date', $month)
            ->whereYear('due_date', $year);

        // --- LOGIKA PEMBATASAN DATA (Operator) ---
        if ($user->role == 'operator') {
            // Filter: Hanya invoice yang customer-nya dipegang operator ini
            $query->whereHas('customer', function($q) use ($user) {
                $q->where('operator_id', $user->id);
            });
        }
        // -----------------------------------------

        $invoices = $query->get();
        $invoiceIds = $invoices->pluck('id')->toArray();

        // 4. Hitung Rekapitulasi (Dari data yang sudah difilter di atas)
        $totalTagihan = $invoices->sum(fn($inv) => $inv->price > 0 ? $inv->price : ($inv->customer->monthly_price ?? 0));

        // Uang Masuk (Pendapatan) = total pembayaran manual (sama dengan card Pendapatan di /billing)
        $paidBill = BillingPayment::whereIn('invoice_id', $invoiceIds)
            ->where('method', 'manual')
            ->sum('amount');
        $totalPendapatan = (float) $paidBill;

        // Saldo = kelebihan pembayaran yang masuk ke saldo pelanggan pada periode ini
        $totalSaldo = BillingPayment::whereIn('invoice_id', $invoiceIds)
            ->sum('excess_to_balance');

        // Kurang Bayar = total outstanding dari invoice unpaid (price - amount_paid)
        $totalKurangBayar = $invoices->where('status', 'unpaid')->sum(function ($inv) {
            $price = $inv->price > 0 ? $inv->price : ($inv->customer->monthly_price ?? 0);
            return max(0, $price - (float) $inv->amount_paid);
        });

        $jumlahLunas = $invoices->where('status', 'paid')->count();
        $jumlahBelumLunas = $invoices->where('status', 'unpaid')->count();

        return view('report.index', compact(
            'invoices', 
            'month', 
            'year', 
            'totalTagihan', 
            'totalPendapatan', 
            'totalKurangBayar',
            'totalSaldo',
            'jumlahLunas',
            'jumlahBelumLunas'
        ));
    }
}