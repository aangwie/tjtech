<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\BillingPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingRekapController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $selectedAdminId = $request->input('admin_id');

        // Filter bulan & tahun (default: bulan & tahun saat ini)
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));

        // =============================================
        // CARD BARIS 1: Data per Periode (Bulan/Tahun)
        // =============================================
        $invoiceQuery = Invoice::with('customer')
            ->whereMonth('due_date', $month)
            ->whereYear('due_date', $year);

        if ($user->role == 'operator') {
            $invoiceQuery->whereHas('customer', function ($q) use ($user) {
                $q->where('operator_id', $user->id);
            });
        } elseif ($user->role == 'superadmin' && $selectedAdminId) {
            $invoiceQuery->whereHas('customer', function ($q) use ($selectedAdminId) {
                $q->where('admin_id', $selectedAdminId);
            });
        }

        $invoices = $invoiceQuery->get();
        $invoiceIdsAll = $invoices->pluck('id')->toArray();

        // Sudah Dibayar = hanya pembayaran manual (bukan dari saldo)
        $periodPaidBill = BillingPayment::whereIn('invoice_id', $invoiceIdsAll)
            ->where('method', 'manual')
            ->sum('amount');

        // Kelebihan → Saldo
        $periodExcessToBalance = BillingPayment::whereIn('invoice_id', $invoiceIdsAll)
            ->sum('excess_to_balance');

        // Dibayar Manual (net) = pembayaran manual - kelebihan yang masuk saldo
        $periodDibayarManual = (float) $periodPaidBill - (float) $periodExcessToBalance;

        // Pendapatan = total pembayaran manual
        $periodRevenue = (float) $periodPaidBill;

        // Kurang Bayar (outstanding dari invoice unpaid periode ini)
        $periodUnderpayment = $invoices->where('status', 'unpaid')->sum(function ($inv) {
            $price = $inv->price > 0 ? $inv->price : ($inv->customer->monthly_price ?? 0);
            return max(0, $price - (float) $inv->amount_paid);
        });

        // =============================================
        // CARD BARIS 2: Data Grand Total (Semua Periode)
        // =============================================
        $customerQuery = Customer::with(['invoices']);

        if ($user->role == 'operator') {
            $customerQuery->where('operator_id', $user->id);
        } elseif ($user->role == 'superadmin' && $selectedAdminId) {
            $customerQuery->where('admin_id', $selectedAdminId);
        }

        $customers = $customerQuery->orderBy('name', 'asc')->get();

        $grandTotalTagihan = 0;
        $grandTotalBayar = 0;
        $grandTotalSaldo = 0;
        $grandTotalKurangBayar = 0;

        foreach ($customers as $customer) {
            $totalTagihan = $customer->invoices->where('status', 'unpaid')->sum(function ($inv) use ($customer) {
                return $inv->price > 0 ? $inv->price : ($customer->monthly_price ?? 0);
            });
            $totalBayar = $customer->invoices->where('status', 'paid')->sum('amount_paid');
            $totalKurangBayar = $customer->invoices->where('status', 'unpaid')->sum(function ($inv) use ($customer) {
                $price = $inv->price > 0 ? $inv->price : ($customer->monthly_price ?? 0);
                return max(0, $price - (float) ($inv->amount_paid ?? 0));
            });

            $grandTotalTagihan += $totalTagihan;
            $grandTotalBayar += $totalBayar;
            $grandTotalSaldo += (float) $customer->balance;
            $grandTotalKurangBayar += $totalKurangBayar;
        }

        $admins = [];
        if ($user->role == 'superadmin') {
            $admins = User::whereIn('role', ['admin', 'superadmin'])->get(['id', 'name', 'role']);
        }

        return view('billing.rekap', compact(
            'month',
            'year',
            'periodPaidBill',
            'periodExcessToBalance',
            'periodDibayarManual',
            'periodRevenue',
            'periodUnderpayment',
            'grandTotalTagihan',
            'grandTotalBayar',
            'grandTotalSaldo',
            'grandTotalKurangBayar',
            'admins',
            'selectedAdminId'
        ));
    }

    /**
     * AJAX: Top-up customer balance (Admin only)
     */
    public function topUpBalance(Request $request)
    {
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json(['status' => false, 'message' => 'Hanya Admin yang dapat melakukan top-up saldo.'], 403);
        }

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:1',
        ]);

        $customer = Customer::findOrFail($request->customer_id);

        // Permission check
        if ($user->role == 'admin' && $customer->admin_id != $user->id) {
            return response()->json(['status' => false, 'message' => 'Anda tidak berhak mengubah saldo pelanggan ini.'], 403);
        }

        $balanceBefore = (float) $customer->balance;
        $customer->balance += $request->amount;
        $customer->save();

        // Record top-up history
        \App\Models\BalanceTopup::create([
            'customer_id' => $customer->id,
            'admin_id' => $user->id,
            'amount' => $request->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => (float) $customer->balance,
            'notes' => $request->input('notes', null),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Saldo berhasil ditambahkan. Saldo baru: Rp ' . number_format($customer->balance, 0, ',', '.'),
            'new_balance' => (float) $customer->balance,
            'new_balance_formatted' => 'Rp ' . number_format($customer->balance, 0, ',', '.'),
        ]);
    }

    /**
     * AJAX: Update or Reset customer balance (Admin only)
     */
    public function updateBalance(Request $request)
    {
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json(['status' => false, 'message' => 'Hanya Admin yang dapat mengubah saldo.'], 403);
        }

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'balance' => 'required|numeric|min:0',
        ]);

        $customer = Customer::findOrFail($request->customer_id);

        if ($user->role == 'admin' && $customer->admin_id != $user->id) {
            return response()->json(['status' => false, 'message' => 'Anda tidak berhak mengubah saldo pelanggan ini.'], 403);
        }

        $customer->balance = $request->balance;
        $customer->save();

        return response()->json([
            'status' => true,
            'message' => 'Saldo berhasil diubah menjadi: Rp ' . number_format($customer->balance, 0, ',', '.'),
        ]);
    }
}
