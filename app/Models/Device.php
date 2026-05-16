<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'nama', 
        'keterangan',
        'rasio',
        'redaman',
        'latitude',
        'longitude',
        'foto'
    ];

    public function sourceRelations()
    {
        return $this->hasMany(DeviceRelation::class, 'source_id');
    }

    public function targetRelations()
    {
        return $this->hasMany(DeviceRelation::class, 'target_id');
    }
}
