<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'nama', 
        'kategori',
        'customer_id',
        'ip_address',
        'keterangan',
        'rasio',
        'redaman',
        'latitude',
        'longitude',
        'foto',
        'out_details'
    ];

    protected $casts = [
        'out_details' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sourceRelations()
    {
        return $this->hasMany(DeviceRelation::class, 'source_id');
    }

    public function targetRelations()
    {
        return $this->hasMany(DeviceRelation::class, 'target_id');
    }
}
