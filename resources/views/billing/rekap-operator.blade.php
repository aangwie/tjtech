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
                                Persentase</th>
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
                                    @if($data['total_tagihan'] > 0)
                                        @php
                                            $percentage = round(($data['tagihan_lunas'] / $data['total_tagihan']) * 100, 1);
                                            $colorClass = $percentage >= 80 ? 'text-green-600' : ($percentage >= 50 ? 'text-yellow-600' : 'text-red-600');
                                        @endphp
                                        <span class="font-semibold {{ $colorClass }}">
                                            {{ $percentage }}%
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
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
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $totalTagihan = array_sum(array_column($operatorData, 'total_tagihan'));
                                        $totalLunas = array_sum(array_column($operatorData, 'tagihan_lunas'));
                                        $totalPercentage = $totalTagihan > 0 ? round(($totalLunas / $totalTagihan) * 100, 1) : 0;
                                    @endphp
                                    <span class="font-bold text-primary-600 dark:text-primary-400">
                                        {{ $totalPercentage }}%
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

    </div>

@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.tailwindcss.css">
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.tailwindcss.js"></script>
    <script>
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