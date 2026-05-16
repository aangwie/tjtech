<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceRelation;
use Illuminate\Http\Request;

class DeviceRelationController extends Controller
{
    public function index()
    {
        // Get all source relations grouped by source_id
        $relationsGrouped = DeviceRelation::with(['source', 'target'])
            ->get()
            ->groupBy('source_id');

        $devices = Device::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        return view('devices.relations.index', compact('relationsGrouped', 'devices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'source_id' => 'required|exists:devices,id',
            'target_ids' => 'required|array',
            'target_ids.*' => 'exists:devices,id'
        ]);

        $source_id = $request->source_id;
        $target_ids = $request->target_ids;

        // Ensure source_id is not in target_ids
        if (in_array($source_id, $target_ids)) {
            return back()->withErrors(['target_ids' => 'Perangkat asal tidak boleh menjadi perangkat tujuan.']);
        }

        foreach ($target_ids as $target_id) {
            DeviceRelation::firstOrCreate([
                'source_id' => $source_id,
                'target_id' => $target_id
            ]);
        }

        return back()->with('success', 'Relasi perangkat berhasil ditambahkan.');
    }

    public function update(Request $request, $source_id)
    {
        $request->validate([
            'target_ids' => 'nullable|array',
            'target_ids.*' => 'exists:devices,id'
        ]);

        $target_ids = $request->target_ids ?? [];

        // Ensure source_id is not in target_ids
        if (in_array($source_id, $target_ids)) {
            return back()->withErrors(['target_ids' => 'Perangkat asal tidak boleh menjadi perangkat tujuan.']);
        }

        // Delete existing relations for this source
        DeviceRelation::where('source_id', $source_id)->delete();

        // Create new relations
        foreach ($target_ids as $target_id) {
            DeviceRelation::create([
                'source_id' => $source_id,
                'target_id' => $target_id
            ]);
        }

        return back()->with('success', 'Relasi perangkat berhasil diperbarui.');
    }

    public function destroy($source_id)
    {
        DeviceRelation::where('source_id', $source_id)->delete();
        return back()->with('success', 'Relasi perangkat berhasil dihapus.');
    }
}
