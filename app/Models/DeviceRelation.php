<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceRelation extends Model
{
    protected $fillable = ['source_id', 'target_id'];

    public function source()
    {
        return $this->belongsTo(Device::class, 'source_id');
    }

    public function target()
    {
        return $this->belongsTo(Device::class, 'target_id');
    }
}
