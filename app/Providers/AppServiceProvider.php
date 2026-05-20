<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use App\Models\Company;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use App\Listeners\LogSuccessfulLogin;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (!app()->runningInConsole()) {
            try {
                $company = \Illuminate\Support\Facades\Cache::rememberForever('global_company', function () {
                    if (Schema::hasColumn('companies', 'admin_id')) {
                        $c = Company::whereHas('admin', function ($q) {
                            $q->where('role', 'superadmin');
                        })->first();
                        if ($c) return $c;
                    }
                    return Company::first();
                });
                
                $faviconUrl = ($company && $company->logo_path)
                    ? asset('uploads/' . $company->logo_path)
                    : asset('favicon.ico');
                
                View::share('global_favicon', $faviconUrl);
                View::share('company', $company);
            } catch (\Exception $e) {
                View::share('global_favicon', asset('favicon.ico'));
                View::share('company', null);
            }

            try {
                $setting = \Illuminate\Support\Facades\Cache::rememberForever('global_site_setting', function () {
                    return SiteSetting::first();
                });

                if ($setting) {
                    if ($setting->connection_mode === 'https' && !app()->isLocal()) {
                        URL::forceScheme('https');
                    } elseif ($setting->connection_mode === 'http') {
                        URL::forceScheme('http');
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors (e.g. table not found during migration)
            }
        }

        // Register Login Listener
        Event::listen(
            Login::class,
            LogSuccessfulLogin::class
        );
    }
}