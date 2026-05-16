@extends('layouts.app2')

@section('title', 'Penghapusan Aset')
@section('header', 'Penghapusan Aset')
@section('subheader', 'Kelola data penghapusan atau penjualan aset perusahaan')

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
    <style>
        /* Pagination Styling (same as index.blade.php) */
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 1rem;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.25rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.375rem 0.75rem !important;
            border-radius: 0.375rem !important;
            border: 1px solid #e2e8f0 !important;
            background: white !important;
            color: #475569 !important;
            font-weight: 500;
            cursor: pointer;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f8fafc !important;
            color: #0f172a !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #352f99 !important;
            color: white !important;
            border-color: #352f99 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .dark .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: #1e293b !important;
            border-color: #334155 !important;
            color: #cbd5e1 !important;
        }

        .dark .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #334155 !important;
            color: white !important;
        }

        .dark .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #352f99 !important;
            border-color: #352f99 !important;
            color: white !important;
        }
    </style>
@endpush

@section('content')

    @if(session('success'))
        <div class="mb-4 p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-slate-800 dark:text-green-400"
            role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-slate-800 dark:text-red-400" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <!-- Form Penghapusan -->
    <div
        class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden mb-6">
        <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-slate-700">
            <h2 class="text-lg font-medium text-slate-800 dark:text-white">Form Penghapusan Aset</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Pilih aset dan isi detail untuk menghapus atau mencatat
                penjualan aset.</p>
        </div>
        <div class="p-4 sm:p-6">
            <form action="{{ route('asset.disposal.store') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div>
                            <label for="asset_id"
                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Daftar Aset <span
                                    class="text-red-500">*</span></label>
                            <select name="asset_id" id="asset_id" required onchange="populateAssetData()"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                                <option value="">-- Pilih Aset --</option>
                                @foreach($assets as $asset)
                                    <option value="{{ $asset->id }}" data-tahun="{{ $asset->tahun_perolehan }}"
                                        data-jumlah="{{ $asset->jumlah_barang }}">
                                        {{ $asset->nama_barang }} ({{ $asset->merk }}) - Sisa: {{ $asset->jumlah_barang }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="tahun_aset"
                                    class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tahun
                                    Aset</label>
                                <input type="text" id="tahun_aset" readonly
                                    class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm bg-slate-100 dark:bg-slate-800 sm:text-sm dark:text-slate-400 cursor-not-allowed">
                            </div>
                            <div>
                                <label for="jumlah_sekarang"
                                    class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Jumlah
                                    Sekarang</label>
                                <input type="number" id="jumlah_sekarang" readonly
                                    class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm bg-slate-100 dark:bg-slate-800 sm:text-sm dark:text-slate-400 cursor-not-allowed">
                            </div>
                        </div>

                        <div>
                            <label for="jumlah_dihapus"
                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Jumlah Dihapus
                                (Jual/Rusak/dll) <span class="text-red-500">*</span></label>
                            <input type="number" name="jumlah_dihapus" id="jumlah_dihapus" required min="1"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div>
                            <label for="alasan"
                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Alasan Dihapus
                                <span class="text-red-500">*</span></label>
                            <select name="alasan" id="alasan" required onchange="togglePenjualanForm()"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                                <option value="">-- Pilih Alasan --</option>
                                <option value="Rusak">Rusak</option>
                                <option value="Dijual">Dijual</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <div id="form-penjualan" style="display: none;"
                            class="p-4 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700 space-y-4">
                            <div>
                                <label for="tanggal_jual"
                                    class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tanggal
                                    Jual</label>
                                <input type="date" name="tanggal_jual" id="tanggal_jual"
                                    class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                            </div>
                            <div>
                                <label for="harga_jual"
                                    class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Harga Jual
                                    (Total)</label>
                                <input type="number" name="harga_jual" id="harga_jual" min="0"
                                    class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                            </div>
                        </div>

                        <div>
                            <label for="keterangan"
                                class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Keterangan</label>
                            <textarea name="keterangan" id="keterangan" rows="2"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors"></textarea>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit"
                        class="inline-flex items-center justify-center px-6 py-2 text-sm font-medium text-white transition-colors border border-transparent rounded-lg bg-[#352f99] hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 dark:focus:ring-offset-slate-800">
                        <i class="fas fa-save mr-2"></i> Simpan Penghapusan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Riwayat Penghapusan -->
    <div
        class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div
            class="p-4 sm:p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-medium text-slate-800 dark:text-white">Riwayat Penghapusan & Penjualan</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Daftar aset yang telah dihapus atau dijual.</p>
            </div>

            <form action="{{ route('asset.disposal.index') }}" method="GET" class="flex flex-col sm:flex-row gap-2">
                <select name="filter_alasan" onchange="this.form.submit()"
                    class="block w-full sm:w-auto px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                    <option value="">Semua Status</option>
                    <option value="Rusak" {{ request('filter_alasan') == 'Rusak' ? 'selected' : '' }}>Rusak</option>
                    <option value="Dijual" {{ request('filter_alasan') == 'Dijual' ? 'selected' : '' }}>Dijual</option>
                    <option value="Lainnya" {{ request('filter_alasan') == 'Lainnya' ? 'selected' : '' }}>Lainnya</option>
                </select>

                @if(request('filter_alasan') == 'Dijual')
                    <select name="filter_tahun" onchange="this.form.submit()"
                        class="block w-full sm:w-auto px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                        <option value="">Semua Tahun</option>
                        @foreach($saleYears as $year)
                            <option value="{{ $year }}" {{ request('filter_tahun') == $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                @endif
            </form>
        </div>

        <div class="p-4 sm:p-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 mb-4">
                <thead class="bg-slate-50 dark:bg-slate-900/50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Tgl Input</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Nama Barang</th>
                        <th scope="col"
                            class="px-6 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Jumlah</th>
                        <th scope="col"
                            class="px-6 py-3 text-center text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Status</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Keterangan</th>
                        <th scope="col"
                            class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Detail Penjualan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
                    @forelse($disposals as $item)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                {{ $item->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $item->nama_barang }}</div>
                                @if(auth()->user()->isSuperAdmin())
                                    <div class="text-xs text-slate-500">{{ $item->admin->name ?? '-' }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span
                                    class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full">-{{ $item->jumlah_dihapus }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($item->alasan == 'Dijual')
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">Dijual</span>
                                @elseif($item->alasan == 'Rusak')
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">Rusak</span>
                                @else
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-300">Lainnya</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 max-w-xs truncate"
                                title="{{ $item->keterangan }}">
                                {{ $item->keterangan ?: '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                @if($item->alasan == 'Dijual')
                                    <div class="text-slate-900 dark:text-white font-medium">Rp
                                        {{ number_format($item->harga_jual, 0, ',', '.') }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">Tgl Jual:
                                        {{ $item->tanggal_jual ? \Carbon\Carbon::parse($item->tanggal_jual)->format('d/m/Y') : '-' }}
                                    </div>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-history text-4xl mb-3 text-slate-300 dark:text-slate-600"></i>
                                    <p>Belum ada riwayat penghapusan aset.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $disposals->links('pagination::tailwind') }}
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        function populateAssetData() {
            const select = document.getElementById('asset_id');
            const selectedOption = select.options[select.selectedIndex];

            const tahunInput = document.getElementById('tahun_aset');
            const jumlahInput = document.getElementById('jumlah_sekarang');
            const hapusInput = document.getElementById('jumlah_dihapus');

            if (selectedOption.value) {
                tahunInput.value = selectedOption.getAttribute('data-tahun');
                const jumlahSekarang = selectedOption.getAttribute('data-jumlah');
                jumlahInput.value = jumlahSekarang;

                // Set max for jumlah dihapus
                hapusInput.max = jumlahSekarang;
                hapusInput.value = 1;
            } else {
                tahunInput.value = '';
                jumlahInput.value = '';
                hapusInput.max = '';
                hapusInput.value = '';
            }
        }

        function togglePenjualanForm() {
            const alasan = document.getElementById('alasan').value;
            const formPenjualan = document.getElementById('form-penjualan');
            const tglJual = document.getElementById('tanggal_jual');
            const hrgJual = document.getElementById('harga_jual');

            if (alasan === 'Dijual') {
                formPenjualan.style.display = 'block';
                tglJual.required = true;
                hrgJual.required = true;
            } else {
                formPenjualan.style.display = 'none';
                tglJual.required = false;
                hrgJual.required = false;
                tglJual.value = '';
                hrgJual.value = '';
            }
        }
    </script>
@endpush