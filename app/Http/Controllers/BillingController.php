<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Company;
use App\Models\User;
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

        // Query Tagihan dengan Filter
        $invoiceQuery = Invoice::with('customer')
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

        $invoices = $invoiceQuery->get();

        // 2. Hitung Totals dari Data Terfilter
        $total_bill = 0;
        $paid_bill = 0;
        $unpaid_bill = 0;

        foreach ($invoices as $inv) {
            $price = $inv->price > 0 ? $inv->price : ($inv->customer->monthly_price ?? 0);

            $total_bill += $price;
            if ($inv->status == 'paid') {
                $paid_bill += $price;
            } else {
                $unpaid_bill += $price;
            }
        }

        $customerQuery = Customer::orderBy('name', 'asc');
        if ($user->role == 'operator') {
            $customerQuery->where('operator_id', $user->id);
        }
        $customers = $customerQuery->get();

        $admins = [];
        if ($user->role == 'superadmin') {
            $admins = User::whereIn('role', ['admin', 'superadmin'])->get(['id', 'name', 'role']);
        }

        // Build a map of customer balances for the view
        $customerBalances = [];
        foreach ($invoices as $inv) {
            if ($inv->customer && !isset($customerBalances[$inv->customer_id])) {
                $customerBalances[$inv->customer_id] = (float) $inv->customer->balance;
            }
        }

        // Build arrears map: invoices with underpayment > 0 per customer (from any month)
        $customerIds = $invoices->pluck('customer_id')->unique()->toArray();
        $arrearsQuery = Invoice::with('customer')
            ->whereIn('customer_id', $customerIds)
            ->where('underpayment', '>', 0)
            ->orderBy('due_date', 'asc')
            ->get();

        $arrearsByCustomer = [];
        foreach ($arrearsQuery as $arrear) {
            $arrearsByCustomer[$arrear->customer_id][] = $arrear;
        }

        return view('billing.index', compact('invoices', 'customers', 'month', 'year', 'total_bill', 'paid_bill', 'unpaid_bill', 'admins', 'selectedAdminId', 'customerBalances', 'arrearsByCustomer'));
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

        $count = 0;
        foreach ($activeCustomers as $customer) {
            $exists = Invoice::where('customer_id', $customer->id)
                ->where('status', 'unpaid')
                ->whereMonth('due_date', $request->month)
                ->whereYear('due_date', $request->year)
                ->exists();

            if (!$exists) {
                Invoice::create([
                    'customer_id' => $customer->id,
                    'admin_id' => $customer->admin_id, // Ensure admin_id is carried over
                    'due_date' => $request->due_date,
                    'price' => $customer->monthly_price, // Save current price
                    'status' => 'unpaid',
                ]);
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
            ->where('status', 'unpaid')
            ->whereMonth('due_date', $request->month)
            ->whereYear('due_date', $request->year)
            ->exists();

        if ($exists) {
            return response()->json(['status' => 'skipped', 'name' => $customer->name]);
        }

        // Check for underpayment from previous months
        $previousUnderpayment = Invoice::where('customer_id', $customer->id)
            ->where('underpayment', '>', 0)
            ->sum('underpayment');

        $basePrice = $customer->monthly_price;
        $totalPrice = $basePrice + $previousUnderpayment;

        Invoice::create([
            'customer_id' => $customer->id,
            'admin_id' => $customer->admin_id,
            'due_date' => $request->due_date,
            'price' => $totalPrice,
            'carried_underpayment' => $previousUnderpayment,
            'status' => 'unpaid',
        ]);

        // Clear the underpayment on old invoices since it's been carried over
        if ($previousUnderpayment > 0) {
            Invoice::where('customer_id', $customer->id)
                ->where('underpayment', '>', 0)
                ->update(['underpayment' => 0]);
        }

        return response()->json(['status' => 'created', 'name' => $customer->name]);
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

        Invoice::create([
            'customer_id' => $request->customer_id,
            'admin_id' => $customer->admin_id,
            'due_date' => $request->due_date,
            'price' => $request->price ?? $customer->monthly_price,
            'status' => 'unpaid',
        ]);

        return back()->with('success', 'Tagihan manual berhasil dibuat!');
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
            $method = $request->input('payment_method'); // 'manual' or 'balance'
            $amountPaid = (float) $request->input('amount_paid', 0);

            if ($method === 'balance') {
                // Pay with customer balance
                if ($customer->balance < $displayPrice) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Saldo tidak mencukupi. Saldo: Rp ' . number_format($customer->balance, 0, ',', '.') . ', Tagihan: Rp ' . number_format($displayPrice, 0, ',', '.')
                    ]);
                }

                // Deduct balance
                $customer->balance -= $displayPrice;
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

                // Mikrotik enable
                $this->enableMikrotik($customer->pppoe_username);

                // Send WA notification
                $this->sendPaymentNotification($invoice, $customer, $displayPrice, 'balance');

                return response()->json([
                    'status' => 'success',
                    'message' => 'Pembayaran via saldo berhasil. Sisa saldo: Rp ' . number_format($customer->balance, 0, ',', '.'),
                    'customer' => $customer->name,
                ]);

            } elseif ($method === 'manual') {
                if ($amountPaid <= 0) {
                    return response()->json(['status' => 'error', 'message' => 'Jumlah pembayaran harus lebih dari 0.']);
                }

                $underpayment = max(0, $displayPrice - $amountPaid);

                if ($underpayment > 0) {
                    // Partial payment — status stays unpaid, record underpayment
                    $invoice->update([
                        'status' => 'unpaid',
                        'amount_paid' => $amountPaid,
                        'underpayment' => $underpayment,
                        'payment_method' => 'manual',
                        'paid_at' => now(),
                    ]);
                    // Connection stays active
                    $customer->update(['is_active' => true]);
                    $this->enableMikrotik($customer->pppoe_username);

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
                    $overpayment = $amountPaid - $displayPrice;

                    $invoice->update([
                        'status' => 'paid',
                        'amount_paid' => $amountPaid,
                        'underpayment' => 0,
                        'payment_method' => 'manual',
                        'paid_at' => now(),
                    ]);
                    $customer->update(['is_active' => true]);

                    // If overpaid, add to customer balance
                    if ($overpayment > 0) {
                        $customer->balance += $overpayment;
                        $customer->save();
                    }

                    $this->enableMikrotik($customer->pppoe_username);
                    $this->sendPaymentNotification($invoice, $customer, $amountPaid, 'manual');

                    // Process arrears payments (Opsi B: separate amount per arrear)
                    $arrearsResult = $this->processArrearsPayments($request, $customer);

                    $msg = 'Pembayaran lunas berhasil.';
                    if ($overpayment > 0) {
                        $msg .= ' Kelebihan Rp ' . number_format($overpayment, 0, ',', '.') . ' ditambahkan ke saldo.';
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

        // Get arrears: other invoices for this customer with underpayment > 0
        $arrears = Invoice::where('customer_id', $customer->id)
            ->where('id', '!=', $invoice->id)
            ->where('underpayment', '>', 0)
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($arr) {
                return [
                    'id' => $arr->id,
                    'due_date' => $arr->due_date,
                    'period' => Carbon::parse($arr->due_date)->locale('id')->isoFormat('MMMM Y'),
                    'price' => (float) $arr->price,
                    'amount_paid' => (float) $arr->amount_paid,
                    'underpayment' => (float) $arr->underpayment,
                    'underpayment_formatted' => 'Rp ' . number_format($arr->underpayment, 0, ',', '.'),
                ];
            });

        return response()->json([
            'invoice_id' => $invoice->id,
            'customer_name' => $customer->name,
            'customer_id' => $customer->id,
            'internet_number' => $customer->internet_number,
            'price' => $displayPrice,
            'price_formatted' => 'Rp ' . number_format($displayPrice, 0, ',', '.'),
            'balance' => (float) $customer->balance,
            'balance_formatted' => 'Rp ' . number_format($customer->balance, 0, ',', '.'),
            'balance_sufficient' => $customer->balance >= $displayPrice,
            'carried_underpayment' => (float) $invoice->carried_underpayment,
            'carried_underpayment_formatted' => 'Rp ' . number_format($invoice->carried_underpayment, 0, ',', '.'),
            'amount_paid' => (float) $invoice->amount_paid,
            'amount_paid_formatted' => 'Rp ' . number_format($invoice->amount_paid, 0, ',', '.'),
            'underpayment' => (float) $invoice->underpayment,
            'underpayment_formatted' => 'Rp ' . number_format($invoice->underpayment, 0, ',', '.'),
            'arrears' => $arrears,
        ]);
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

            if (!$arrearId || $arrearAmount <= 0) continue;

            $arrearInvoice = Invoice::where('id', $arrearId)
                ->where('customer_id', $customer->id)
                ->where('underpayment', '>', 0)
                ->first();

            if (!$arrearInvoice) continue;

            $newAmountPaid = (float) $arrearInvoice->amount_paid + $arrearAmount;
            $newUnderpayment = max(0, (float) $arrearInvoice->underpayment - $arrearAmount);

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
        if ($paidCount > 0) $parts[] = "$paidCount tunggakan dilunasi";
        if ($partialCount > 0) $parts[] = "$partialCount tunggakan dibayar sebagian";

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
                $this->mikrotik->kickUser($pppoeUsername);
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

            // Update Database
            $invoice->update(['status' => 'paid']);
            $customer->update(['is_active' => true]);

            // Eksekusi Mikrotik
            $pesanMikrotik = "";
            try {
                if ($this->mikrotik->isConnected()) {
                    $this->mikrotik->setSecretStatus($userPppoe, 'enabled');
                    $this->mikrotik->kickUser($userPppoe);
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

        // Update Database
        $invoice->update(['status' => 'paid']);
        $customer->update(['is_active' => true]);

        // Eksekusi Mikrotik
        $pesanMikrotik = "";
        try {
            if ($this->mikrotik->isConnected()) {
                $this->mikrotik->setSecretStatus($userPppoe, 'enabled');
                $this->mikrotik->kickUser($userPppoe);
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
                $this->mikrotik->kickUser($userPppoe);
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

        return back()->with('warning', "Pembayaran DIBATALKAN! $pesanMikrotik $pesanWA");
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
}
