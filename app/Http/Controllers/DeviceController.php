<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $devices = Device::latest()->get();
        return view('devices.index', compact('devices'));
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
            'keterangan' => 'nullable|string',
            'rasio' => 'nullable|string|max:50',
            'redaman' => 'nullable|string|max:50',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:500' // max 500kb
        ]);

        $data = $request->except('foto');

        if ($request->hasFile('foto')) {
            $data['foto'] = $this->handlePhotoUpload($request->file('foto'));
        }

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
            'keterangan' => 'nullable|string',
            'rasio' => 'nullable|string|max:50',
            'redaman' => 'nullable|string|max:50',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:500'
        ]);

        $data = $request->except('foto');

        if ($request->hasFile('foto')) {
            $data['foto'] = $this->handlePhotoUpload($request->file('foto'), $device->foto);
        }

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
