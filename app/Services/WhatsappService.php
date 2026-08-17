<?php

namespace App\Services;

use App\Models\WhatsappSetting;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    /**
     * Helper to check if a WhatsappSetting model instance is properly configured
     */
    private static function isSettingConfigured($setting)
    {
        if (!$setting) {
            return false;
        }

        if ($setting->wa_provider === 'gateway') {
            return !empty($setting->wa_gateway_url);
        }

        // Default: 'api' provider
        return !empty($setting->api_key) && !empty($setting->target_url);
    }

    /**
     * Helper to get the best valid WhatsappSetting
     */
    private static function resolveSetting($adminId = null)
    {
        // 1. Coba ambil berdasarkan admin_id pelanggan (jika ada)
        if ($adminId) {
            $setting = WhatsappSetting::withoutGlobalScopes()->where('admin_id', $adminId)->first();
            if (self::isSettingConfigured($setting)) {
                return $setting;
            }
        }

        // 2. Coba ambil berdasarkan tenant scope user yang sedang login
        $setting = WhatsappSetting::first();
        if (self::isSettingConfigured($setting)) {
            return $setting;
        }

        // 3. Fallback: Cari setting manapun di database yang sudah memiliki konfigurasi valid
        $setting = WhatsappSetting::withoutGlobalScopes()
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('wa_provider', 'gateway')
                        ->whereNotNull('wa_gateway_url')
                        ->where('wa_gateway_url', '!=', '');
                })->orWhere(function ($sub) {
                    $sub->where(function ($p) {
                        $p->where('wa_provider', 'api')
                          ->orWhereNull('wa_provider');
                    })
                    ->whereNotNull('api_key')
                    ->where('api_key', '!=', '')
                    ->whereNotNull('target_url')
                    ->where('target_url', '!=', '');
                });
            })
            ->first();

        if (self::isSettingConfigured($setting)) {
            return $setting;
        }

        // 4. Terakhir: kembalikan record WhatsappSetting manapun jika ada
        return WhatsappSetting::withoutGlobalScopes()->first();
    }

    public static function send($targetNumber, $message, $adminId = null)
    {
        // 1. Ambil Pengaturan dari Database (Dukung fallback otomatis jika setting adminId belum terisi API Key)
        $setting = self::resolveSetting($adminId);

        if (!$setting) {
            return ['status' => false, 'message' => 'Pengaturan WhatsApp belum dikonfigurasi.'];
        }

        if (!self::isSettingConfigured($setting)) {
            if ($setting->wa_provider === 'gateway' && empty($setting->wa_gateway_url)) {
                return ['status' => false, 'message' => 'URL Gateway WhatsApp belum diisi pada pengaturan.'];
            }
            if (empty($setting->api_key)) {
                return ['status' => false, 'message' => 'API Key WhatsApp belum diisi pada pengaturan.'];
            }
            if (empty($setting->target_url)) {
                return ['status' => false, 'message' => 'Target URL API WhatsApp belum diisi pada pengaturan.'];
            }
        }

        // 2. Format Nomor (Pastikan 628...)
        // Hapus karakter non-digit (seperti +, -, space)
        $targetNumber = preg_replace('/[^0-9]/', '', (string)$targetNumber);

        if (empty($targetNumber)) {
            return ['status' => false, 'message' => 'Nomor HP tujuan kosong atau tidak valid.'];
        }

        if (substr($targetNumber, 0, 1) == '0') {
            $targetNumber = '62' . substr($targetNumber, 1);
        }

        // 3. Cek Provider: API atau Gateway
        if ($setting->wa_provider === 'gateway') {
            // KIRIM VIA SELF-HOSTED GATEWAY (BAILEYS)
            $baseUrl = $setting->wa_gateway_url ?? 'http://localhost:3000';
            $url = rtrim($baseUrl, '/') . '/send';
            $data = [
                'number' => $targetNumber,
                'message' => $message,
            ];

            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->post($url, [
                    'json' => $data,
                    'timeout' => 15,
                    'http_errors' => false
                ]);

                $body = $response->getBody()->getContents();
                $result = json_decode($body, true);

                if ($response->getStatusCode() == 200 && isset($result['status']) && $result['status']) {
                    return ['status' => true, 'response' => $body];
                } else {
                    return ['status' => false, 'message' => 'Gateway Error: ' . ($result['message'] ?? 'Unknown error')];
                }
            } catch (\Exception $e) {
                Log::error("WA Gateway Exception: " . $e->getMessage());
                return ['status' => false, 'message' => 'Gateway Exception: ' . $e->getMessage()];
            }
        }

        // --- KIRIM VIA API EXTERNAL (Provider Lama) ---
        $url = $setting->target_url;
        if (empty($url)) {
            return ['status' => false, 'message' => 'Target URL API WhatsApp belum diisi pada pengaturan.'];
        }

        $apiKey = $setting->api_key;
        $sender = $setting->sender_number;

        $data = [
            'api_key' => $apiKey,
            'nomor_pengirim' => $sender,
            'nomor_penerima' => $targetNumber,
            'pesan' => $message,
        ];

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($url, [
                'form_params' => $data,
                'timeout' => 10,
                'http_errors' => false
            ]);

            $body = $response->getBody()->getContents();
            Log::info("WA API Response: " . $body);

            if ($response->getStatusCode() == 200) {
                return ['status' => true, 'response' => $body];
            } else {
                return ['status' => false, 'message' => 'API Error (' . $response->getStatusCode() . '): ' . $body];
            }
        } catch (\Exception $e) {
            Log::error("WA API Exception: " . $e->getMessage());
            return ['status' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }
}