<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceRelation;
use Illuminate\Http\Request;

class DeviceMapController extends Controller
{
    public function index()
    {
        // Get all devices that have coordinates
        $devices = Device::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
            
        // Get all relations to draw lines
        $relations = DeviceRelation::with(['source', 'target'])->get();

        return view('devices.map.index', compact('devices', 'relations'));
    }
}
