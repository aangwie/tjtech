<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouterConnectionLog extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function routerSetting()
    {
        return $this->belongsTo(RouterSetting::class, 'router_setting_id');
    }
}
