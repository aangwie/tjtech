<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    public function index()
    {
        $setting = SiteSetting::first();
        if (!$setting) {
            $setting = SiteSetting::create([
                'about_us' => 'Selamat datang di layanan kami.',
                'terms_conditions' => 'Syarat dan ketentuan berlaku.',
                'connection_mode' => 'auto'
            ]);
        }
        return view('superadmin.settings.site', compact('setting'));
    }

    public function update(Request $request)
    {
        $setting = SiteSetting::first();

        $request->validate([
            'turnstile_site_key' => 'nullable|string|max:255',
            'turnstile_secret_key' => 'nullable|string|max:255',
            'turnstile_enabled' => 'nullable|in:0,1,on,off,true,false',
        ]);

        $data = $request->only([
            'about_us',
            'terms_conditions',
            'connection_mode',
            'turnstile_site_key',
            'turnstile_secret_key',
        ]);

        // Handle boolean toggle
        $data['turnstile_enabled'] = $request->boolean('turnstile_enabled', false);

        $setting->update($data);

        return back()->with('success', 'Informasi situs berhasil diperbarui.');
    }
}
