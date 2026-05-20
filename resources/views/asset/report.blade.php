@extends('layouts.app2')

@section('title', 'Laporan Aset')
@section('header', 'Laporan Aset')
@section('subheader', 'Cetak laporan data aset')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-[#352f99] dark:text-indigo-400">
                    <i class="fas fa-print"></i>
                </div>
                <div>
                    <h2 class="text-lg font-medium text-slate-800 dark:text-white">Filter Laporan Aset</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Sesuaikan parameter laporan yang ingin dicetak.</p>
                </div>
            </div>
        </div>

        <div class="p-6">
            <form action="{{ route('asset.print') }}" method="POST" target="_blank">
                @csrf
                
                <div class="space-y-6">
                    <div>
                        <label for="tahun_perolehan" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Pilih Tahun Perolehan</label>
                        <select name="tahun_perolehan" id="tahun_perolehan" class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                            <option value="">-- Semua Tahun --</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pilih tahun tertentu untuk membatasi aset yang ditampilkan.</p>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-lg border border-slate-200 dark:border-slate-700">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="hitung_penyusutan" name="hitung_penyusutan" type="checkbox" value="1" class="w-4 h-4 text-[#352f99] bg-white border-slate-300 rounded focus:ring-[#352f99] dark:focus:ring-[#352f99] dark:ring-offset-slate-800 dark:bg-slate-700 dark:border-slate-600">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="hitung_penyusutan" class="font-medium text-slate-700 dark:text-slate-300">Tampilkan Perhitungan Penyusutan</label>
                                <p class="text-slate-500 dark:text-slate-400 mt-1 text-xs">Jika dicentang, laporan akan mengkalkulasi dan menampilkan Nilai Aset saat ini berdasarkan umur aset dan nilai penyusutan per tahun.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end">
                    <button type="submit" class="inline-flex items-center justify-center px-6 py-2.5 text-sm font-medium text-white transition-colors border border-transparent rounded-lg bg-[#352f99] hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 dark:focus:ring-offset-slate-800 shadow-sm">
                        <i class="fas fa-file-pdf mr-2"></i> Cetak Laporan PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
