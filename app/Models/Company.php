<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;
use Illuminate\Support\Facades\Cache;

class Company extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected static function booted()
    {
        static::saved(function ($model) {
            Cache::forget('global_company');
        });

        static::deleted(function ($model) {
            Cache::forget('global_company');
        });
    }
}