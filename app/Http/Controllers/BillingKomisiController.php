<?php

namespace App\Http\Controllers;

use App\Models\BillingPayment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TabelKomisi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillingKomisiController extends Controller
{
    /**
     * Simpan komisi operator untuk periode (month+year)
     * Body:
     *  - operator_id
     *  - month
     *  - year
     *  - komisi_percent (0..100)
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'operator_id' => 'required|exists:users,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'komisi_percent' => 'required|numeric|min:0|max:100',
        ]);

        $operator = User::findOrFail($data['operator_id']);

        // Permission check untuk role admin: operator harus berada di bawah parent_id admin tsb
        if ($user->role === 'admin') {
            if ((int) $operator->parent_id !== (int) $user->id) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            }
        }

        $month = (int) $data['month'];
        $year = (int) $data['year'];
        $komisiPercent = (float) $data['komisi_percent'];

        // Hitung tagihan lunas untuk operator pada periode tsb (manual only, sama seperti rekap)
        $invoices = Invoice::with('customer')
            ->whereMonth('due_date', $month)
            ->whereYear('due_date', $year)
            ->whereHas('customer', function ($q) use ($operator) {
                $q->where('operator_id', $operator->id);
            })
            ->get();

        $invoiceIds = $invoices->pluck('id')->toArray();

        $tagihanLunas = BillingPayment::whereIn('invoice_id', $invoiceIds)
            ->where('method', 'manual')
            ->sum('amount');

        $komisiValue = round(($komisiPercent / 100) * (float) $tagihanLunas);

        TabelKomisi::updateOrCreate(
            [
                'operator_id' => $operator->id,
                'month' => $month,
                'year' => $year,
            ],
            [
                'komisi_percent' => $komisiPercent,
                'komisi_value' => $komisiValue,
            ]
        );

        return response()->json(['status' => 'success', 'message' => 'Komisi tersimpan.', 'komisi_value' => $komisiValue]);
    }
}

