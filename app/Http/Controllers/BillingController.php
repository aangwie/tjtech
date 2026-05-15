<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Company;
use App\Models\User;
use App\Models\BillingPayment;
use App\Models\BalanceTopup;
use App\Services\MikrotikService;
use App\Services\WhatsappService; // 1. Import Service WA
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BillingController extends Controller
{
    protected $mikrotik;
    protected $wa; // 2. Property baru untuk WA

    // 3. Inject WhatsappService di Constructor
    public function __construct(MikrotikService $mikrotikService, WhatsappService $whatsappService)
    {
        $this->mikrotik = $mikrotikService;
        $this->wa = $whatsappService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // 1. Ambil Filter Bulan & Tahun (Default: Bulan Ini)
        $month = $request->input('month', date('n'));
        $year = $request->input('year', date('Y'));
        $selectedAdminId = $request->input('admin_id');
        $selectedOperatorId = $request->input('operator_id');

        // Query Tagihan dengan Filter
        $invoiceQuery = Invoice::with(['customer', 'payments.admin'])
            ->whereMonth('due_date', $month)
            ->whereYear('due_date', $year)
            ->orderByRaw("FIELD(status, 'unpaid', 'paid')")
            ->orderBy('due_date', 'asc');

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

        // 2. Hitung Totals dari Data Terfilter
        $total_bill = 0;
        $unpaid_bill = 0;

        foreach ($invoices as $inv) {
            $price = $inv->price > 0 ? $inv->price : ($inv->customer->monthly_price ?? 0);

            $total_bill += $price;
            if ($inv->status != 'paid') {
                $unpaid_bill += $price;
            }
        }

        // Sudah Dibayar = hanya pembayaran manual (bukan dari saldo)
        $invoiceIdsAll = $invoices->pluck('id')->toArray();
        $paid_bill = BillingPayment::whereIn('invoice_id', $invoiceIdsAll)
            ->where('method', 'manual')
            ->sum('amount');

        $customerQuery = Customer::orderBy('name', 'asc');
        if ($user->role == 'operator') {
            $customerQuery->where('operator_id', $user->id);
        }
        $customers = $customerQuery->get();

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

        // Build a map of customer balances for the view
        $customerBalances = [];
        foreach ($invoices as $inv) {
            if ($inv->customer && !isset($customerBalances[$inv->customer_id])) {
                $customerBalances[$inv->customer_id] = (float) $inv->customer->balance;
            }
        }

        // Build all unpaid invoices map for the manual invoice modal (to avoid N+1 in view)
        $allUnpaidInvoices = Invoice::whereIn('customer_id', $customers->pluck('id'))
            ->where('status', 'unpaid')
            ->orderBy('due_date', 'asc')
            ->get();
            
        $customerPriceMap = $customers->pluck('monthly_price', 'id')->toArray();
        $allArrearsMap = [];
        foreach ($allUnpaidInvoices as $arr) {
            $monthlyPrice = $customerPriceMap[$arr->customer_id] ?? 0;
            $arrPrice = $arr->price > 0 ? $arr->price : $monthlyPrice;
            $arrOutstanding = $arrPrice - (float) $arr->amount_paid;
            if ($arrOutstanding > 0) {
                $allArrearsMap[$arr->customer_id][] = [
                    'period' => Carbon::parse($arr->due_date)->isoFormat('MMM Y'),
                    'amount' => $arrOutstanding,
                ];
            }
        }

        // Build arrears map: ALL unpaid invoices per customer (from any month, excluding current period)
        $customerIds = $invoices->pluck('customer_id')->unique()->toArray();
        $arrearsQuery = Invoice::with('customer')
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'unpaid')
            ->where(function ($q) use ($month, $year) {
                // Exclude invoices from the currently viewed period (they are the main rows)
                $q->whereRaw("NOT (MONTH(due_date) = ? AND YEAR(due_date) = ?)", [$month, $year]);
            })
            ->orderBy('due_date', 'asc')
            ->get();

        $arrearsByCustomer = [];
        foreach ($arrearsQuery as $arrear) {
            $arrearPrice = $arrear->price > 0 ? $arrear->price : ($arrear->customer->monthly_price ?? 0);
            $arrear->outstanding = $arrearPrice - (float) $arrear->amount_paid;
            if ($arrear->outstanding > 0) {
                $arrearsByCustomer[$arrear->customer_id][] = $arrear;
            }
        }

        // Calculate period totals from billing_payments
        $total_excess_to_balance = BillingPayment::whereIn('invoice_id', $invoiceIdsAll)
            ->sum('excess_to_balance');
            
        $total_balance_used = BillingPayment::whereIn('invoice_id', $invoiceIdsAll)
            ->sum('balance_used');

        // Total outstanding for unpaid invoices in current period
        $total_underpayment = 0;
        foreach ($invoices->where('status', 'unpaid') as $unpaidInv) {
            $uPrice = $unpaidInv->price > 0 ? $unpaidInv->price : ($unpaidInv->customer->monthly_price ?? 0);
            $total_underpayment += max(0, $uPrice - (float) $unpaidInv->amount_paid);
        }

        // Pendapatan = pembayaran manual - kelebihan yang masuk saldo
        $total_revenue = (float) $paid_bill - (float) $total_excess_to_balance;

        // Get excess balance per invoice to show in the table
        $excessByInvoice = BillingPayment::whereIn('invoice_id', $invoiceIdsAll)
            ->where('excess_to_balance', '>', 0)
            ->groupBy('invoice_id')
            ->selectRaw('invoice_id, sum(excess_to_balance) as total_excess')
            ->pluck('total_excess', 'invoice_id')
            ->toArray();

        // Build map: who processed payment for each invoice (admin name if different from operator)
        $paymentAdminByInvoice = [];
        foreach ($invoices as $inv) {
            if ($inv->status == 'paid' || $inv->amount_paid > 0) {
                $lastPayment = $inv->payments->sortByDesc('created_at')->first();
                if ($lastPayment && $lastPayment->admin) {
                    $payAdmin = $lastPayment->admin;
                    // Show label if payment was made by admin/superadmin (not the operator themselves)
                    if (in_array($payAdmin->role, ['admin', 'superadmin'])) {
                        $paymentAdminByInvoice[$inv->id] = $payAdmin->name;
                    }
                }
            }
        }
        // Determine the latest billing period to disable actions on older periods
        $latestInvoiceQuery = Invoice::query();
        if ($user->role == 'operator') {
            $latestInvoiceQuery->whereHas('customer', fn($q) => $q->where('operator_id', $user->id));
        }
        $latestInvoice = $latestInvoiceQuery->orderBy('due_date', 'desc')->first();
        $isLatestPeriod = true;
        if ($latestInvoice) {
            $latestMonth = (int) Carbon::parse($latestInvoice->due_date)->format('m');
            $latestYear = (int) Carbon::parse($latestInvoice->due_date)->format('Y');
            
            // Allow actions if selected period is >= latest generated period
            if ($year > $latestYear || ((int) $year === $latestYear && (int) $month >= $latestMonth)) {
                $isLatestPeriod = true;
            } else {
                $isLatestPeriod = false;
            }
        }

        return view('billing.index', compact('invoices', 'customers', 'month', 'year', 'total_bill', 'paid_bill', 'unpaid_bill', 'admins', 'selectedAdminId', 'selectedOperatorId', 'operators', 'customerBalances', 'arrearsByCustomer', 'total_excess_to_balance', 'total_underpayment', 'total_revenue', 'excessByInvoice', 'paymentAdminByInvoice', 'isLatestPeriod', 'allArrearsMap', 'total_balance_used'));
    }

    public function generate(Request $request)
    {
        $request->validate([
            'month' => 'required|numeric',
            'year' => 'required|numeric',
            'due_date' => 'required|date',
        ]);

        $user = Auth::user();

        // Use global scope but add explicit filters for redundancy and clarity
        $query = Customer::where('is_active', true);

        if ($user->role == 'operator') {
            $query->where('operator_id', $user->id);
        } elseif ($user->role == 'admin') {
            $query->where('admin_id', $user->id);
        }

        $activeCustomers = $query->get();

        if ($activeCustomers->isEmpty()) {
            return back()->with('error', 'Tidak ada pelanggan aktif yang ditemukan untuk akun Anda.');
        }

        $activeCustomerIds = $activeCustomers->pluck('id')->toArray();

        // 1. Get existing invoices for the selected month to check existence efficiently
        $existingInvoiceCustomerIds = Invoice::whereIn('customer_id', $activeCustomerIds)
            ->whereMonth('due_date', $request->month)
            ->whereYear('due_date', $request->year)
            ->pluck('customer_id')
            ->toArray();

        // 2. Get all previous unpaid invoices
        $allPreviousUnpaid = Invoice::whereIn('customer_id', $activeCustomerIds)
            ->where('status', 'unpaid')
            ->get();
            
        $unpaidMap = [];
        foreach ($allPreviousUnpaid as $inv) {
            $unpaidMap[$inv->customer_id][] = $inv;
        }

        $count = 0;
        foreach ($activeCustomers as $customer) {
            $exists = in_array($customer->id, $existingInvoiceCustomerIds);

            if (!$exists) {
                // Calculate previous arrears
                $previousUnpaidInvoices = $unpaidMap[$customer->id] ?? [];

                $previousUnderpayment = 0;
                $carriedInvoiceIds = [];
                foreach ($previousUnpaidInvoices as $unpaidInv) {
                    $unpaidPrice = $unpaidInv->price > 0 ? $unpaidInv->price : ($customer->monthly_price ?? 0);
                    $outstanding = $unpaidPrice - (float) $unpaidInv->amount_paid;
                    if ($outstanding > 0) {
                        $previousUnderpayment += $outstanding;
                        $carriedInvoiceIds[] = $unpaidInv->id;
                    }
                }

                $totalPrice = $customer->monthly_price + $previousUnderpayment;

                $status = ($totalPrice == 0) ? 'paid' : 'unpaid';

                Invoice::create([
                    'customer_id' => $customer->id,
                    'admin_id' => $customer->admin_id,
                    'due_date' => $request->due_date,
                    'price' => $totalPrice,
                    'carried_underpayment' => $previousUnderpayment,
                    'status' => $status,
                    'paid_at' => ($status === 'paid') ? now() : null,
                    'payment_method' => ($status === 'paid') ? 'free' : null,
                ]);

                // Mark old unpaid invoices as 'carried'
                if ($previousUnderpayment > 0 && !empty($carriedInvoiceIds)) {
                    Invoice::whereIn('id', $carriedInvoiceIds)
                        ->update([
                            'status' => 'carried',
                            'underpayment' => 0,
                        ]);
                }

                $count++;
            }
        }

        return back()->with('success', "Berhasil membuat $count tagihan baru.");
    }

    /**
     * AJAX: Get List of Customers for Bulk Generation
     */
    public function getList(Request $request)
    {
        $user = Auth::user();
        $query = Customer::where('is_active', true);

        if ($user->role == 'operator') {
            $query->where('operator_id', $user->id);
        } elseif ($user->role == 'admin') {
            $query->where('admin_id', $user->id);
        } elseif ($user->role == 'superadmin' && $request->has('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        $customers = $query->get(['id', 'name', 'monthly_price', 'admin_id']);

        return response()->json([
            'customers' => $customers,
            'total' => $customers->count()
        ]);
    }

    /**
     * AJAX: Process Single Billing Item
     */
    public function processItem(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'month' => 'required|numeric',
            'year' => 'required|numeric',
            'due_date' => 'required|date',
        ]);

        $customer = Customer::findOrFail($request->customer_id);

        // Double check ownership
        if (Auth::user()->role == 'operator' && $customer->operator_id != Auth::user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }
        if (Auth::user()->role == 'admin' && $customer->admin_id != Auth::user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }
        // Superadmin is allowed to process any if needed, or we could add a validation for selected admin_id here if passed.
        if (Auth::user()->role == 'superadmin' && $request->has('admin_id') && $customer->admin_id != $request->admin_id) {
            return response()->json(['status' => 'error', 'message' => 'Admin ID mismatch'], 403);
        }

        $exists = Invoice::where('customer_id', $customer->id)
            ->whereMonth('due_date', $request->month)
            ->whereYear('due_date', $request->year)
            ->exists();

        if ($exists) {
            return response()->json(['status' => 'skipped', 'name' => $customer->name]);
        }

        // Check for ALL unpaid invoices from previous months
        $previousUnpaidInvoices = Invoice::where('customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->get();

        $previousUnderpayment = 0;
        $carriedInvoiceIds = [];
        foreach ($previousUnpaidInvoices as $unpaidInv) {
            $unpaidPrice = $unpaidInv->price > 0 ? $unpaidInv->price : ($customer->monthly_price ?? 0);
            $outstanding = $unpaidPrice - (float) $unpaidInv->amount_paid;
            if ($outstanding > 0) {
                $previousUnderpayment += $outstanding;
                $carriedInvoiceIds[] = $unpaidInv->id;
            }
        }

        $basePrice = $customer->monthly_price;
        $totalPrice = $basePrice + $previousUnderpayment;

        $status = ($totalPrice == 0) ? 'paid' : 'unpaid';

        // Create the invoice first
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'admin_id' => $customer->admin_id,
            'due_date' => $request->due_date,
            'price' => $totalPrice,
            'carried_underpayment' => $previousUnderpayment,
            'status' => $status,
            'paid_at' => ($status === 'paid') ? now() : null,
            'payment_method' => ($status === 'paid') ? 'free' : null,
        ]);

        // Mark old unpaid invoices as 'carried' so they won't be double-counted
        if ($previousUnderpayment > 0 && !empty($carriedInvoiceIds)) {
            Invoice::whereIn('id', $carriedInvoiceIds)
                ->update([
                    'status' => 'carried',
                    'underpayment' => 0,
                ]);
        }

        // === AUTO-PAY FROM BALANCE ===
        $balance = (float) $customer->balance;
        $autoPayStatus = 'created';
        $autoPayMsg = '';

        if ($status === 'paid') {
            $autoPayStatus = 'auto_paid';
            $autoPayMsg = ' (Lunas otomatis karena harga 0)';
            $customer->update(['is_active' => true]);
            $this->enableMikrotik($customer->pppoe_username);
        } elseif ($balance > 0) {
            if ($balance >= $totalPrice) {
                // Saldo cukup — lunas otomatis
                $customer->balance -= $totalPrice;
                $customer->save();

                $invoice->update([
                    'status' => 'paid',
                    'amount_paid' => $totalPrice,
                    'underpayment' => 0,
                    'payment_method' => 'auto_balance',
                    'paid_at' => now(),
                ]);

                // Record payment
                BillingPayment::create([
                    'invoice_id' => $invoice->id,
                    'customer_id' => $customer->id,
                    'admin_id' => Auth::id(),
                    'amount' => $totalPrice,
                    'method' => 'auto_balance',
                    'balance_used' => $totalPrice,
                    'excess_to_balance' => 0,
                    'notes' => 'Auto-pay dari saldo saat generate tagihan',
                ]);

                $customer->update(['is_active' => true]);
                $this->enableMikrotik($customer->pppoe_username);

                $autoPayStatus = 'auto_paid';
                $autoPayMsg = ' (Lunas otomatis dari saldo)';
            } else {
                // Saldo kurang — bayar sebagian dari saldo
                $usedBalance = $balance;
                $remaining = $totalPrice - $usedBalance;

                $customer->balance = 0;
                $customer->save();

                $invoice->update([
                    'amount_paid' => $usedBalance,
                    'underpayment' => $remaining,
                    'payment_method' => 'auto_balance',
                ]);

                // Record partial payment
                BillingPayment::create([
                    'invoice_id' => $invoice->id,
                    'customer_id' => $customer->id,
                    'admin_id' => Auth::id(),
                    'amount' => $usedBalance,
                    'method' => 'auto_balance',
                    'balance_used' => $usedBalance,
                    'excess_to_balance' => 0,
                    'notes' => 'Saldo terpakai sebagian saat generate tagihan',
                ]);

                $autoPayStatus = 'partial_balance';
                $autoPayMsg = ' (Saldo Rp ' . number_format($usedBalance, 0, ',', '.') . ' terpakai, sisa tagihan Rp ' . number_format($remaining, 0, ',', '.') . ')';
            }
        }

        return response()->json([
            'status' => $autoPayStatus,
            'name' => $customer->name,
            'message' => $autoPayMsg,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'due_date' => 'required|date',
            'price' => 'nullable|numeric',
        ]);

        $customer = Customer::findOrFail($request->customer_id);

        if (Auth::user()->role == 'operator') {
            if ($customer->operator_id != Auth::user()->id) {
                return back()->with('error', 'Anda tidak berhak membuat tagihan untuk pelanggan ini.');
            }
        } elseif (Auth::user()->role == 'admin') {
            if ($customer->admin_id != Auth::user()->id) {
                return back()->with('error', 'Anda tidak berhak membuat tagihan untuk pelanggan ini.');
            }
        }

        // Check for ALL unpaid invoices from previous months
        $previousUnpaidInvoices = Invoice::where('customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->get();

        $previousUnderpayment = 0;
        $carriedInvoiceIds = [];
        foreach ($previousUnpaidInvoices as $unpaidInv) {
            $unpaidPrice = $unpaidInv->price > 0 ? $unpaidInv->price : ($customer->monthly_price ?? 0);
            $outstanding = $unpaidPrice - (float) $unpaidInv->amount_paid;
            if ($outstanding > 0) {
                $previousUnderpayment += $outstanding;
                $carriedInvoiceIds[] = $unpaidInv->id;
            }
        }

        $basePrice = $request->price ?? $customer->monthly_price;
        $totalPrice = $basePrice + $previousUnderpayment;

        $status = ($totalPrice == 0) ? 'paid' : 'unpaid';

        // Create invoice
        $invoice = Invoice::create([
            'customer_id' => $request->customer_id,
            'admin_id' => $customer->admin_id,
            'due_date' => $request->due_date,
            'price' => $totalPrice,
            'carried_underpayment' => $previousUnderpayment,
            'status' => $status,
            'paid_at' => ($status === 'paid') ? now() : null,
            'payment_method' => ($status === 'paid') ? 'free' : null,
        ]);

        // Mark old unpaid invoices as 'carried' so they won't be double-counted
        if ($previousUnderpayment > 0 && !empty($carriedInvoiceIds)) {
            Invoice::whereIn('id', $carriedInvoiceIds)
                ->update([
                    'status' => 'carried',
                    'underpayment' => 0,
                ]);
        }

        // === AUTO-PAY FROM BALANCE ===
        $balance = (float) $customer->balance;
        $statusMsg = 'Tagihan manual berhasil dibuat!';

        if ($status === 'paid') {
            $statusMsg = 'Tagihan manual berhasil dibuat & lunas otomatis (Gratis / Harga 0).';
            $customer->update(['is_active' => true]);
            $this->enableMikrotik($customer->pppoe_username);
        } elseif ($balance > 0) {
            if ($balance >= $totalPrice) {
                // Saldo cukup — lunas otomatis
                $customer->balance -= $totalPrice;
                $customer->save();

                $invoice->update([
                    'status' => 'paid',
                    'amount_paid' => $totalPrice,
                    'underpayment' => 0,
                    'payment_method' => 'auto_balance',
                    'paid_at' => now(),
                ]);

                BillingPayment::create([
                    'invoice_id' => $invoice->id,
                    'customer_id' => $customer->id,
                    'admin_id' => Auth::id(),
                    'amount' => $totalPrice,
                    'method' => 'auto_balance',
                    'balance_used' => $totalPrice,
                    'excess_to_balance' => 0,
                    'notes' => 'Auto-pay dari saldo saat buat tagihan manual',
                ]);

                $customer->update(['is_active' => true]);
                $this->enableMikrotik($customer->pppoe_username);

                $statusMsg = 'Tagihan manual dibuat & lunas otomatis dari saldo (Rp ' . number_format($totalPrice, 0, ',', '.') . ')';
            } else {
                // Saldo kurang — bayar sebagian
                $usedBalance = $balance;
                $remaining = $totalPrice - $usedBalance;

                $customer->balance = 0;
                $customer->save();

                $invoice->update([
                    'amount_paid' => $usedBalance,
                    'underpayment' => $remaining,
                    'payment_method' => 'auto_balance',
                ]);

                BillingPayment::create([
                    'invoice_id' => $invoice->id,
                    'customer_id' => $customer->id,
                    'admin_id' => Auth::id(),
                    'amount' => $usedBalance,
                    'method' => 'auto_balance',
                    'balance_used' => $usedBalance,
                    'excess_to_balance' => 0,
                    'notes' => 'Saldo terpakai sebagian saat buat tagihan manual',
                ]);

                $statusMsg = 'Tagihan manual dibuat. Saldo Rp ' . number_format($usedBalance, 0, ',', '.') . ' terpakai, sisa tagihan Rp ' . number_format($remaining, 0, ',', '.') . '.';
            }
        }

        return back()->with('success', $statusMsg);
    }

    /**
     * AJAX: Pay with Method (Manual or Balance) — called from popup
     */
    public function payWithMethod(Request $request, $id)
    {
        try {
            $invoice = Invoice::with('customer')->findOrFail($id);
            $customer = $invoice->customer;

            if ($invoice->status == 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invoice sudah lunas.'
                ]);
            }

            $user = Auth::user();
            // Permission check
            if ($user->role == 'operator' && $customer->operator_id != $user->id) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            }

            $displayPrice = $invoice->price > 0 ? $invoice->price : ($customer->monthly_price ?? 0);
            // Account for any amount already paid (e.g. auto_balance during generate)
            $remainingToPay = $displayPrice - (float) $invoice->amount_paid;
            if ($remainingToPay <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tagihan ini sudah tidak memiliki sisa yang harus dibayar.'
                ]);
            }

            $method = $request->input('payment_method'); // 'manual' or 'balance'
            $amountPaid = (float) $request->input('amount_paid', 0);

            if ($method === 'balance') {
                // Pay with customer balance
                if ($customer->balance < $remainingToPay) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Saldo tidak mencukupi. Saldo: Rp ' . number_format($customer->balance, 0, ',', '.') . ', Sisa Tagihan: Rp ' . number_format($remainingToPay, 0, ',', '.')
                    ]);
                }

                // Deduct balance
                $customer->balance -= $remainingToPay;
                $customer->save();

                // Mark as paid
                $invoice->update([
                    'status' => 'paid',
                    'amount_paid' => $displayPrice,
                    'underpayment' => 0,
                    'payment_method' => 'balance',
                    'paid_at' => now(),
                ]);
                $customer->update(['is_active' => true]);

                // Record payment
                BillingPayment::create([
                    'invoice_id' => $invoice->id,
                    'customer_id' => $customer->id,
                    'admin_id' => Auth::id(),
                    'amount' => $remainingToPay,
                    'method' => 'balance',
                    'balance_used' => $remainingToPay,
                    'excess_to_balance' => 0,
                    'notes' => 'Pembayaran via saldo oleh ' . Auth::user()->name,
                ]);

                // Mikrotik enable
                $this->enableMikrotik($customer->pppoe_username);

                // Send WA notification
                $this->sendPaymentNotification($invoice, $customer, $remainingToPay, 'balance');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Pembayaran via saldo berhasil. Sisa saldo: Rp ' . number_format($customer->balance, 0, ',', '.'),
                    'customer' => $customer->name,
                ]);

            } elseif ($method === 'manual') {
                if ($amountPaid <= 0) {
                    return response()->json(['status' => 'error', 'message' => 'Jumlah pembayaran harus lebih dari 0.']);
                }

                $underpayment = max(0, $remainingToPay - $amountPaid);
                $excessToBalance = max(0, $amountPaid - $remainingToPay);

                if ($underpayment > 0) {
                    // Partial payment — status stays unpaid, record underpayment
                    $totalAmountPaid = (float) $invoice->amount_paid + $amountPaid;
                    $invoice->update([
                        'status' => 'unpaid',
                        'amount_paid' => $totalAmountPaid,
                        'underpayment' => $underpayment,
                        'payment_method' => 'manual',
                        'paid_at' => now(),
                    ]);
                    // Connection stays active
                    $customer->update(['is_active' => true]);
                    $this->enableMikrotik($customer->pppoe_username);

                    // Record payment
                    BillingPayment::create([
                        'invoice_id' => $invoice->id,
                        'customer_id' => $customer->id,
                        'admin_id' => Auth::id(),
                        'amount' => $amountPaid,
                        'method' => 'manual',
                        'balance_used' => 0,
                        'excess_to_balance' => 0,
                        'notes' => 'Pembayaran sebagian oleh ' . Auth::user()->name,
                    ]);

                    // Process arrears payments (Opsi B: separate amount per arrear)
                    $arrearsResult = $this->processArrearsPayments($request, $customer);

                    $msg = 'Pembayaran sebagian diterima. Kurang bayar: Rp ' . number_format($underpayment, 0, ',', '.');
                    if ($arrearsResult) {
                        $msg .= '. ' . $arrearsResult;
                    }

                    return response()->json([
                        'status' => 'partial',
                        'message' => $msg,
                        'customer' => $customer->name,
                        'underpayment' => $underpayment,
                    ]);
                } else {
                    // Full payment or overpayment
                    $totalAmountPaid = (float) $invoice->amount_paid + $amountPaid;

                    $invoice->update([
                        'status' => 'paid',
                        'amount_paid' => $totalAmountPaid,
                        'underpayment' => 0,
                        'payment_method' => 'manual',
                        'paid_at' => now(),
                    ]);
                    $customer->update(['is_active' => true]);

                    // If overpaid, add excess to customer balance
                    if ($excessToBalance > 0) {
                        $balanceBefore = (float) $customer->balance;
                        $customer->balance += $excessToBalance;
                        $customer->save();

                        // Catat ke riwayat top up agar terlihat di history
                        BalanceTopup::create([
                            'customer_id' => $customer->id,
                            'admin_id' => Auth::id(),
                            'amount' => $excessToBalance,
                            'balance_before' => $balanceBefore,
                            'balance_after' => (float) $customer->balance,
                            'notes' => 'Kelebihan bayar tagihan #INV-' . str_pad($invoice->id, 5, '0', STR_PAD_LEFT),
                            'invoice_id' => $invoice->id,
                        ]);
                    }

                    // Record payment
                    BillingPayment::create([
                        'invoice_id' => $invoice->id,
                        'customer_id' => $customer->id,
                        'admin_id' => Auth::id(),
                        'amount' => $amountPaid,
                        'method' => 'manual',
                        'balance_used' => 0,
                        'excess_to_balance' => $excessToBalance,
                        'notes' => 'Pembayaran lunas oleh ' . Auth::user()->name,
                    ]);

                    $this->enableMikrotik($customer->pppoe_username);
                    $this->sendPaymentNotification($invoice, $customer, $amountPaid, 'manual');

                    // Process arrears payments (Opsi B: separate amount per arrear)
                    $arrearsResult = $this->processArrearsPayments($request, $customer);

                    $msg = 'Pembayaran lunas berhasil.';
                    if ($excessToBalance > 0) {
                        $msg .= ' Kelebihan Rp ' . number_format($excessToBalance, 0, ',', '.') . ' ditambahkan ke saldo.';
                    }
                    if ($arrearsResult) {
                        $msg .= ' ' . $arrearsResult;
                    }

                    return response()->json([
                        'status' => 'success',
                        'message' => $msg,
                        'customer' => $customer->name,
                    ]);
                }
            }

            return response()->json(['status' => 'error', 'message' => 'Metode pembayaran tidak valid.']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Get customer info for payment popup
     */
    public function getInvoiceInfo($id)
    {
        $invoice = Invoice::with('customer')->findOrFail($id);
        $customer = $invoice->customer;
        $displayPrice = $invoice->price > 0 ? $invoice->price : ($customer->monthly_price ?? 0);

        // Get arrears: other UNPAID invoices for this customer (outstanding > 0)
        $arrears = Invoice::where('customer_id', $customer->id)
            ->where('id', '!=', $invoice->id)
            ->where('status', 'unpaid')
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($arr) use ($customer) {
                $arrPrice = $arr->price > 0 ? $arr->price : ($customer->monthly_price ?? 0);
                $outstanding = $arrPrice - (float) $arr->amount_paid;
                return [
                    'id' => $arr->id,
                    'due_date' => $arr->due_date,
                    'period' => Carbon::parse($arr->due_date)->locale('id')->isoFormat('MMMM Y'),
                    'price' => (float) $arrPrice,
                    'amount_paid' => (float) $arr->amount_paid,
                    'underpayment' => $outstanding,
                    'underpayment_formatted' => 'Rp ' . number_format($outstanding, 0, ',', '.'),
                ];
            })
            ->filter(fn($arr) => $arr['underpayment'] > 0)
            ->values();

        $remainingToPay = max(0, $displayPrice - (float) $invoice->amount_paid);

        // Calculate base price (without carried underpayment) for breakdown display
        $basePrice = $displayPrice - (float) $invoice->carried_underpayment;

        // Build carried underpayment details from carried invoices
        $carriedDetails = [];
        if ((float) $invoice->carried_underpayment > 0) {
            $carriedInvoices = Invoice::where('customer_id', $customer->id)
                ->where('status', 'carried')
                ->orderBy('due_date', 'asc')
                ->get();
            foreach ($carriedInvoices as $ci) {
                $ciPrice = $ci->price > 0 ? $ci->price : ($customer->monthly_price ?? 0);
                $ciOutstanding = $ciPrice - (float) $ci->amount_paid;
                if ($ciOutstanding > 0) {
                    $carriedDetails[] = [
                        'period' => Carbon::parse($ci->due_date)->locale('id')->isoFormat('MMMM Y'),
                        'amount' => $ciOutstanding,
                        'amount_formatted' => 'Rp ' . number_format($ciOutstanding, 0, ',', '.'),
                    ];
                }
            }
        }

        return response()->json([
            'invoice_id' => $invoice->id,
            'customer_name' => $customer->name,
            'customer_id' => $customer->id,
            'internet_number' => $customer->internet_number,
            'price' => $displayPrice,
            'price_formatted' => 'Rp ' . number_format($displayPrice, 0, ',', '.'),
            'base_price' => $basePrice,
            'base_price_formatted' => 'Rp ' . number_format($basePrice, 0, ',', '.'),
            'remaining_to_pay' => $remainingToPay,
            'remaining_to_pay_formatted' => 'Rp ' . number_format($remainingToPay, 0, ',', '.'),
            'balance' => (float) $customer->balance,
            'balance_formatted' => 'Rp ' . number_format($customer->balance, 0, ',', '.'),
            'balance_sufficient' => $customer->balance >= $remainingToPay,
            'carried_underpayment' => (float) $invoice->carried_underpayment,
            'carried_underpayment_formatted' => 'Rp ' . number_format($invoice->carried_underpayment, 0, ',', '.'),
            'carried_details' => $carriedDetails,
            'amount_paid' => (float) $invoice->amount_paid,
            'amount_paid_formatted' => 'Rp ' . number_format($invoice->amount_paid, 0, ',', '.'),
            'underpayment' => (float) $invoice->underpayment,
            'underpayment_formatted' => 'Rp ' . number_format($invoice->underpayment, 0, ',', '.'),
            'arrears' => $arrears,
        ]);
    }

    /**
     * AJAX: Get payment details for an invoice (for Detail popup)
     */
    public function getPaymentDetails($id)
    {
        $invoice = Invoice::with('customer')->findOrFail($id);
        $customer = $invoice->customer;
        $displayPrice = $invoice->price > 0 ? $invoice->price : ($customer->monthly_price ?? 0);

        $payments = BillingPayment::where('invoice_id', $invoice->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $paymentList = [];
        $runningPaid = 0;
        foreach ($payments as $idx => $payment) {
            $runningPaid += (float) $payment->amount + (float) $payment->balance_used;
            $remaining = max(0, $displayPrice - $runningPaid);

            $methodLabel = match ($payment->method) {
                'manual' => 'Manual',
                'balance' => 'Saldo',
                'auto_balance' => 'Auto Saldo',
                default => ucfirst($payment->method),
            };

            $paymentList[] = [
                'id' => $payment->id,
                'index' => $idx + 1,
                'date' => Carbon::parse($payment->created_at)->locale('id')->isoFormat('D MMM Y, HH:mm'),
                'amount' => (float) $payment->amount,
                'amount_formatted' => 'Rp ' . number_format($payment->amount, 0, ',', '.'),
                'balance_used' => (float) $payment->balance_used,
                'balance_used_formatted' => 'Rp ' . number_format($payment->balance_used, 0, ',', '.'),
                'excess_to_balance' => (float) $payment->excess_to_balance,
                'excess_formatted' => 'Rp ' . number_format($payment->excess_to_balance, 0, ',', '.'),
                'method' => $payment->method,
                'method_label' => $methodLabel,
                'notes' => $payment->notes,
                'admin_name' => $payment->admin ? $payment->admin->name : '-',
                'running_paid' => $runningPaid,
                'remaining' => $remaining,
                'remaining_formatted' => 'Rp ' . number_format($remaining, 0, ',', '.'),
            ];
        }

        return response()->json([
            'invoice_id' => $invoice->id,
            'customer_name' => $customer->name,
            'price' => $displayPrice,
            'price_formatted' => 'Rp ' . number_format($displayPrice, 0, ',', '.'),
            'carried_underpayment' => (float) $invoice->carried_underpayment,
            'carried_formatted' => 'Rp ' . number_format($invoice->carried_underpayment, 0, ',', '.'),
            'base_price' => $displayPrice - (float) $invoice->carried_underpayment,
            'base_price_formatted' => 'Rp ' . number_format($displayPrice - (float) $invoice->carried_underpayment, 0, ',', '.'),
            'total_paid' => (float) $invoice->amount_paid,
            'total_paid_formatted' => 'Rp ' . number_format($invoice->amount_paid, 0, ',', '.'),
            'underpayment' => (float) $invoice->underpayment,
            'underpayment_formatted' => 'Rp ' . number_format($invoice->underpayment, 0, ',', '.'),
            'status' => $invoice->status,
            'payments' => $paymentList,
        ]);
    }

    /**
     * AJAX: Cancel a single payment record
     */
    public function cancelSinglePayment($paymentId)
    {
        try {
            $payment = BillingPayment::findOrFail($paymentId);
            $invoice = Invoice::with('customer')->findOrFail($payment->invoice_id);
            $customer = $invoice->customer;

            // Reverse balance effects
            if ((float) $payment->excess_to_balance > 0) {
                $customer->balance -= (float) $payment->excess_to_balance;
            }
            if ((float) $payment->balance_used > 0) {
                $customer->balance += (float) $payment->balance_used;
            }
            if ($customer->balance < 0) {
                $customer->balance = 0;
            }
            $customer->save();

            // Recalculate invoice totals after removing this payment
            $paymentAmount = (float) $payment->amount + (float) $payment->balance_used;
            $newAmountPaid = max(0, (float) $invoice->amount_paid - $paymentAmount);

            $displayPrice = $invoice->price > 0 ? $invoice->price : ($customer->monthly_price ?? 0);
            $newUnderpayment = max(0, $displayPrice - $newAmountPaid);

            // Delete the payment
            $payment->delete();

            // Determine new status
            $remainingPayments = BillingPayment::where('invoice_id', $invoice->id)->count();
            if ($newAmountPaid <= 0 || $remainingPayments === 0) {
                $newStatus = 'unpaid';
                $newAmountPaid = 0;
                $newUnderpayment = 0;
            } elseif ($newAmountPaid >= $displayPrice) {
                $newStatus = 'paid';
                $newUnderpayment = 0;
            } else {
                $newStatus = 'unpaid';
                $newUnderpayment = $displayPrice - $newAmountPaid;
            }

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'underpayment' => $newUnderpayment,
                'status' => $newStatus,
                'paid_at' => $newStatus === 'paid' ? $invoice->paid_at : null,
                'payment_method' => $remainingPayments > 0 ? $invoice->payment_method : null,
            ]);

            // If status changed to unpaid, disable mikrotik
            if ($newStatus === 'unpaid' && $invoice->getOriginal('status') === 'paid') {
                try {
                    $pppoeUsername = $customer->pppoe_username;
                    if ($this->mikrotik->isConnected()) {
                        $this->mikrotik->setSecretStatus($pppoeUsername, 'disabled');
                        // Tidak kick koneksi aktif pelanggan
                    }
                } catch (\Exception $e) {
                    // Log but don't fail
                }
                $customer->update(['is_active' => false]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Pembayaran berhasil dibatalkan.',
                'new_status' => $newStatus,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper: Process arrears payments (Opsi B — separate amount per arrear)
     * Expects request to contain 'arrears_payments' as array of {id, amount}
     */
    private function processArrearsPayments(Request $request, $customer)
    {
        $arrearsPayments = $request->input('arrears_payments', []);
        if (empty($arrearsPayments)) {
            return null;
        }

        $paidCount = 0;
        $partialCount = 0;

        foreach ($arrearsPayments as $arrearPayment) {
            $arrearId = $arrearPayment['id'] ?? null;
            $arrearAmount = (float) ($arrearPayment['amount'] ?? 0);

            if (!$arrearId || $arrearAmount <= 0)
                continue;

            $arrearInvoice = Invoice::where('id', $arrearId)
                ->where('customer_id', $customer->id)
                ->where('status', 'unpaid')
                ->first();

            if (!$arrearInvoice)
                continue;

            // Compute actual outstanding for this arrear
            $arrearPrice = $arrearInvoice->price > 0 ? $arrearInvoice->price : ($customer->monthly_price ?? 0);
            $currentOutstanding = $arrearPrice - (float) $arrearInvoice->amount_paid;

            if ($currentOutstanding <= 0)
                continue;

            $newAmountPaid = (float) $arrearInvoice->amount_paid + $arrearAmount;
            $newUnderpayment = max(0, $currentOutstanding - $arrearAmount);

            if ($newUnderpayment <= 0) {
                // Fully paid
                $arrearInvoice->update([
                    'amount_paid' => $newAmountPaid,
                    'underpayment' => 0,
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
                $paidCount++;
            } else {
                // Still partial
                $arrearInvoice->update([
                    'amount_paid' => $newAmountPaid,
                    'underpayment' => $newUnderpayment,
                ]);
                $partialCount++;
            }
        }

        $parts = [];
        if ($paidCount > 0)
            $parts[] = "$paidCount tunggakan dilunasi";
        if ($partialCount > 0)
            $parts[] = "$partialCount tunggakan dibayar sebagian";

        return !empty($parts) ? implode(', ', $parts) . '.' : null;
    }

    /**
     * Helper: Enable Mikrotik connection
     */
    private function enableMikrotik($pppoeUsername)
    {
        try {
            if ($this->mikrotik->isConnected()) {
                $this->mikrotik->setSecretStatus($pppoeUsername, 'enabled');
                // Tidak kick koneksi aktif pelanggan
            }
        } catch (\Exception $e) {
            // Log but don't fail
        }
    }

    /**
     * Helper: Send WA payment notification
     */
    private function sendPaymentNotification($invoice, $customer, $amountPaid, $method)
    {
        try {
            if (!empty($customer->phone)) {
                $tglBayar = Carbon::now()->locale('id')->isoFormat('D MMMM Y, HH:mm');
                $nominal = number_format($amountPaid, 0, ',', '.');
                $periode = Carbon::parse($invoice->due_date)->locale('id')->isoFormat('MMMM Y');
                $linkDownload = route('frontend.invoice', $invoice->id);
                $metodeTeks = $method === 'balance' ? 'Saldo' : 'Manual';

                $text = "*PEMBAYARAN DITERIMA*\n\n";
                $text .= "Halo {$customer->name},\n";
                $text .= "Terima kasih, pembayaran tagihan internet Anda telah kami terima.\n\n";
                $text .= "📅 Tanggal Bayar: $tglBayar\n";
                $text .= "💰 Nominal: Rp $nominal\n";
                $text .= "💳 Metode: $metodeTeks\n";
                $text .= "🗓️ Periode Tagihan: $periode\n";
                $text .= "✅ Status: LUNAS\n\n";
                $text .= "📄 *Unduh Invoice (PDF):*\n";
                $text .= "$linkDownload\n\n";
                $text .= "Internet Anda sudah aktif kembali. Terima kasih atas kepercayaan Anda.";

                $this->wa->send($customer->phone, $text);
            }
        } catch (\Exception $e) {
            // Log but don't fail
        }
    }

    /**
     * PROCESS PAYMENT VIA AJAX (MASS PAYMENT)
     */
    public function processPaymentAjax($id)
    {
        try {
            $invoice = Invoice::with('customer')->findOrFail($id);

            // Skip if already paid
            if ($invoice->status == 'paid') {
                return response()->json([
                    'status' => 'skipped',
                    'message' => 'Invoice sudah lunas.',
                    'customer' => $invoice->customer->name
                ]);
            }

            $customer = $invoice->customer;
            $userPppoe = $customer->pppoe_username;

            // Validasi Operator
            if (Auth::user()->role == 'operator') {
                if ($customer->operator_id != Auth::user()->id) {
                    return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
                }
            }

            $displayPrice = $invoice->price > 0 ? $invoice->price : ($customer->monthly_price ?? 0);
            $remainingToPay = max(0, $displayPrice - (float) $invoice->amount_paid);

            // Update Database
            $invoice->update([
                'status' => 'paid',
                'amount_paid' => $displayPrice,
                'underpayment' => 0,
                'payment_method' => 'manual',
                'paid_at' => now(),
            ]);

            \App\Models\BillingPayment::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $customer->id,
                'admin_id' => Auth::id(),
                'amount' => $remainingToPay,
                'method' => 'manual',
                'balance_used' => 0,
                'excess_to_balance' => 0,
                'notes' => 'Pembayaran massal (Bayar Sekaligus)',
            ]);

            $customer->update(['is_active' => true]);

            // Eksekusi Mikrotik
            $pesanMikrotik = "";
            try {
                if ($this->mikrotik->isConnected()) {
                    $this->mikrotik->setSecretStatus($userPppoe, 'enabled');
                    // Tidak kick koneksi aktif pelanggan
                    $pesanMikrotik = "Mikrotik: Enabled.";
                } else {
                    $pesanMikrotik = "Mikrotik: Gagal Konek.";
                }
            } catch (\Exception $e) {
                // Log error but don't fail the payment
                $pesanMikrotik = "Mikrotik Error.";
            }

            // --- KIRIM NOTIFIKASI WA (LUNAS) ---
            $pesanWA = "";
            try {
                if (!empty($customer->phone)) {
                    $tglBayar = Carbon::now()->locale('id')->isoFormat('D MMMM Y, HH:mm');
                    $nominal = number_format($customer->monthly_price, 0, ',', '.');
                    $periode = Carbon::parse($invoice->due_date)->locale('id')->isoFormat('MMMM Y');
                    $linkDownload = route('frontend.invoice', $invoice->id);

                    $text = "*PEMBAYARAN DITERIMA*\n\n";
                    $text .= "Halo {$customer->name},\n";
                    $text .= "Terima kasih, pembayaran tagihan internet Anda telah kami terima.\n\n";
                    $text .= "📅 Tanggal Bayar: $tglBayar\n";
                    $text .= "💰 Nominal: Rp $nominal\n";
                    $text .= "🗓️ Periode Tagihan: $periode\n";
                    $text .= "✅ Status: LUNAS\n\n";
                    $text .= "📄 *Unduh Invoice (PDF):*\n";
                    $text .= "$linkDownload\n\n";
                    $text .= "Internet Anda sudah aktif kembali. Terima kasih atas kepercayaan Anda.";

                    $waResult = $this->wa->send($customer->phone, $text);
                    $pesanWA = $waResult['status'] ? "WA Terkirim." : "WA Gagal.";
                }
            } catch (\Exception $e) {
                $pesanWA = "WA Error.";
            }

            return response()->json([
                'status' => 'success',
                'customer' => $customer->name,
                'message' => "Sukses. $pesanMikrotik $pesanWA"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'customer' => 'Unknown'
            ], 500);
        }
    }

    /**
     * PROSES PEMBAYARAN (BAYAR & AKTIFKAN + KIRIM WA)
     */
    public function processPayment($id)
    {
        $invoice = Invoice::with('customer')->findOrFail($id);
        $customer = $invoice->customer;
        $userPppoe = $customer->pppoe_username;

        // Validasi Operator
        if (Auth::user()->role == 'operator') {
            if ($customer->operator_id != Auth::user()->id) {
                return back()->with('error', 'Akses Ditolak.');
            }
        }

        $displayPrice = $invoice->price > 0 ? $invoice->price : ($customer->monthly_price ?? 0);
        $remainingToPay = max(0, $displayPrice - (float) $invoice->amount_paid);

        // Update Database
        $invoice->update([
            'status' => 'paid',
            'amount_paid' => $displayPrice,
            'underpayment' => 0,
            'payment_method' => 'manual',
            'paid_at' => now(),
        ]);

        \App\Models\BillingPayment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'admin_id' => Auth::id(),
            'amount' => $remainingToPay,
            'method' => 'manual',
            'balance_used' => 0,
            'excess_to_balance' => 0,
            'notes' => 'Pembayaran manual (Direct)',
        ]);

        $customer->update(['is_active' => true]);

        // Eksekusi Mikrotik
        $pesanMikrotik = "";
        try {
            if ($this->mikrotik->isConnected()) {
                $this->mikrotik->setSecretStatus($userPppoe, 'enabled');
                // Tidak kick koneksi aktif pelanggan
                $pesanMikrotik = "Mikrotik: Enabled.";
            } else {
                $pesanMikrotik = "Mikrotik: Gagal Konek.";
            }
        } catch (\Exception $e) {
            $pesanMikrotik = "Mikrotik Error: " . $e->getMessage();
        }

        // --- KIRIM NOTIFIKASI WA (LUNAS) ---
        $pesanWA = "";
        if (!empty($customer->phone)) {
            $tglBayar = Carbon::now()->locale('id')->isoFormat('D MMMM Y, HH:mm');
            $nominal = number_format($customer->monthly_price, 0, ',', '.');
            $periode = Carbon::parse($invoice->due_date)->locale('id')->isoFormat('MMMM Y');

            // 1. GENERATE LINK DOWNLOAD INVOICE
            // Kita gunakan route frontend yang sudah ada
            $linkDownload = route('frontend.invoice', $invoice->id);

            $text = "*PEMBAYARAN DITERIMA*\n\n";
            $text .= "Halo {$customer->name},\n";
            $text .= "Terima kasih, pembayaran tagihan internet Anda telah kami terima.\n\n";
            $text .= "📅 Tanggal Bayar: $tglBayar\n";
            $text .= "💰 Nominal: Rp $nominal\n";
            $text .= "🗓️ Periode Tagihan: $periode\n";
            $text .= "✅ Status: LUNAS\n\n";

            // 2. MASUKKAN LINK KE PESAN
            $text .= "📄 *Unduh Invoice (PDF):*\n";
            $text .= "$linkDownload\n\n";

            $text .= "Internet Anda sudah aktif kembali. Terima kasih atas kepercayaan Anda.";

            $waResult = $this->wa->send($customer->phone, $text);
            $pesanWA = $waResult['status'] ? "WA Terkirim." : "WA Gagal.";
        }

        return back()->with('success', "Pembayaran sukses! $pesanMikrotik $pesanWA");
    }

    /**
     * BATALKAN PEMBAYARAN (KOREKSI + KIRIM WA)
     */
    public function cancelPayment($id)
    {
        $invoice = Invoice::with('customer')->findOrFail($id);
        $customer = $invoice->customer;

        if ($invoice->status != 'paid')
            return back()->with('error', 'Gagal.');

        // Validasi Operator
        if (Auth::user()->role == 'operator') {
            if ($customer->operator_id != Auth::user()->id) {
                return back()->with('error', 'Akses Ditolak.');
            }
        }

        // === REVERSE SALDO: kembalikan excess_to_balance & balance_used ===
        $payments = BillingPayment::where('invoice_id', $invoice->id)->get();
        $totalExcessReversed = 0;
        $totalBalanceRestored = 0;

        foreach ($payments as $payment) {
            // Kurangi kelebihan pembayaran yang sudah masuk ke saldo
            if ((float) $payment->excess_to_balance > 0) {
                $customer->balance -= (float) $payment->excess_to_balance;
                $totalExcessReversed += (float) $payment->excess_to_balance;
            }

            // Kembalikan saldo yang terpakai untuk pembayaran via balance/auto_balance
            if ((float) $payment->balance_used > 0) {
                $customer->balance += (float) $payment->balance_used;
                $totalBalanceRestored += (float) $payment->balance_used;
            }
        }

        // Pastikan saldo tidak negatif (edge case)
        if ($customer->balance < 0) {
            $customer->balance = 0;
        }
        $customer->save();

        // Hapus semua record pembayaran untuk invoice ini
        BillingPayment::where('invoice_id', $invoice->id)->delete();

        // Hapus juga record BalanceTopup yang terkait invoice ini (kelebihan bayar)
        BalanceTopup::where('invoice_id', $invoice->id)->delete();

        // Update Database
        $invoice->update([
            'status' => 'unpaid',
            'amount_paid' => 0,
            'underpayment' => 0,
            'payment_method' => null,
            'paid_at' => null
        ]);
        $customer->update(['is_active' => false]);

        // Eksekusi Mikrotik
        $userPppoe = $customer->pppoe_username;
        $pesanMikrotik = "";
        try {
            if ($this->mikrotik->isConnected()) {
                $this->mikrotik->setSecretStatus($userPppoe, 'disabled');
                // Tidak kick koneksi aktif pelanggan
                $pesanMikrotik = "Mikrotik: Disabled.";
            }
        } catch (\Exception $e) {
            $pesanMikrotik = "Mikrotik Error: " . $e->getMessage();
        }

        // --- KIRIM NOTIFIKASI WA (PEMBATALAN) ---
        $pesanWA = "";
        if (!empty($customer->phone)) {
            $nominal = number_format($customer->monthly_price, 0, ',', '.');
            $periode = Carbon::parse($invoice->due_date)->locale('id')->isoFormat('MMMM Y');

            $text = "*PEMBATALAN STATUS LUNAS*\n\n";
            $text .= "Halo {$customer->name},\n";
            $text .= "Mohon maaf, terjadi koreksi pada sistem kami. Status pembayaran tagihan periode *$periode* (Rp $nominal) telah dibatalkan menjadi **BELUM LUNAS**.\n\n";
            $text .= "Koneksi internet untuk sementara dinonaktifkan.\n";
            $text .= "Silakan hubungi admin jika ini adalah kesalahan.";

            $waResult = $this->wa->send($customer->phone, $text);
            $pesanWA = $waResult['status'] ? "WA Terkirim." : "WA Gagal.";
        }

        // Build pesan detail saldo
        $pesanSaldo = "";
        if ($totalExcessReversed > 0) {
            $pesanSaldo .= " Kelebihan saldo Rp " . number_format($totalExcessReversed, 0, ',', '.') . " dikembalikan dari saldo pelanggan.";
        }
        if ($totalBalanceRestored > 0) {
            $pesanSaldo .= " Saldo Rp " . number_format($totalBalanceRestored, 0, ',', '.') . " dikembalikan ke pelanggan.";
        }

        return back()->with('warning', "Pembayaran DIBATALKAN!{$pesanSaldo} $pesanMikrotik $pesanWA");
    }

    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);

        if (Auth::user()->role == 'operator') {
            if ($invoice->customer->operator_id != Auth::user()->id)
                abort(403);
        }

        $invoice->delete();
        return back()->with('success', 'Invoice berhasil dihapus.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'type' => 'required|in:selected,all',
            'ids' => 'nullable|array',
            'ids.*' => 'exists:invoices,id',
            'month' => 'nullable|numeric',
            'year' => 'nullable|numeric',
        ]);

        $user = Auth::user();
        $count = 0;

        if ($request->type == 'selected') {
            if (!$request->ids) {
                return back()->with('error', 'Tidak ada tagihan yang dipilih.');
            }

            $invoices = Invoice::whereIn('id', $request->ids)->get();
            foreach ($invoices as $inv) {
                // Permission Check
                if ($user->role == 'operator' && $inv->customer->operator_id != $user->id)
                    continue;
                if ($user->role == 'admin' && $inv->admin_id != $user->id)
                    continue;

                $inv->delete();
                $count++;
            }

        } else {
            // Delete ALL for Month/Year
            if (!$request->month || !$request->year) {
                return back()->with('error', 'Parameter bulan/tahun tidak valid.');
            }

            $query = Invoice::whereMonth('due_date', $request->month)
                ->whereYear('due_date', $request->year);

            if ($user->role == 'operator') {
                $query->whereHas('customer', function ($q) use ($user) {
                    $q->where('operator_id', $user->id);
                });
            }

            // Global Scope handles Admin/Superadmin generally, but explicit check doesn't hurt OR if using TenantScope
            // Assuming TenantScope handles 'admin' filtering automatically.

            $count = $query->delete();
        }

        return back()->with('success', "Berhasil menghapus $count tagihan.");
    }

    public function print($id)
    {
        $invoice = Invoice::with('customer')->findOrFail($id);
        if (Auth::user()->role == 'operator') {
            if ($invoice->customer->operator_id != Auth::user()->id)
                abort(403);
        }
        $company = Company::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('admin_id', $invoice->admin_id)
            ->first();

        // Convert Logo to Base64 (Optional for print, but keeps view logic simple)
        $logoBase64 = null;
        if ($company && !empty($company->logo_path)) {
            $path = public_path('uploads/' . $company->logo_path);
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }

        return view('billing.invoice', compact('invoice', 'company', 'logoBase64'));
    }

    public function bulkUpdateDueDate(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:invoices,id',
            'due_date' => 'required|date',
        ]);

        $user = Auth::user();
        $count = 0;

        $invoices = Invoice::whereIn('id', $request->ids)->get();
        foreach ($invoices as $inv) {
            // Permission Check
            if ($user->role == 'operator' && $inv->customer->operator_id != $user->id)
                continue;
            if ($user->role == 'admin' && $inv->admin_id != $user->id)
                continue;

            $inv->update(['due_date' => $request->due_date]);
            $count++;
        }

        return back()->with('success', "Berhasil memperbarui jatuh tempo untuk $count tagihan.");
    }

    /**
     * AJAX: Rollback Generate — Hapus semua tagihan hasil generate pada bulan/tahun tertentu
     * dan kembalikan saldo yang telah terpotong. Admin only.
     */
    public function rollbackGenerate(Request $request)
    {
        $user = Auth::user();

        // Admin & Superadmin only
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['status' => 'error', 'message' => 'Akses ditolak. Hanya admin yang bisa membatalkan generate.'], 403);
        }

        $request->validate([
            'month' => 'required|numeric',
            'year' => 'required|numeric',
        ]);

        $month = $request->month;
        $year = $request->year;

        // Get invoices for that month/year
        $invoiceQuery = Invoice::with('customer')
            ->whereMonth('due_date', $month)
            ->whereYear('due_date', $year);

        if ($user->role == 'admin') {
            $invoiceQuery->where('admin_id', $user->id);
        } elseif ($user->role == 'superadmin' && $request->has('admin_id') && $request->admin_id) {
            $invoiceQuery->where('admin_id', $request->admin_id);
        }

        $invoices = $invoiceQuery->get();

        if ($invoices->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Tidak ada tagihan ditemukan pada periode ini.']);
        }

        $deletedCount = 0;
        $restoredBalance = 0;

        foreach ($invoices as $invoice) {
            // Find all auto_balance payments for this invoice and restore balances
            $autoPayments = BillingPayment::where('invoice_id', $invoice->id)
                ->where('method', 'auto_balance')
                ->get();

            foreach ($autoPayments as $payment) {
                $customer = Customer::find($payment->customer_id);
                if ($customer) {
                    $customer->balance += (float) $payment->balance_used;
                    $customer->save();
                    $restoredBalance += (float) $payment->balance_used;
                }
            }

            // Delete all billing payments for this invoice
            BillingPayment::where('invoice_id', $invoice->id)->delete();

            // Restore carried invoices back to 'unpaid' if this invoice had carried_underpayment
            if ((float) $invoice->carried_underpayment > 0) {
                Invoice::where('customer_id', $invoice->customer_id)
                    ->where('status', 'carried')
                    ->update([
                        'status' => 'unpaid',
                    ]);
            }

            $invoice->delete();
            $deletedCount++;
        }

        $monthName = Carbon::createFromFormat('!m', $month)->locale('id')->isoFormat('MMMM');
        $msg = "Berhasil membatalkan $deletedCount tagihan periode {$monthName} {$year}.";
        if ($restoredBalance > 0) {
            $msg .= ' Saldo dikembalikan: Rp ' . number_format($restoredBalance, 0, ',', '.') . '.';
        }

        return response()->json([
            'status' => 'success',
            'message' => $msg,
            'deleted' => $deletedCount,
            'restored_balance' => $restoredBalance,
        ]);
    }
}
