<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class AssetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            $assets = Asset::with('admin')->latest()->get();
        } else {
            $assets = Asset::where('admin_id', $user->id)->latest()->get();
        }

        $total_quantity = $assets->sum('jumlah_barang');
        $total_value = $assets->sum('harga_perolehan');

        return view('asset.index', compact('assets', 'total_quantity', 'total_value'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_barang' => 'required|string|max:255',
            'merk' => 'required|string|max:255',
            'identifier' => 'nullable|string|max:255',
            'tahun_perolehan' => 'required|integer|min:1900|max:' . date('Y'),
            'kondisi_perolehan' => 'required|in:Baru,Bekas',
            'jumlah_barang' => 'required|integer|min:1',
            'harga_satuan' => 'required|numeric|min:0',
            'harga_perolehan' => 'required|numeric|min:0',
            'nilai_penyusutan' => 'nullable|numeric|min:0',
        ]);

        $validated['has_identifier'] = $request->has('has_identifier') ? 1 : 0;
        if (!$validated['has_identifier']) {
            $validated['identifier'] = null;
        }

        $validated['has_penyusutan'] = $request->has('has_penyusutan') ? 1 : 0;
        if (!$validated['has_penyusutan']) {
            $validated['nilai_penyusutan'] = null;
        }

        $validated['harga_perolehan'] = $validated['jumlah_barang'] * $validated['harga_satuan'];
        $validated['admin_id'] = auth()->id();

        Asset::create($validated);

        return redirect()->route('asset.index')->with('success', 'Data aset berhasil ditambahkan');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $asset = Asset::findOrFail($id);
        $user = auth()->user();

        // Authorization check
        if (!$user->isSuperAdmin() && $asset->admin_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'nama_barang' => 'required|string|max:255',
            'merk' => 'required|string|max:255',
            'identifier' => 'nullable|string|max:255',
            'tahun_perolehan' => 'required|integer|min:1900|max:' . date('Y'),
            'kondisi_perolehan' => 'required|in:Baru,Bekas',
            'jumlah_barang' => 'required|integer|min:1',
            'harga_satuan' => 'required|numeric|min:0',
            'harga_perolehan' => 'required|numeric|min:0',
            'nilai_penyusutan' => 'nullable|numeric|min:0',
        ]);

        $validated['has_identifier'] = $request->has('has_identifier') ? 1 : 0;
        if (!$validated['has_identifier']) {
            $validated['identifier'] = null;
        }

        $validated['has_penyusutan'] = $request->has('has_penyusutan') ? 1 : 0;
        if (!$validated['has_penyusutan']) {
            $validated['nilai_penyusutan'] = null;
        }

        $validated['harga_perolehan'] = $validated['jumlah_barang'] * $validated['harga_satuan'];

        $asset->update($validated);

        return redirect()->route('asset.index')->with('success', 'Data aset berhasil diperbarui');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $asset = Asset::findOrFail($id);
        $user = auth()->user();

        // Authorization check
        if (!$user->isSuperAdmin() && $asset->admin_id !== $user->id) {
            abort(403, 'Unauthorized action.');
        }

        $asset->delete();

        return redirect()->route('asset.index')->with('success', 'Data aset berhasil dihapus');
    }

    /**
     * Display report filters view.
     */
    public function report()
    {
        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            $years = Asset::select('tahun_perolehan')->distinct()->orderBy('tahun_perolehan', 'desc')->pluck('tahun_perolehan');
        } else {
            $years = Asset::where('admin_id', $user->id)->select('tahun_perolehan')->distinct()->orderBy('tahun_perolehan', 'desc')->pluck('tahun_perolehan');
        }
        
        return view('asset.report', compact('years'));
    }

    /**
     * Generate PDF report.
     */
    public function printReport(Request $request)
    {
        $request->validate([
            'tahun_perolehan' => 'nullable|integer',
            'hitung_penyusutan' => 'boolean'
        ]);

        $tahunFilter = $request->tahun_perolehan;
        $hitungPenyusutan = $request->has('hitung_penyusutan');

        $user = auth()->user();
        $query = Asset::with('admin')->latest();

        if (!$user->isSuperAdmin()) {
            $query->where('admin_id', $user->id);
        }

        if ($tahunFilter) {
            $query->where('tahun_perolehan', $tahunFilter);
        }

        $assets = $query->get();

        $currentYear = date('Y');

        $pdf = Pdf::loadView('asset.pdf', [
            'assets' => $assets,
            'tahunFilter' => $tahunFilter,
            'hitungPenyusutan' => $hitungPenyusutan,
            'currentYear' => $currentYear
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('laporan-aset-' . date('YmdHis') . '.pdf');
    }
}
