<?php

namespace App\Services;

use RouterOS\Client;
use RouterOS\Query;
use RouterOS\Exceptions\ConnectException;
use RouterOS\Exceptions\ClientException;
use App\Models\RouterSetting;
use Illuminate\Support\Facades\Cache;

class MikrotikService
{
    protected $client = null;
    protected $config = null;
    protected $connectionAttempted = false;

    public function __construct()
    {
        // AMBIL YANG STATUSNYA AKTIF
        $this->config = RouterSetting::where('is_active', true)->first();

        // Jika tidak ada yang aktif, ambil yang pertama saja (fallback)
        if (!$this->config) {
            $this->config = RouterSetting::first();
        }
    }

    protected function connect()
    {
        if ($this->connectionAttempted) {
            return;
        }
        $this->connectionAttempted = true;

        if (!$this->config) {
            $this->client = null;
            return;
        }

        // If cache says router is offline, abort immediately to prevent timeout hanging
        if (!$this->isConnected()) {
            $this->client = null;
            return;
        }

        try {
            $this->client = new Client([
                'host' => $this->config->host,
                'user' => $this->config->username,
                'pass' => $this->config->password,
                'port' => (int) $this->config->port,
                'timeout' => 2, // Reduced timeout (2s instead of 10s)
            ]);
        } catch (\Throwable $e) {
            $this->client = null;
        }
    }

    // Cek status koneksi (dengan Caching)
    public function isConnected()
    {
        if (!$this->config) {
            return false;
        }

        $cacheKey = 'mikrotik_status_' . $this->config->id;

        return Cache::remember($cacheKey, 30, function () {
            try {
                $client = new Client([
                    'host' => $this->config->host,
                    'user' => $this->config->username,
                    'pass' => $this->config->password,
                    'port' => (int) $this->config->port,
                    'timeout' => 2, // Fast check timeout
                ]);
                return $client !== null;
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    public function getClient()
    {
        $this->connect();
        return $this->client;
    }

    // Ambil daftar user yang sedang Online (Active)
    public function getActiveUsers()
    {
        $this->connect();
        if (!$this->client) {
            return [];
        }

        try {
            // /ppp/active/print
            $query = new Query('/ppp/active/print');
            return $this->client->query($query)->read();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // Ambil daftar semua user terdaftar (Secret)
    public function getSecrets()
    {
        $this->connect();
        if (!$this->client) {
            return [];
        }

        try {
            // /ppp/secret/print
            $query = new Query('/ppp/secret/print');
            return $this->client->query($query)->read();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // Logic untuk memutus koneksi user
    public function kickUser($username)
    {
        $this->connect();
        if (!$this->client) {
            return false;
        }

        try {
            // 1. Cari ID koneksi aktif berdasarkan nama user
            $queryFind = (new Query('/ppp/active/print'))
                ->where('name', $username);

            $activeUser = $this->client->query($queryFind)->read();

            // Jika user ditemukan sedang online
            if (!empty($activeUser)) {
                // Ambil .id (contoh: *1A)
                $id = $activeUser[0]['.id'];

                // 2. Eksekusi perintah remove
                $queryKick = (new Query('/ppp/active/remove'))
                    ->equal('.id', $id);

                $this->client->query($queryKick)->read();
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false; // User tidak sedang online
    }

    // Fungsi untuk Mengubah Status Secret (Enable/Disable)
    public function setSecretStatus($username, $status = 'disabled') // status: 'disabled' atau 'enabled'
    {
        $this->connect();
        if (!$this->client) {
            return false;
        }

        try {
            // 1. Cari ID Secret berdasarkan username
            $queryFind = (new Query('/ppp/secret/print'))
                ->where('name', $username);
            $secret = $this->client->query($queryFind)->read();

            if (!empty($secret)) {
                $id = $secret[0]['.id'];
                $value = ($status === 'disabled') ? 'yes' : 'no';

                // 2. Set disabled=yes/no
                $querySet = (new Query('/ppp/secret/set'))
                    ->equal('.id', $id)
                    ->equal('disabled', $value);

                $this->client->query($querySet)->read();
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    // Ambil daftar Profile PPPoE (untuk Dropdown) - Caching
    public function getProfiles()
    {
        if (!$this->config) {
            return [];
        }

        $cacheKey = 'mikrotik_profiles_' . $this->config->id;

        // Check if profiles are cached
        $cached = Cache::get($cacheKey);
        if ($cached !== null && !empty($cached)) {
            return $cached;
        }

        $this->connect();
        if (!$this->client) {
            return [];
        }

        try {
            $query = new Query('/ppp/profile/print');
            $profiles = $this->client->query($query)->read();
            if (!empty($profiles)) {
                Cache::put($cacheKey, $profiles, 600); // Cache for 10 minutes
            }
            return $profiles;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // Tambah User Baru ke Mikrotik
    public function addSecret($data)
    {
        $this->connect();
        if (!$this->client) {
            return false;
        }

        try {
            $query = (new Query('/ppp/secret/add'))
                ->equal('name', $data['username'])
                ->equal('password', $data['password'])
                ->equal('service', 'pppoe')
                ->equal('profile', $data['profile'])
                ->equal('comment', $data['comment'] ?? '');

            $this->client->query($query)->read();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // Hapus User dari Mikrotik
    public function removeSecret($username)
    {
        $this->connect();
        if (!$this->client) {
            return false;
        }

        try {
            // Cari ID dulu
            $queryFind = (new Query('/ppp/secret/print'))->where('name', $username);
            $user = $this->client->query($queryFind)->read();

            if (!empty($user)) {
                $id = $user[0]['.id'];
                $queryRemove = (new Query('/ppp/secret/remove'))->equal('.id', $id);
                $this->client->query($queryRemove)->read();
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    // Ambil Daftar Interface (Ethernet/VLAN/Bridge/dll)
    public function getInterfaces()
    {
        $this->connect();
        if (!$this->client) {
            return [];
        }

        try {
            $query = new Query('/interface/print');
            return $this->client->query($query)->read();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // Ambil Traffic Realtime (Monitor Traffic)
    public function getTraffic($interfaceName)
    {
        $this->connect();
        if (!$this->client) {
            return ['rx' => 0, 'tx' => 0];
        }

        try {
            // Perintah monitor-traffic dengan argumen 'once' agar tidak streaming
            $query = (new Query('/interface/monitor-traffic'))
                ->equal('interface', $interfaceName)
                ->equal('once');

            $result = $this->client->query($query)->read();

            if (!empty($result)) {
                return [
                    'rx' => isset($result[0]['rx-bits-per-second']) ? $result[0]['rx-bits-per-second'] : 0,
                    'tx' => isset($result[0]['tx-bits-per-second']) ? $result[0]['tx-bits-per-second'] : 0,
                ];
            }
        } catch (\Throwable $e) {
            return ['rx' => 0, 'tx' => 0];
        }

        return ['rx' => 0, 'tx' => 0];
    }

    // Update Data Secret (Misal ganti Profile atau Password)
    public function updateSecret($username, $data)
    {
        $this->connect();
        if (!$this->client) {
            return false;
        }

        try {
            // 1. Cari ID Secret berdasarkan Username
            $queryFind = (new Query('/ppp/secret/print'))->where('name', $username);
            $user = $this->client->query($queryFind)->read();

            if (empty($user)) {
                return false;
            }

            $id = $user[0]['.id'];

            // 2. Lakukan Update (Set)
            $queryUpdate = (new Query('/ppp/secret/set'))->equal('.id', $id);

            foreach ($data as $key => $value) {
                $queryUpdate->equal($key, $value);
            }

            $this->client->query($queryUpdate)->read();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // --- MONITOR METHODS ---

    // Ambil daftar user Hotspot yang sedang Online (Active)
    public function getHotspotActive()
    {
        $this->connect();
        if (!$this->client) {
            return [];
        }

        try {
            $query = new Query('/ip/hotspot/active/print');
            return $this->client->query($query)->read();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // Ambil daftar semua user Hotspot terdaftar
    public function getHotspotUsers()
    {
        $this->connect();
        if (!$this->client) {
            return [];
        }

        try {
            $query = new Query('/ip/hotspot/user/print');
            return $this->client->query($query)->read();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // Ambil daftar DHCP Leases
    public function getDhcpLeases()
    {
        $this->connect();
        if (!$this->client) {
            return [];
        }

        try {
            $query = new Query('/ip/dhcp-server/lease/print');
            return $this->client->query($query)->read();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // Ambil daftar Simple Queues
    public function getSimpleQueues()
    {
        $this->connect();
        if (!$this->client) {
            return [];
        }

        try {
            $query = new Query('/queue/simple/print');
            return $this->client->query($query)->read();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // --- HOTSPOT MANAGEMENT ---

    public function getHotspotProfiles()
    {
        $this->connect();
        if (!$this->client) {
            return [];
        }

        try {
            $query = new Query('/ip/hotspot/user/profile/print');
            return $this->client->query($query)->read();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // Ambil daftar hotspot servers
    public function getHotspotServers()
    {
        $this->connect();
        if (!$this->client) {
            return [];
        }

        try {
            $query = (new Query('/ip/hotspot/print'));
            return $this->client->query($query)->read();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function addHotspotUser($data)
    {
        $this->connect();
        if (!$this->client) {
            return false;
        }

        try {
            $query = (new Query('/ip/hotspot/user/add'))
                ->equal('name', $data['name'])
                ->equal('password', $data['password'])
                ->equal('profile', $data['profile'])
                ->equal('limit-uptime', $data['limit_uptime'] ?? '0')
                ->equal('comment', $data['comment'] ?? '');

            $this->client->query($query)->read();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function removeHotspotUser($name)
    {
        $this->connect();
        if (!$this->client) {
            return false;
        }

        try {
            $queryFind = (new Query('/ip/hotspot/user/print'))->where('name', $name);
            $user = $this->client->query($queryFind)->read();

            if (!empty($user)) {
                $id = $user[0]['.id'];
                $queryRemove = (new Query('/ip/hotspot/user/remove'))->equal('.id', $id);
                $this->client->query($queryRemove)->read();
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }
}
