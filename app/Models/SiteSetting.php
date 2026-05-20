<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::saved(function ($model) {
            Cache::forget('global_site_setting');
        });

        static::deleted(function ($model) {
            Cache::forget('global_site_setting');
        });
    }
}
