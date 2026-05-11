<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingRekapController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $selectedAdminId = $request->input('admin_id');

        // Build customer query
        $customerQuery = Customer::with(['invoices']);

        if ($user->role == 'operator') {
            $customerQuery->where('operator_id', $user->id);
        } elseif ($user->role == 'superadmin' && $selectedAdminId) {
            $customerQuery->where('admin_id', $selectedAdminId);
        }

        $customers = $customerQuery->orderBy('name', 'asc')->get();

        // Build rekap data
        $rekap = [];
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
                return $price - ($inv->amount_paid ?? 0) - ($inv->carried_underpayment ?? 0);
            });
            $unpaidCount = $customer->invoices->where('status', 'unpaid')->count();
            $paidCount = $customer->invoices->where('status', 'paid')->count();
            $lastInvoice = $customer->invoices->sortByDesc('due_date')->first();

            $rekap[] = [
                'customer' => $customer,
                'total_tagihan' => $totalTagihan,
                'total_bayar' => $totalBayar,
                'saldo' => (float) $customer->balance,
                'kurang_bayar' => $totalKurangBayar,
                'unpaid_count' => $unpaidCount,
                'paid_count' => $paidCount,
                'total_invoices' => $customer->invoices->count(),
                'last_invoice_date' => $lastInvoice ? $lastInvoice->due_date : null,
                'last_invoice_status' => $lastInvoice ? $lastInvoice->status : null,
            ];

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
            'rekap',
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

    /**
     * AJAX: Get customer invoices for detail modal
     */
    public function getCustomerInvoices($id)
    {
        $customer = Customer::with('invoices')->findOrFail($id);
        
        $invoices = $customer->invoices->map(function($inv) use ($customer) {
            return [
                'id' => $inv->id,
                'invoice_number' => '#INV-' . str_pad($inv->id, 5, '0', STR_PAD_LEFT),
                'due_date' => \Carbon\Carbon::parse($inv->due_date)->isoFormat('DD MMM YYYY'),
                'price' => $inv->price > 0 ? $inv->price : ($customer->monthly_price ?? 0),
                'price_formatted' => 'Rp ' . number_format($inv->price > 0 ? $inv->price : ($customer->monthly_price ?? 0), 0, ',', '.'),
                'status' => $inv->status,
                'underpayment' => $inv->underpayment,
                'underpayment_formatted' => 'Rp ' . number_format($inv->underpayment, 0, ',', '.'),
            ];
        })->sortByDesc('due_date')->values();

        return response()->json([
            'customer_name' => $customer->name,
            'invoices' => $invoices
        ]);
    }
}
