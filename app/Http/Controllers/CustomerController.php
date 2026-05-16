<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use App\Exports\CustomersExport;
use App\Imports\CustomersImport;
use App\Exports\CustomerTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Company;

class CustomerController extends Controller
{
    protected $mikrotik;

    public function __construct(MikrotikService $mikrotik)
    {
        $this->mikrotik = $mikrotik;
    }

    // 1. Halaman Utama Manajemen Pelanggan
    public function index(Request $request)
    {
        $user = auth()->user();
        $selectedAdmin = $request->input('admin_id');

        $query = Customer::with(['operator', 'admin']);

        if ($user->role == 'operator') {
            $query->where('operator_id', $user->id);
        } elseif ($user->isSuperAdmin() && $selectedAdmin) {
            $query->where('admin_id', $selectedAdmin);
        }

        $customers = $query->get();
        $operators = User::where('role', 'operator')->get();
        
        $admins = [];
        if ($user->isSuperAdmin()) {
            $admins = User::whereIn('role', ['admin', 'superadmin'])->get();
        }

        // Hitung total saldo seluruh pelanggan
        $totalBalance = $customers->sum(function ($c) {
            return (float) $c->balance;
        });
        $totalCustomers = $customers->count();
        $customersWithBalance = $customers->filter(function ($c) {
            return (float) $c->balance > 0;
        })->count();

        // Ambil profile dari Mikrotik untuk dropdown 'Tambah User'
        $profiles = [];
        try {
            if ($this->mikrotik->isConnected()) {
                $profiles = $this->mikrotik->getProfiles();
            }
        } catch (\Exception $e) {
            // Abaikan error koneksi agar halaman tetap jalan
        }

        return view('customers.index', compact('customers', 'profiles', 'operators', 'admins', 'selectedAdmin', 'totalBalance', 'totalCustomers', 'customersWithBalance'));
    }

    // 2. Simpan User Baru (Ke DB & Mikrotik)
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $admin = $user->isAdmin() ? $user : $user->parent;
            $plan = $admin->plan;

            if (!$plan) {
                return back()->with('error', 'Layanan Anda belum diaktifkan (Belum memiliki paket).');
            }

            if (!$request->id) { // New customer
                $currentCount = Customer::where('admin_id', $admin->id)->count();
                if ($currentCount >= $plan->max_customers) {
                    return back()->with('error', "Limit Pelanggan Tercapai! Paket Anda (" . $plan->name . ") hanya mendukung maksimal " . $plan->max_customers . " pelanggan.");
                }
            }
        }

        $request->validate([
            'name' => 'required',
            'internet_number' => 'required|unique:customers,internet_number', // Validasi baru
            'pppoe_username' => 'required|unique:customers,pppoe_username',
            'pppoe_password' => 'required',
            'profile' => 'required',
            'monthly_price' => 'required|numeric',
        ]);

        try {
            // 1. Simpan ke Mikrotik (Username & Password tetap dipakai disini)
            $this->mikrotik->addSecret([
                'username' => $request->pppoe_username,
                'password' => $request->pppoe_password,
                'profile' => $request->profile,
                'comment' => $request->name . " (" . $request->internet_number . ")" // Opsional: Tambah No Inet di komentar mikrotik
            ]);

            // 2. Simpan ke Database
            Customer::create([
                'internet_number' => $request->internet_number, // Simpan No Internet
                'name' => $request->name,
                'phone' => $request->phone,
                'pppoe_username' => $request->pppoe_username,
                'pppoe_password' => $request->pppoe_password,
                'profile' => $request->profile,
                'monthly_price' => $request->monthly_price,
                'is_active' => true,
                'operator_id' => $request->operator_id,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'notes' => $request->notes,
            ]);

            return back()->with('success', 'Pelanggan berhasil ditambahkan.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }

    // 3. Update Data Database (No HP / Harga)
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $request->validate([
            'internet_number' => 'required|unique:customers,internet_number,' . $id,
            'name' => 'required',
            'monthly_price' => 'required|numeric',
            'profile' => 'required', // Validasi Profile Baru
        ]);

        try {
            // 1. Update ke Mikrotik (Sinkronisasi Profile dan Status)
            $mikrotikData = [
                'profile' => $request->profile
            ];

            if ($request->has('is_active')) {
                $mikrotikData['disabled'] = $request->is_active ? 'false' : 'true';
            }

            $this->mikrotik->updateSecret($customer->pppoe_username, $mikrotikData);

            // 2. Update Database Lokal
            $customer->update([
                'internet_number' => $request->internet_number,
                'name' => $request->name,
                'phone' => $request->phone,
                'monthly_price' => $request->monthly_price,
                'operator_id' => $request->operator_id,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'profile' => $request->profile,
                'notes' => $request->notes,
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : $customer->is_active,
            ]);

            return back()->with('success', 'Data pelanggan & Paket Internet (Profile) berhasil diperbarui.');
        } catch (\Exception $e) {
            // Jika mikrotik mati, update DB saja tapi beri peringatan, atau gagalkan keduanya.
            // Disini kita gagalkan agar data tetap sinkron.
            return back()->with('error', 'Gagal update ke Mikrotik: ' . $e->getMessage());
        }
    }

    // 4. Hapus User (Dari DB & Mikrotik OPSIONAL)
    public function destroy(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        try {
            // Hapus di Mikrotik jika diminta (default true jika dari form lama tanpa flag, 
            // tapi kita akan ubah workflow di view untuk selalu kirim flag)
            $deleteMikrotik = $request->input('delete_mikrotik', '0') == '1';

            if ($deleteMikrotik) {
                $this->mikrotik->removeSecret($customer->pppoe_username);
            }

            // Hapus di DB
            $customer->delete();

            $msg = 'Pelanggan dihapus dari database.';
            if ($deleteMikrotik)
                $msg .= ' PPPoE Secret juga dihapus dari Mikrotik.';

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // --- LOGIKA SINKRONISASI (AJAX) ---

    // Tahap A: Ambil daftar username dari Mikrotik
    public function syncGetList()
    {
        try {
            $secrets = $this->mikrotik->getSecrets();
            // Kita hanya butuh data mentahnya untuk dikirim ke JS
            return response()->json([
                'status' => 'success',
                'data' => $secrets,
                'total' => count($secrets)
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function syncProcessItem(Request $request)
    {
        $secret = $request->secret;
        if (!$secret || !isset($secret['name'])) {
            return response()->json(['status' => 'error', 'message' => 'Invalid data'], 400);
        }

        $user = auth()->user();
        $admin = $user->isSuperAdmin() ? ($user->isSuperAdmin() && $request->admin_id ? User::find($request->admin_id) : $user) : ($user->isAdmin() ? $user : $user->parent);
        
        // If superadmin but no admin selected/found, fallback to current user
        if (!$admin) $admin = $user;

        $customer = Customer::where('admin_id', $admin->id)->where('pppoe_username', $secret['name'])->first();

        // Check Plan Limit (Total Pelanggan)
        if (!$user->isSuperAdmin()) {
            $admin = $user->isAdmin() ? $user : $user->parent;
            $plan = $admin->plan;

            if ($plan && $plan->max_customers > 0 && !$customer) {
                $currentCount = Customer::where('admin_id', $admin->id)->count();
                if ($currentCount >= $plan->max_customers) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Limit pelanggan ({$plan->max_customers}) tercapai. Sinkronisasi dihentikan.",
                        'stop' => true
                    ], 403);
                }
            }
        }

        if ($customer) {
            // Logic Update (Sama seperti sebelumnya)
            $customer->update([
                'pppoe_password' => $secret['password'] ?? '',
                'profile' => $secret['profile'] ?? 'default',
            ]);
            return response()->json(['status' => 'updated', 'name' => $secret['name']]);
        } else {
            // Logic Insert Baru (DIPERBARUI)

            // Generate 8 Digit Angka Acak untuk Nomor Internet
            // Loop while sederhana untuk memastikan benar-benar unik (opsional tapi disarankan)
            do {
                $randomInet = rand(10000000, 99999999);
            } while (Customer::where('internet_number', $randomInet)->exists());

            Customer::create([
                'internet_number' => $randomInet, // <-- Pakai angka acak 8 digit
                'name' => $secret['comment'] ?? $secret['name'],
                'pppoe_username' => $secret['name'],
                'pppoe_password' => $secret['password'] ?? '',
                'profile' => $secret['profile'] ?? 'default',
                'monthly_price' => 0,
                'is_active' => ($secret['disabled'] ?? 'false') == 'false'
            ]);

            return response()->json(['status' => 'created', 'name' => $secret['name']]);
        }
    }

    public function export()
    {
        return Excel::download(new CustomersExport, 'data_pelanggan.xlsx');
    }

    // 2. Import Data dari Excel
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            Excel::import(new CustomersImport, $request->file('file'));
            return back()->with('success', 'Data pelanggan berhasil diimpor ke Database!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal impor: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        return Excel::download(new CustomerTemplateExport, 'template_import_pelanggan.xlsx');
    }

    // 10. Hapus Semua Pelanggan (Tenant-Aware)
    public function destroyAll(Request $request)
    {
        $user = auth()->user();
        $query = Customer::query();

        if ($user->role == 'operator') {
            $query->where('operator_id', $user->id);
        }

        $customers = $query->get();

        if ($customers->isEmpty()) {
            return back()->with('info', 'Tidak ada data pelanggan untuk dihapus.');
        }

        try {
            $failedMikrotik = [];
            $deleteMikrotik = $request->has('delete_mikrotik') && $request->delete_mikrotik == '1';

            if ($deleteMikrotik) {
                foreach ($customers as $customer) {
                    try {
                        $this->mikrotik->removeSecret($customer->pppoe_username);
                    } catch (\Exception $e) {
                        $failedMikrotik[] = $customer->pppoe_username;
                    }
                }
            }

            // Bulk delete database records
            $count = $customers->count();
            Customer::whereIn('id', $customers->pluck('id'))->delete();

            $msg = "Berhasil menghapus {$count} pelanggan dari database.";
            if ($deleteMikrotik) {
                if (empty($failedMikrotik)) {
                    $msg .= " Semua user juga berhasil dihapus dari Mikrotik.";
                } else {
                    $msg .= " Catatan: " . count($failedMikrotik) . " user gagal dihapus di Mikrotik.";
                }
            }

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal hapus massal: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Get customer top-up history
     */
    public function getTopupHistory($id)
    {
        $customer = \App\Models\Customer::findOrFail($id);

        $topups = \App\Models\BalanceTopup::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($topup) {
                return [
                    'id' => $topup->id,
                    'amount' => (float) $topup->amount,
                    'amount_formatted' => 'Rp ' . number_format($topup->amount, 0, ',', '.'),
                    'balance_before' => (float) $topup->balance_before,
                    'balance_after' => (float) $topup->balance_after,
                    'notes' => $topup->notes,
                    'date' => \Carbon\Carbon::parse($topup->created_at)->locale('id')->isoFormat('DD MMM YYYY, HH:mm'),
                ];
            });

        return response()->json([
            'customer_name' => $customer->name,
            'balance' => (float) $customer->balance,
            'balance_formatted' => 'Rp ' . number_format($customer->balance, 0, ',', '.'),
            'topups' => $topups,
        ]);
    }

    /**
     * AJAX: Update a top-up record
     */
    public function updateTopup(Request $request, $topupId)
    {
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json(['status' => false, 'message' => 'Tidak memiliki akses.'], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $topup = \App\Models\BalanceTopup::findOrFail($topupId);
        $customer = \App\Models\Customer::findOrFail($topup->customer_id);

        // Calculate balance adjustment: reverse old, apply new
        $oldAmount = (float) $topup->amount;
        $newAmount = (float) $request->amount;
        $diff = $newAmount - $oldAmount;

        $customer->balance += $diff;
        $customer->save();

        $topup->update([
            'amount' => $newAmount,
            'balance_after' => (float) $topup->balance_after + $diff,
            'notes' => $request->input('notes', $topup->notes),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Top up berhasil diperbarui.',
            'new_balance' => (float) $customer->balance,
            'new_balance_formatted' => 'Rp ' . number_format($customer->balance, 0, ',', '.'),
        ]);
    }

    /**
     * AJAX: Delete a top-up record
     */
    public function deleteTopup($topupId)
    {
        $user = auth()->user();
        if (!$user->isAdmin() && !$user->isSuperAdmin()) {
            return response()->json(['status' => false, 'message' => 'Tidak memiliki akses.'], 403);
        }

        $topup = \App\Models\BalanceTopup::findOrFail($topupId);
        $customer = \App\Models\Customer::findOrFail($topup->customer_id);

        // Reverse the balance
        $customer->balance -= (float) $topup->amount;
        if ($customer->balance < 0) $customer->balance = 0;
        $customer->save();

        $topup->delete();

        return response()->json([
            'status' => true,
            'message' => 'Data top up berhasil dihapus. Saldo disesuaikan.',
            'new_balance' => (float) $customer->balance,
            'new_balance_formatted' => 'Rp ' . number_format($customer->balance, 0, ',', '.'),
        ]);
    }

    /**
     * Cetak Kartu Informasi Router
     */
    public function printCard(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $company = Company::first() ?? new Company();

        $data = [
            'customer' => $customer,
            'company' => $company
        ];

        if ($request->type == 'pdf') {
            // Ukuran 12cm x 8cm dalam point (1cm = 28.346 pt)
            // 12 * 28.346 = 340.152
            // 8 * 28.346 = 226.768
            $customPaper = array(0, 0, 340.152, 226.768);
            
            $pdf = Pdf::loadView('customers.card', $data)->setPaper($customPaper, 'landscape');
            return $pdf->stream('kartu-router-' . $customer->internet_number . '.pdf');
        } elseif ($request->type == 'jpg') {
            return view('customers.card-jpg', $data);
        }

        return back()->with('error', 'Format cetak tidak valid.');
    }

    /**
     * Cetak Semua Kartu Informasi Router
     */
    public function printAllCards(Request $request)
    {
        $user = auth()->user();
        
        // Sesuaikan query pengambilan pelanggan berdasarkan role (mirip dengan index)
        $query = Customer::query();
        if ($user->role == 'operator') {
            $query->where('operator_id', $user->id);
        } elseif ($user->isSuperAdmin() && $request->has('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        $customers = $query->orderBy('name', 'asc')->get();
        $company = Company::first() ?? new Company();

        if ($customers->isEmpty()) {
            return back()->with('error', 'Tidak ada data pelanggan untuk dicetak.');
        }

        $data = [
            'customers' => $customers,
            'company' => $company
        ];

        if ($request->type == 'pdf') {
            // Ukuran Folio / F4: 8.5 x 13 inchi (612 x 936 point)
            $folioPaper = array(0, 0, 612, 936);
            
            $pdf = Pdf::loadView('customers.print-all-pdf', $data)->setPaper($folioPaper, 'landscape');
            return $pdf->stream('Semua-Kartu-Router.pdf');
        } elseif ($request->type == 'jpg') {
            $data['customers_json'] = $customers->map(function($c) {
                return [
                    'inet' => $c->internet_number,
                    'name' => strtoupper($c->name ?? ''),
                    'filename' => preg_replace('/[^A-Za-z0-9\-]/', '_', $c->name ?? 'Unknown')
                ];
            });
            return view('customers.print-all-jpg', $data);
        }

        return back()->with('error', 'Format cetak tidak valid.');
    }
}
