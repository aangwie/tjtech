<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\BillingPayment;
use App\Models\BalanceTopup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingRekapController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $selectedAdminId = $request->input('admin_id');
        $selectedOperatorId = $request->input('operator_id');

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

        // Filter by operator_id (for admin & superadmin)
        if ($selectedOperatorId && in_array($user->role, ['admin', 'superadmin'])) {
            $invoiceQuery->whereHas('customer', function ($q) use ($selectedOperatorId) {
                $q->where('operator_id', $selectedOperatorId);
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

        // Total Tagihan = jumlah seluruh tagihan yang belum terbayar lunas (unpaid)
        // termasuk sisa kekurangannya (harga - amount_paid)
        $allUnpaidQuery = Invoice::with('customer')->where('status', 'unpaid');
        if ($user->role == 'operator') {
            $allUnpaidQuery->whereHas('customer', function ($q) use ($user) {
                $q->where('operator_id', $user->id);
            });
        } elseif ($user->role == 'superadmin' && $selectedAdminId) {
            $allUnpaidQuery->whereHas('customer', function ($q) use ($selectedAdminId) {
                $q->where('admin_id', $selectedAdminId);
            });
        }

        // Filter by operator_id for grand total (for admin & superadmin)
        if ($selectedOperatorId && in_array($user->role, ['admin', 'superadmin'])) {
            $allUnpaidQuery->whereHas('customer', function ($q) use ($selectedOperatorId) {
                $q->where('operator_id', $selectedOperatorId);
            });
        }
        $allUnpaidInvoices = $allUnpaidQuery->get();
        $grandTotalTagihan = $allUnpaidInvoices->sum(function ($inv) {
            $price = $inv->price > 0 ? $inv->price : ($inv->customer->monthly_price ?? 0);
            return max(0, $price - (float) $inv->amount_paid);
        });

        // Total Sudah Bayar = seluruh pembayaran manual di semua periode
        $manualPaymentQuery = BillingPayment::where('method', 'manual');
        if ($user->role == 'operator') {
            $manualPaymentQuery->whereHas('invoice.customer', function ($q) use ($user) {
                $q->where('operator_id', $user->id);
            });
        } elseif ($user->role == 'superadmin' && $selectedAdminId) {
            $manualPaymentQuery->whereHas('invoice.customer', function ($q) use ($selectedAdminId) {
                $q->where('admin_id', $selectedAdminId);
            });
        }
        // Filter by operator_id for grand total (for admin & superadmin)
        if ($selectedOperatorId && in_array($user->role, ['admin', 'superadmin'])) {
            $manualPaymentQuery->whereHas('invoice.customer', function ($q) use ($selectedOperatorId) {
                $q->where('operator_id', $selectedOperatorId);
            });
        }
        $grandTotalBayar = (float) $manualPaymentQuery->sum('amount');

        // Total Dibayar Pakai Saldo = seluruh pembayaran yang menggunakan saldo
        // (method = 'balance' atau 'auto_balance', atau balance_used > 0)
        $balancePaymentQuery = BillingPayment::where(function ($q) {
            $q->whereIn('method', ['balance', 'auto_balance'])
              ->orWhere('balance_used', '>', 0);
        });
        if ($user->role == 'operator') {
            $balancePaymentQuery->whereHas('invoice.customer', function ($q) use ($user) {
                $q->where('operator_id', $user->id);
            });
        } elseif ($user->role == 'superadmin' && $selectedAdminId) {
            $balancePaymentQuery->whereHas('invoice.customer', function ($q) use ($selectedAdminId) {
                $q->where('admin_id', $selectedAdminId);
            });
        }
        // Filter by operator_id for grand total (for admin & superadmin)
        if ($selectedOperatorId && in_array($user->role, ['admin', 'superadmin'])) {
            $balancePaymentQuery->whereHas('invoice.customer', function ($q) use ($selectedOperatorId) {
                $q->where('operator_id', $selectedOperatorId);
            });
        }
        $grandTotalDibayarSaldo = (float) $balancePaymentQuery->sum('balance_used')
            + (float) BillingPayment::whereIn('method', ['balance', 'auto_balance'])
                ->when($user->role == 'operator', function ($q) use ($user) {
                    $q->whereHas('invoice.customer', fn($q2) => $q2->where('operator_id', $user->id));
                })
                ->when($user->role == 'superadmin' && $selectedAdminId, function ($q) use ($selectedAdminId) {
                    $q->whereHas('invoice.customer', fn($q2) => $q2->where('admin_id', $selectedAdminId));
                })
                ->when($selectedOperatorId && in_array($user->role, ['admin', 'superadmin']), function ($q) use ($selectedOperatorId) {
                    $q->whereHas('invoice.customer', fn($q2) => $q2->where('operator_id', $selectedOperatorId));
                })
                ->where('balance_used', 0)
                ->sum('amount');

        $admins = [];
        if ($user->role == 'superadmin') {
            $admins = User::whereIn('role', ['admin', 'superadmin'])->get(['id', 'name', 'role']);
        }

        // Build operators list for filter
        $operators = collect();
        if ($user->role == 'admin') {
            // Admin sees their own operators
            $operators = User::where('role', 'operator')->where('parent_id', $user->id)->get(['id', 'name', 'role']);
        } elseif ($user->role == 'superadmin') {
            // Superadmin sees all admins and operators
            $operatorQuery = User::whereIn('role', ['admin', 'operator']);
            if ($selectedAdminId) {
                $operatorQuery->where(function ($q) use ($selectedAdminId) {
                    $q->where('id', $selectedAdminId)
                        ->orWhere('parent_id', $selectedAdminId);
                });
            }
            $operators = $operatorQuery->orderBy('role')->orderBy('name')->get(['id', 'name', 'role', 'parent_id']);
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
            'grandTotalDibayarSaldo',
            'admins',
            'selectedAdminId',
            'selectedOperatorId',
            'operators'
        ));
    }

    /**
     * AJAX: Top-up customer balance (Admin only)
     */
    public function topUpBalance(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'superadmin'])) {
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
        if (!in_array($user->role, ['admin', 'superadmin'])) {
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
     * Rekap Operator - Tampilkan tagihan per operator untuk admin yang login
     */
    public function rekapOperator(Request $request)
    {
        $user = Auth::user();

        // Filter bulan & tahun (default: bulan & tahun saat ini)
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));

        // Get operators yang berada di bawah admin ini
        $operators = User::where('role', 'operator')
            ->where('parent_id', $user->id)
            ->get(['id', 'name']);

        $operatorData = [];

        // Ambil komisi yang sudah tersimpan untuk periode ini
        $komisiMap = \App\Models\TabelKomisi::whereIn('operator_id', $operators->pluck('id'))
            ->where('month', $month)
            ->where('year', $year)
            ->get()
            ->keyBy('operator_id');

        foreach ($operators as $operator) {
            // Get invoices untuk periode ini per operator
            $invoices = Invoice::with('customer')
                ->whereMonth('due_date', $month)
                ->whereYear('due_date', $year)
                ->whereHas('customer', function ($q) use ($operator) {
                    $q->where('operator_id', $operator->id);
                })
                ->get();


            $invoiceIds = $invoices->pluck('id')->toArray();

            // Hitung total tagihan
            $totalTagihan = 0;
            foreach ($invoices as $inv) {
                $price = $inv->price > 0 ? $inv->price : ($inv->customer->monthly_price ?? 0);
                $totalTagihan += $price;
            }

            // Hitung tagihan yang sudah dibayar (manual saja)
            $tagihanLunas = BillingPayment::whereIn('invoice_id', $invoiceIds)
                ->where('method', 'manual')
                ->sum('amount');

            // Hitung tagihan yang belum dibayar
            $sisaTagihan = 0;
            foreach ($invoices as $inv) {
                $price = $inv->price > 0 ? $inv->price : ($inv->customer->monthly_price ?? 0);
                $outstanding = max(0, $price - (float) $inv->amount_paid);
                $sisaTagihan += $outstanding;
            }

            $komisiRow = $komisiMap->get($operator->id);
            $komisiPercent = $komisiRow ? (float) $komisiRow->komisi_percent : 0;
            $komisiValue = $komisiRow ? (float) $komisiRow->komisi_value : round(($komisiPercent / 100) * (float) $tagihanLunas);

            $operatorData[] = [
                'id' => $operator->id,
                'name' => $operator->name,
                'total_tagihan' => $totalTagihan,
                'tagihan_lunas' => $tagihanLunas,
                'sisa_tagihan' => $sisaTagihan,
                'komisi_percent' => $komisiPercent,
                'komisi_value' => $komisiValue,
            ];
        }


        return view('billing.rekap-operator', compact(
            'month',
            'year',
            'operators',
            'operatorData'
        ));
    }
}
