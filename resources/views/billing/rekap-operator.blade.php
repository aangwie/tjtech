@extends('layouts.app2')

@section('title', 'Rekap Operator')
@section('header', 'Rekap Operator')
@section('subheader', 'Ringkasan tagihan per operator.')

@section('content')

    <div>

        <!-- Filter Bar -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="bg-indigo-50 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 p-2 rounded-lg">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Rekap Operator</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Ringkasan tagihan per operator</p>
                </div>
            </div>
            <form action="{{ route('billing.rekapOperator') }}" method="GET"
                class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <select name="month"
                    class="block w-full sm:w-40 rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6">
                    @for ($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}" {{ $month == $i ? 'selected' : '' }}>
                            {{ DateTime::createFromFormat('!m', $i)->format('F') }}
                        </option>
                    @endfor
                </select>
                <select name="year"
                    class="block w-full sm:w-32 rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6">
                    @for ($y = date('Y'); $y >= 2023; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
                <button type="submit"
                    class="inline-flex justify-center items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 transition-all">
                    <i class="fas fa-search mr-2"></i> Tampilkan
                </button>
            </form>
        </div>

        <!-- Period Label -->
        <div class="mb-4">
            <h4 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                <i class="fas fa-calendar-alt mr-1"></i>
                Periode: {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}
            </h4>
        </div>

        <!-- Table Card -->
        <div
            class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm ring-1 ring-slate-900/5 dark:ring-slate-700/50 overflow-hidden">
            <div class="overflow-x-auto p-4">
                <table id="tableRekapOperator" class="w-full text-left border-collapse">
                    <thead>
                        <tr>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 rounded-l-lg">
                                No</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                                Nama Operator</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                                Total Tagihan</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                                Tagihan Lunas</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                                Sisa Tagihan</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 rounded-r-lg text-center">
                                Komisi (%)</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 text-center">
                                Komisi (Rp.)</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 text-center">
                                Tagihan Bersih</th>

                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @forelse($operatorData as $index => $data)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
                                <td class="px-4 py-3 align-middle text-sm text-slate-600 dark:text-slate-300">
                                    {{ $index + 1 }}
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        {{ $data['name'] }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-middle font-medium text-slate-700 dark:text-slate-200">
                                    Rp {{ number_format($data['total_tagihan'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-medium text-green-600 dark:text-green-400">
                                        Rp {{ number_format($data['tagihan_lunas'], 0, ',', '.') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-medium text-orange-600 dark:text-orange-400">
                                        Rp {{ number_format($data['sisa_tagihan'], 0, ',', '.') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-middle text-center">
                                    @php
                                        $komisiPercent = isset($data['komisi_percent']) ? (float) $data['komisi_percent'] : 0;
                                        $isAdminCanEdit = in_array(auth()->user()->role, ['admin','superadmin']);
                                    @endphp

                                    @if($isAdminCanEdit)
                                        <div class="flex items-center justify-center gap-2">
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="1"
                                                class="w-24 px-2 py-1 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/20 text-center"
                                                value="{{ $komisiPercent }}"
                                                onchange="saveKomisi({{ (int)$data['id'] }}, {{ (int)$month }}, {{ (int)$year }}, this.value)"
                                            >
                                            <span class="text-xs text-slate-500">%</span>
                                        </div>
                                    @else
                                        <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $komisiPercent }}%</span>
                                    @endif
                                </td>

                                @php
                                    $komisiValue = isset($data['komisi_value']) ? (float) $data['komisi_value'] : ((($komisiPercent/100) * (float)$data['tagihan_lunas']));
                                    $tagihanBersih = (float) $komisiValue;
                                @endphp
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-medium text-emerald-600 dark:text-emerald-400">
                                        Rp {{ number_format($tagihanBersih, 0, ',', '.') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-medium text-orange-600 dark:text-orange-400">
                                        Rp {{ number_format($data['tagihan_lunas'] - $komisiValue, 0, ',', '.') }}
                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Tidak ada operator ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($operatorData) > 0)
                        <tfoot class="bg-slate-50 dark:bg-slate-700/50">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-right font-bold text-slate-800 dark:text-white">
                                    Total Semua Operator
                                </td>
                                <td class="px-4 py-3 font-bold text-slate-800 dark:text-white">
                                    Rp {{ number_format(array_sum(array_column($operatorData, 'total_tagihan')), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 font-bold text-green-600 dark:text-green-400">
                                    Rp {{ number_format(array_sum(array_column($operatorData, 'tagihan_lunas')), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 font-bold text-orange-600 dark:text-orange-400">
                                    Rp {{ number_format(array_sum(array_column($operatorData, 'sisa_tagihan')), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 font-bold text-slate-800 dark:text-white text-center">
                                    {{ number_format(array_sum(array_column($operatorData, 'komisi_percent')), 0, ',', '.') }} %
                                </td>
                                <td class="px-4 py-3 font-bold text-green-600 dark:text-green-400">
                                    Rp {{ number_format(array_sum(array_column($operatorData, 'komisi_value')), 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 font-bold text-orange-600 dark:text-orange-400">
                                    Rp {{ number_format(array_sum(array_column($operatorData, 'tagihan_lunas')) - array_sum(array_column($operatorData, 'komisi_value')), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>

                <!-- Tabel baru: ditagih oleh operator tapi berhasil ditagih admin -->
                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr>
                                <th class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 rounded-l-lg">Operator</th>
                                <th class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 rounded-r-lg text-right">Berhasil Ditagih Admin (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @forelse($operatorSuccessTable ?? [] as $row)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
                                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $row['operator_name'] }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">Rp {{ number_format((float)$row['tagihan_berhasil_ditagih_admin'], 0, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                        Tidak ada data.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.tailwindcss.css">
@endpush

@push('scripts')
    <script>
        // global: saveKomisi() defined below in DataTables script block
    </script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.tailwindcss.js"></script>
    <script>
        async function saveKomisi(operatorId, month, year, komisiPercent) {
            const form = new FormData();
            form.append('operator_id', operatorId);
            form.append('month', month);
            form.append('year', year);
            form.append('komisi_percent', komisiPercent);

            try {
                const resp = await fetch(`{{ route('billing.rekapOperator.komisi') }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: form
                });
                const data = await resp.json();
                if (!resp.ok || data.status !== 'success') {
                    alert(data.message || 'Gagal menyimpan komisi');
                    return;
                }

                // Reload agar tagihan bersih & input komisi mengikuti value terbaru
                location.reload();
            } catch (e) {
                alert('Gagal menyimpan komisi');
            }
        }

        $(document).ready(function () {
            $('#tableRekapOperator').DataTable({
                responsive: true,
                pageLength: 10,
                ordering: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                }
            });
        });

    </script>
@endpush