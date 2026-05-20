<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Customer;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $devices = Device::with('customer')->latest()->get();
        $customers = Customer::orderBy('name')->get();
        return view('devices.index', compact('devices', 'customers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'kategori' => 'required|string|in:ODP,Router,HTB,Lainnya',
            'customer_id' => 'nullable|exists:customers,id',
            'ip_address' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
            'rasio' => 'nullable|string|max:50',
            'redaman' => 'nullable|string|max:50',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:500' // max 500kb
        ]);

        $data = $request->except('foto', 'out_details', 'enable_out');

        if ($request->hasFile('foto')) {
            $data['foto'] = $this->handlePhotoUpload($request->file('foto'));
        }

        // Handle out details
        $outDetails = [];
        if ($request->has('enable_out') && $request->has('out_details')) {
            $outs = $request->input('out_details.out', []);
            $kets = $request->input('out_details.ket', []);
            
            foreach ($outs as $index => $outVal) {
                if (!empty($outVal) || !empty($kets[$index])) {
                    $outDetails[] = [
                        'out' => $outVal,
                        'ket' => $kets[$index] ?? ''
                    ];
                }
            }
        }
        $data['out_details'] = !empty($outDetails) ? $outDetails : null;

        Device::create($data);

        return redirect()->route('devices.index')->with('success', 'Perangkat berhasil ditambahkan');
    }

    /**
     * Display the specified resource.
     */
    public function show(Device $device)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Device $device)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Device $device)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'kategori' => 'required|string|in:ODP,Router,HTB,Lainnya',
            'customer_id' => 'nullable|exists:customers,id',
            'ip_address' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
            'rasio' => 'nullable|string|max:50',
            'redaman' => 'nullable|string|max:50',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:500'
        ]);

        $data = $request->except('foto', 'out_details', 'enable_out');

        if ($request->hasFile('foto')) {
            $data['foto'] = $this->handlePhotoUpload($request->file('foto'), $device->foto);
        }

        // Handle out details
        $outDetails = [];
        if ($request->has('enable_out') && $request->has('out_details')) {
            $outs = $request->input('out_details.out', []);
            $kets = $request->input('out_details.ket', []);
            
            foreach ($outs as $index => $outVal) {
                if (!empty($outVal) || !empty($kets[$index])) {
                    $outDetails[] = [
                        'out' => $outVal,
                        'ket' => $kets[$index] ?? ''
                    ];
                }
            }
        }
        $data['out_details'] = !empty($outDetails) ? $outDetails : null;

        $device->update($data);

        return redirect()->route('devices.index')->with('success', 'Perangkat berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Device $device)
    {
        if ($device->foto && \Illuminate\Support\Facades\Storage::disk('hosting')->exists($device->foto)) {
            \Illuminate\Support\Facades\Storage::disk('hosting')->delete($device->foto);
        }
        
        $device->delete();

        return redirect()->route('devices.index')->with('success', 'Perangkat berhasil dihapus');
    }

    /**
     * Get Customer IP via Mikrotik
     */
    public function getCustomerIp(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        
        try {
            $mikrotik = app(MikrotikService::class);
            if ($mikrotik->isConnected()) {
                // Try Active Connections first
                $activeUsers = collect($mikrotik->getActiveUsers());
                $active = $activeUsers->firstWhere('name', $customer->pppoe_username);
                
                if ($active && isset($active['address'])) {
                    return response()->json(['ip_address' => $active['address']]);
                }

                // If not active, check Secrets (remote-address might be assigned)
                $secrets = collect($mikrotik->getSecrets());
                $secret = $secrets->firstWhere('name', $customer->pppoe_username);
                
                if ($secret && isset($secret['remote-address'])) {
                    return response()->json(['ip_address' => $secret['remote-address']]);
                }
            }
        } catch (\Exception $e) {
            // Silently fail to return offline fallback
        }

        return response()->json(['ip_address' => '- (Offline/No IP)']);
    }

    /**
     * Convert and save image to WebP
     */
    private function handlePhotoUpload($file, $oldPath = null)
    {
        if ($oldPath && \Illuminate\Support\Facades\Storage::disk('hosting')->exists($oldPath)) {
            \Illuminate\Support\Facades\Storage::disk('hosting')->delete($oldPath);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $image = null;

        if ($extension === 'png') {
            $image = @imagecreatefrompng($file->getRealPath());
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
        } else {
            $image = @imagecreatefromjpeg($file->getRealPath());
        }

        if (!$image) {
            return $file->store('devices', 'hosting');
        }

        $filename = 'devices/' . uniqid() . time() . '.webp';
        
        // Dapatkan root folder untuk disk hosting
        $hostingDiskRoot = config('filesystems.disks.hosting.root');
        $fullPath = rtrim($hostingDiskRoot, '/') . '/' . $filename;
        
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        imagewebp($image, $fullPath, 80);
        imagedestroy($image);

        return $filename;
    }
}
