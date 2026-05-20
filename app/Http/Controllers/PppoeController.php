<?php

namespace App\Http\Controllers;

use App\Services\MikrotikService;
use App\Models\RouterSetting; // 1. Import Model RouterSetting
use App\Models\SiteSetting;
use App\Models\Customer;
use Illuminate\Http\Request;

class PppoeController extends Controller
{
    protected $mikrotik;

    public function __construct(MikrotikService $mikrotikService)
    {
        $this->mikrotik = $mikrotikService;
    }

    public function index()
    {
        // 2. Ambil Info Router dari Database
        $routerInfo = RouterSetting::where('is_active', true)->first();
        // Fallback: Jika tidak ada yang aktif (misal baru install), ambil yang pertama
        if (!$routerInfo) {
            $routerInfo = RouterSetting::first();
        }

        $siteSetting = SiteSetting::first();
        if (!$siteSetting) {
            $siteSetting = SiteSetting::create([
                'about_us' => 'Selamat datang di layanan kami.',
                'terms_conditions' => 'Syarat dan ketentuan berlaku.',
                'connection_mode' => 'auto'
            ]);
        }
        
        // 3. Cek Status Koneksi
        $isConnected = $this->mikrotik->isConnected();

        // Scope queries based on roles
        $user = auth()->user();
        $customerQuery = Customer::query();

        if ($user->role === 'operator') {
            $customerQuery->where('operator_id', $user->id);
        } elseif ($user->role === 'admin') {
            $customerQuery->where('admin_id', $user->id);
        }

        // Jika belum ada settingan sama sekali
        if (!$routerInfo) {
            return view('pppoe.index', [
                'siteSetting' => $siteSetting,
                'error' => 'Konfigurasi Router belum diatur. Silakan ke menu Pengaturan -> Konfigurasi Mikrotik.'
            ]);
        }

        // Jika setting ada tapi Gagal Konek
        if (!$isConnected) {
            $totalUser = (clone $customerQuery)->count();
            $onlineUser = (clone $customerQuery)->where('is_active', true)->count();
            $offlineUser = $totalUser - $onlineUser;

            return view('pppoe.index', [
                'routerInfo' => $routerInfo,
                'isConnected' => false,
                'secrets' => [],
                'actives' => collect([]),
                'totalUser' => $totalUser,
                'onlineUser' => $onlineUser,
                'offlineUser' => $offlineUser,
                'siteSetting' => $siteSetting,
                'error' => "Gagal terhubung ke Mikrotik ({$routerInfo->host}:{$routerInfo->port}). Cek koneksi/VPN."
            ]);
        }

        try {
            // Ambil data dari Mikrotik
            $activeUsers = $this->mikrotik->getActiveUsers();
            $secrets = $this->mikrotik->getSecrets();

            $myCustomerUsernames = (clone $customerQuery)->pluck('pppoe_username')->filter()->toArray();

            // Filter secrets & actives to only show users present in local DB (MySQL)
            $secrets = array_values(array_filter($secrets, function ($s) use ($myCustomerUsernames) {
                return is_array($s) && isset($s['name']) && in_array($s['name'], $myCustomerUsernames);
            }));
            $activeUsers = array_values(array_filter($activeUsers, function ($a) use ($myCustomerUsernames) {
                return is_array($a) && isset($a['name']) && in_array($a['name'], $myCustomerUsernames);
            }));

            // Mapping data active user
            $activeCollection = collect($activeUsers)->keyBy('name');

            // Calculate card metrics exactly like dashboard
            $totalUser = (clone $customerQuery)->count();
            $onlineUser = count($activeUsers);
            $offlineUser = $totalUser - $onlineUser;

            return view('pppoe.index', [
                'routerInfo' => $routerInfo, // Kirim data router ke view
                'isConnected' => true,       // Kirim status koneksi
                'secrets' => $secrets,
                'actives' => $activeCollection,
                'totalUser' => $totalUser,
                'onlineUser' => $onlineUser,
                'offlineUser' => $offlineUser,
                'siteSetting' => $siteSetting,
                'error' => null
            ]);

        } catch (\Exception $e) {
            $totalUser = (clone $customerQuery)->count();
            $onlineUser = (clone $customerQuery)->where('is_active', true)->count();
            $offlineUser = $totalUser - $onlineUser;

            return view('pppoe.index', [
                'routerInfo' => $routerInfo,
                'isConnected' => false,
                'secrets' => [],
                'actives' => collect([]),
                'totalUser' => $totalUser,
                'onlineUser' => $onlineUser,
                'offlineUser' => $offlineUser,
                'siteSetting' => $siteSetting,
                'error' => 'Terjadi kesalahan: ' . $e->getMessage()
            ]);
        }
    }

    // ... method kick & toggle biarkan tetap sama ...
    public function kick(Request $request)
    {
        // ... kode lama ...
        $request->validate(['username' => 'required']);
        try {
            $status = $this->mikrotik->kickUser($request->username);
            if ($status) return back()->with('success', "User {$request->username} berhasil diputus (Kick).");
            else return back()->with('warning', "User {$request->username} tidak sedang online.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal melakukan kick: ' . $e->getMessage());
        }
    }

    public function toggle(Request $request)
    {
        // ... kode lama ...
        $request->validate(['username' => 'required', 'action' => 'required|in:enable,disable']);
        try {
            if ($request->action === 'disable') {
                $this->mikrotik->setSecretStatus($request->username, 'disabled');
                $this->mikrotik->kickUser($request->username);
                $msg = "User {$request->username} dinonaktifkan & dikick.";
            } else {
                $this->mikrotik->setSecretStatus($request->username, 'enabled');
                $msg = "User {$request->username} diaktifkan.";
            }
            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal: ' . $e->getMessage());
        }
    }
}