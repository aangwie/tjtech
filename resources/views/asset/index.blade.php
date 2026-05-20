@extends('layouts.app2')

@section('title', 'Data Aset')
@section('header', 'Data Aset')
@section('subheader', 'Manajemen daftar aset perusahaan')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
<style>
    /* Fix for Tailwind forms plugin overriding datatables select */
    .dataTables_wrapper .dataTables_length select { 
        padding-right: 2rem; 
        min-width: 4rem;
        border-radius: 0.5rem;
    }
    .dataTables_wrapper .dataTables_filter input {
        margin-left: 0.5rem;
        border-radius: 0.5rem;
        border: 1px solid #e2e8f0;
        padding: 0.375rem 0.75rem;
        outline: none;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #352f99;
        box-shadow: 0 0 0 1px #352f99;
    }
    .dark .dataTables_wrapper .dataTables_info,
    .dark .dataTables_wrapper .dataTables_length,
    .dark .dataTables_wrapper .dataTables_filter {
        color: #94a3b8;
    }
    .dark .dataTables_wrapper .dataTables_length select,
    .dark .dataTables_wrapper .dataTables_filter input {
        background-color: #1e293b;
        border-color: #334155;
        color: #f8fafc;
    }
    
    /* Pagination Styling */
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
<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 flex items-center transition-all hover:shadow-md">
        <div class="w-12 h-12 rounded-full bg-[#352f99]/10 dark:bg-[#352f99]/20 flex items-center justify-center text-[#352f99] dark:text-indigo-400 mr-4">
            <i class="fas fa-boxes text-xl"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Jumlah Aset</p>
            <h3 class="text-2xl font-bold text-slate-800 dark:text-white">{{ number_format($total_quantity, 0, ',', '.') }}</h3>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 flex items-center transition-all hover:shadow-md">
        <div class="w-12 h-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400 mr-4">
            <i class="fas fa-wallet text-xl"></i>
        </div>
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Nilai Aset</p>
            <h3 class="text-2xl font-bold text-slate-800 dark:text-white">Rp {{ number_format($total_value, 0, ',', '.') }}</h3>
        </div>
    </div>
</div>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-medium text-slate-800 dark:text-white">Daftar Aset</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola dan lihat daftar aset yang Anda miliki.</p>
        </div>
        <button type="button" onclick="openAssetModal()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white transition-colors border border-transparent rounded-lg bg-[#352f99] hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 dark:focus:ring-offset-slate-800">
            <i class="fas fa-plus mr-2"></i> Tambah Aset
        </button>
    </div>

    <div class="p-4 sm:p-6 overflow-x-auto">
        <table id="assetsTable" class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-900/50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Barang / Merk</th>
                    @if(auth()->user()->isSuperAdmin())
                        <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pemilik (Admin)</th>
                    @endif
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tahun & Kondisi</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Harga Perolehan</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Penyusutan</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
                @forelse($assets as $asset)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $asset->nama_barang }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $asset->merk }}</div>
                            @if($asset->has_identifier)
                                <div class="text-xs mt-1 text-[#352f99] dark:text-indigo-400"><i class="fas fa-barcode mr-1"></i>{{ $asset->identifier }}</div>
                            @endif
                        </td>
                        @if(auth()->user()->isSuperAdmin())
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-900 dark:text-white">{{ $asset->admin->name ?? '-' }}</div>
                            </td>
                        @endif
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-slate-900 dark:text-white">{{ $asset->tahun_perolehan }}</div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $asset->kondisi_perolehan == 'Baru' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' }}">
                                {{ $asset->kondisi_perolehan }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white font-medium">
                            Rp {{ number_format($asset->harga_perolehan, 0, ',', '.') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                            @if($asset->has_penyusutan && $asset->nilai_penyusutan > 0)
                                Rp {{ number_format($asset->nilai_penyusutan * $asset->jumlah_barang, 0, ',', '.') }} / thn
                            @else
                                <span class="italic text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button type="button" onclick="openAssetModal({{ $asset->toJson() }})" class="text-[#352f99] hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3 transition-colors">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form action="{{ route('asset.destroy', $asset->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus aset ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 transition-colors">
                                    <i class="fas fa-trash-alt"></i> Hapus
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isSuperAdmin() ? '6' : '5' }}" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                            <div class="flex flex-col items-center justify-center">
                                <i class="fas fa-box-open text-4xl mb-3 text-slate-300 dark:text-slate-600"></i>
                                <p>Belum ada data aset.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Aset -->
<div id="assetModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-slate-900/75 dark:bg-slate-900/90 backdrop-blur-sm" aria-hidden="true" onclick="closeAssetModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-slate-800 rounded-xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6 border border-slate-200 dark:border-slate-700">
            <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white" id="modal-title">Tambah Data Aset</h3>
                <button type="button" onclick="closeAssetModal()" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form id="assetForm" action="{{ route('asset.store') }}" method="POST">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                
                <div class="space-y-4">
                    <div>
                        <label for="nama_barang" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nama Barang <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_barang" id="nama_barang" required
                            class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                    </div>
                    
                    <div>
                        <label for="merk" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Merk <span class="text-red-500">*</span></label>
                        <input type="text" name="merk" id="merk" required
                            class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                    </div>

                    <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700">
                        <div class="flex items-center">
                            <input id="has_identifier" name="has_identifier" type="checkbox" onchange="toggleIdentifier(this.checked)" class="w-4 h-4 text-[#352f99] bg-white border-slate-300 rounded focus:ring-[#352f99] dark:focus:ring-[#352f99] dark:ring-offset-slate-800 dark:bg-slate-700 dark:border-slate-600">
                            <label for="has_identifier" class="ml-2 text-sm font-medium text-slate-700 dark:text-slate-300">Ceklist Identifier (Memiliki Kode/SN)</label>
                        </div>
                        
                        <div id="identifier_container" style="display: none;" class="mt-3">
                            <label for="identifier" class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Input Identifier (SN/Kode)</label>
                            <input type="text" name="identifier" id="identifier"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="tahun_perolehan" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tahun Perolehan <span class="text-red-500">*</span></label>
                            <input type="number" name="tahun_perolehan" id="tahun_perolehan" required min="1900" max="{{ date('Y') }}" value="{{ date('Y') }}"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                        </div>
                        <div>
                            <label for="kondisi_perolehan" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Kondisi Perolehan <span class="text-red-500">*</span></label>
                            <select name="kondisi_perolehan" id="kondisi_perolehan" required
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                                <option value="Baru">Baru</option>
                                <option value="Bekas">Bekas</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="jumlah_barang" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Jumlah Barang <span class="text-red-500">*</span></label>
                            <input type="number" name="jumlah_barang" id="jumlah_barang" required min="1" value="1" oninput="calculateHargaPerolehan()"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                        </div>
                        <div>
                            <label for="harga_satuan" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Harga Satuan (Rp) <span class="text-red-500">*</span></label>
                            <input type="number" name="harga_satuan" id="harga_satuan" required min="0" oninput="calculateHargaPerolehan()"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                        </div>
                        <div>
                            <label for="harga_perolehan" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Total Harga (Rp) <span class="text-red-500">*</span></label>
                            <input type="number" name="harga_perolehan" id="harga_perolehan" required min="0" readonly
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm bg-slate-100 dark:bg-slate-800 focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:text-slate-400 transition-colors cursor-not-allowed">
                        </div>
                    </div>

                    <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700">
                        <div class="flex items-center">
                            <input id="has_penyusutan" name="has_penyusutan" type="checkbox" onchange="togglePenyusutan(this.checked)" class="w-4 h-4 text-[#352f99] bg-white border-slate-300 rounded focus:ring-[#352f99] dark:focus:ring-[#352f99] dark:ring-offset-slate-800 dark:bg-slate-700 dark:border-slate-600">
                            <label for="has_penyusutan" class="ml-2 text-sm font-medium text-slate-700 dark:text-slate-300">Penyusutan per tahun</label>
                        </div>
                        
                        <div id="penyusutan_container" style="display: none;" class="mt-3">
                            <label for="nilai_penyusutan" class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Input Nilai Penyusutan (Rp)</label>
                            <input type="number" name="nilai_penyusutan" id="nilai_penyusutan" min="0"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <button type="button" onclick="closeAssetModal()" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#352f99] dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-800 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-[#352f99] border border-transparent rounded-lg shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#352f99] dark:focus:ring-offset-slate-800 transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.tailwindcss.min.js"></script>
<script>
    $(document).ready(function() {
        $('#assetsTable').DataTable({
            dom: '<"flex flex-col sm:flex-row justify-between items-center mb-4"lf>rt<"flex flex-col sm:flex-row justify-between items-center mt-4"ip>',
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json',
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ data",
                info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                paginate: {
                    first: '<i class="fas fa-angle-double-left"></i>',
                    last: '<i class="fas fa-angle-double-right"></i>',
                    next: '<i class="fas fa-chevron-right"></i>',
                    previous: '<i class="fas fa-chevron-left"></i>'
                }
            },
            responsive: true,
            columnDefs: [
                { orderable: false, targets: -1 } // Disable sorting on action column
            ],
            drawCallback: function() {
                // Fix pagination styling for tailwind dark mode
                $('.paginate_button').addClass('dark:text-slate-300');
                $('.paginate_button.current').addClass('dark:bg-slate-700 dark:border-slate-600');
            }
        });
    });

    function toggleIdentifier(show) {
        const container = document.getElementById('identifier_container');
        const input = document.getElementById('identifier');
        if (show) {
            container.style.display = 'block';
            input.required = true;
        } else {
            container.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }

    function togglePenyusutan(show) {
        const container = document.getElementById('penyusutan_container');
        const input = document.getElementById('nilai_penyusutan');
        if (show) {
            container.style.display = 'block';
            input.required = true;
        } else {
            container.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }

    function calculateHargaPerolehan() {
        const jumlah = parseFloat(document.getElementById('jumlah_barang').value) || 0;
        const harga = parseFloat(document.getElementById('harga_satuan').value) || 0;
        document.getElementById('harga_perolehan').value = jumlah * harga;
    }

    function openAssetModal(asset = null) {
        const form = document.getElementById('assetForm');
        const modalTitle = document.getElementById('modal-title');
        const methodInput = document.getElementById('formMethod');
        
        const hasIdentifierCheckbox = document.getElementById('has_identifier');
        const hasPenyusutanCheckbox = document.getElementById('has_penyusutan');

        if (asset) {
            modalTitle.innerText = 'Edit Data Aset';
            form.action = `/asset/${asset.id}`;
            methodInput.value = 'PUT';
            
            document.getElementById('nama_barang').value = asset.nama_barang;
            document.getElementById('merk').value = asset.merk;
            document.getElementById('tahun_perolehan').value = asset.tahun_perolehan;
            document.getElementById('kondisi_perolehan').value = asset.kondisi_perolehan;
            document.getElementById('jumlah_barang').value = asset.jumlah_barang || 1;
            document.getElementById('harga_satuan').value = asset.harga_satuan || 0;
            document.getElementById('harga_perolehan').value = asset.harga_perolehan;
            
            hasIdentifierCheckbox.checked = asset.has_identifier == 1;
            toggleIdentifier(hasIdentifierCheckbox.checked);
            if (hasIdentifierCheckbox.checked) {
                document.getElementById('identifier').value = asset.identifier;
            }

            hasPenyusutanCheckbox.checked = asset.has_penyusutan == 1;
            togglePenyusutan(hasPenyusutanCheckbox.checked);
            if (hasPenyusutanCheckbox.checked) {
                document.getElementById('nilai_penyusutan').value = asset.nilai_penyusutan;
            }
        } else {
            modalTitle.innerText = 'Tambah Data Aset';
            form.action = "{{ route('asset.store') }}";
            methodInput.value = 'POST';
            form.reset();
            
            document.getElementById('tahun_perolehan').value = new Date().getFullYear();
            document.getElementById('jumlah_barang').value = 1;
            document.getElementById('harga_satuan').value = '';
            document.getElementById('harga_perolehan').value = '';
            
            hasIdentifierCheckbox.checked = false;
            toggleIdentifier(false);
            
            hasPenyusutanCheckbox.checked = false;
            togglePenyusutan(false);
        }
        
        document.getElementById('assetModal').classList.remove('hidden');
    }

    function closeAssetModal() {
        document.getElementById('assetModal').classList.add('hidden');
    }
</script>
@endpush
