<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDisposal;
use Illuminate\Http\Request;

class AssetDisposalController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Fetch assets for dropdown
        if ($user->isSuperAdmin()) {
            $assets = Asset::with('admin')->latest()->get();
        } else {
            $assets = Asset::with('admin')->where('admin_id', $user->id)->latest()->get();
        }

        // Fetch disposals with filters
        $query = AssetDisposal::with(['asset', 'admin'])->latest();
        
        if (!$user->isSuperAdmin()) {
            $query->where('admin_id', $user->id);
        }

        if ($request->filled('filter_alasan')) {
            $query->where('alasan', $request->filter_alasan);
        }

        if ($request->filled('filter_tahun') && $request->filter_alasan == 'Dijual') {
            $query->whereYear('tanggal_jual', $request->filter_tahun);
        }

        $disposals = $query->paginate(10)->withQueryString();

        // Get unique years for the filter (only for sales)
        $saleYearsQuery = AssetDisposal::where('alasan', 'Dijual');
        if (!$user->isSuperAdmin()) {
            $saleYearsQuery->where('admin_id', $user->id);
        }
        
        $saleYears = $saleYearsQuery->whereNotNull('tanggal_jual')
            ->selectRaw('YEAR(tanggal_jual) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        return view('asset.disposal', compact('assets', 'disposals', 'saleYears'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'jumlah_dihapus' => 'required|integer|min:1',
            'alasan' => 'required|in:Dijual,Rusak,Lainnya',
            'keterangan' => 'nullable|string',
            'tanggal_jual' => 'nullable|required_if:alasan,Dijual|date',
            'harga_jual' => 'nullable|required_if:alasan,Dijual|numeric|min:0',
        ]);

        $asset = Asset::findOrFail($validated['asset_id']);
        $user = auth()->user();

        if (!$user->isSuperAdmin() && $asset->admin_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        if ($validated['jumlah_dihapus'] > $asset->jumlah_barang) {
            return back()->with('error', 'Jumlah dihapus tidak boleh melebihi jumlah barang saat ini.');
        }

        // Create disposal record
        AssetDisposal::create([
            'asset_id' => $asset->id,
            'admin_id' => $asset->admin_id,
            'nama_barang' => $asset->nama_barang,
            'jumlah_dihapus' => $validated['jumlah_dihapus'],
            'alasan' => $validated['alasan'],
            'keterangan' => $validated['keterangan'] ?? null,
            'tanggal_jual' => $validated['alasan'] == 'Dijual' ? $validated['tanggal_jual'] : null,
            'harga_jual' => $validated['alasan'] == 'Dijual' ? $validated['harga_jual'] : null,
        ]);

        // Decrease asset quantity
        $asset->jumlah_barang -= $validated['jumlah_dihapus'];
        
        // If quantity becomes 0, delete the asset completely.
        if ($asset->jumlah_barang <= 0) {
            $asset->delete();
        } else {
            // Update harga_perolehan based on the new quantity (if applicable)
            // or we just save the new quantity
            $asset->harga_perolehan = $asset->jumlah_barang * $asset->harga_satuan;
            $asset->save();
        }

        return redirect()->route('asset.disposal.index')->with('success', 'Data penghapusan aset berhasil dicatat.');
    }
}
