<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\BillingPayment;
use App\Models\Expense;
use App\Models\Company;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AccountingController extends Controller
{
    public function index(Request $request)
    {
        // Filter Bulan & Tahun (Default: Bulan Ini)
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        // 1. HITUNG PEMASUKAN (PENDAPATAN)
        // Ambil invoice pada bulan/tahun yang dipilih
        $invoices = Invoice::with('customer')
            ->whereMonth('due_date', $month)
            ->whereYear('due_date', $year)
            ->get();

        $invoiceIds = $invoices->pluck('id')->toArray();

        // Pendapatan = total pembayaran manual (sama dengan card Pendapatan di /billing)
        $paidBill = BillingPayment::whereIn('invoice_id', $invoiceIds)
            ->where('method', 'manual')
            ->sum('amount');
        $totalRevenue = (float) $paidBill;

        // 2. HITUNG PENGELUARAN
        $expenses = Expense::whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->orderBy('transaction_date', 'desc')
            ->get();

        $totalExpense = $expenses->sum('amount');

        // 3. HITUNG LABA BERSIH
        $netProfit = $totalRevenue - $totalExpense;

        return view('accounting.index', compact(
            'month',
            'year',
            'totalRevenue',
            'totalExpense',
            'netProfit',
            'expenses'
        ));
    }

    // SIMPAN PENGELUARAN BARU
    public function storeExpense(Request $request)
    {
        $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'transaction_date' => 'required|date',
        ]);

        Expense::create($request->all());

        return back()->with('success', 'Pengeluaran berhasil dicatat.');
    }

    // HAPUS PENGELUARAN
    public function destroyExpense($id)
    {
        Expense::destroy($id);
        return back()->with('success', 'Data pengeluaran dihapus.');
    }

    // CETAK LAPORAN LABA RUGI
    // CETAK LAPORAN LABA RUGI
    public function print(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        // Ambil Pilihan Tipe Laporan (Default 1 = Rincian Semua)
        $reportType = $request->input('report_type', 1);

        // Ambil Data Pemasukan — sama dengan rumus Pendapatan di /billing
        $invoices = Invoice::with('customer')
            ->whereMonth('due_date', $month)
            ->whereYear('due_date', $year)
            ->get();

        $invoiceIds = $invoices->pluck('id')->toArray();

        $paidBill = BillingPayment::whereIn('invoice_id', $invoiceIds)
            ->where('method', 'manual')
            ->sum('amount');
        $excessToBalance = BillingPayment::whereIn('invoice_id', $invoiceIds)
            ->sum('excess_to_balance');
        $totalRevenue = (float) $paidBill + (float) $excessToBalance;

        // Ambil Data Pengeluaran Detil
        $expenses = Expense::whereMonth('transaction_date', $month)
            ->whereYear('transaction_date', $year)
            ->orderBy('transaction_date', 'asc')
            ->get();

        $totalExpense = $expenses->sum('amount');
        $netProfit = $totalRevenue - $totalExpense;

        $company = Company::first();

        return view('accounting.print', compact(
            'invoices',
            'expenses',
            'company',
            'totalRevenue',
            'totalExpense',
            'netProfit',
            'month',
            'year',
            'reportType' // Tambahkan reportType disini
        ));
    }
}
