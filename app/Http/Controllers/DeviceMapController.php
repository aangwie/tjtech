<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceRelation;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class DeviceMapController extends Controller
{
    protected $mikrotik;

    public function __construct(MikrotikService $mikrotik)
    {
        $this->mikrotik = $mikrotik;
    }

    public function index()
    {
        // Get all devices that have coordinates and load customer
        $devices = Device::with('customer')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
            
        // Get active users from Mikrotik
        $onlineUsers = collect([]);
        try {
            if ($this->mikrotik->isConnected()) {
                $actives = $this->mikrotik->getActiveUsers();
                $onlineUsers = collect($actives)->pluck('name')->flip();
            }
        } catch (\Exception $e) {
            // Ignore error
        }
        
        // Append online status to devices
        $devices->transform(function($device) use ($onlineUsers) {
            if (in_array($device->kategori, ['Router', 'HTB']) && $device->customer) {
                $device->is_online = $onlineUsers->has($device->customer->pppoe_username);
            } else {
                $device->is_online = null; // null for non-routers
            }
            return $device;
        });
            
        // Get all relations to draw lines
        $relations = DeviceRelation::with(['source', 'target'])->get();

        return view('devices.map.index', compact('devices', 'relations'));
    }
}
