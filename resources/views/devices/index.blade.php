@extends('layouts.app2')

@section('title', 'Data Perangkat')
@section('header', 'Data Perangkat')
@section('subheader', 'Manajemen daftar perangkat')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-medium text-slate-800 dark:text-white">Daftar Perangkat</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Kelola dan lihat daftar perangkat Anda.</p>
        </div>
        <button type="button" onclick="openDeviceModal()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white transition-colors border border-transparent rounded-lg bg-[#352f99] hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 dark:focus:ring-offset-slate-800">
            <i class="fas fa-plus mr-2"></i> Tambah Perangkat
        </button>
    </div>

    <div class="p-4 sm:p-6 overflow-x-auto">
        <table id="devicesTable" class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-900/50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">No</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gambar</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jenis</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Rasio</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Redaman</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">IP Addr</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Keterangan</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
                @forelse($devices as $index => $device)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $index + 1 }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($device->foto)
                                <img src="{{ asset('uploads/' . $device->foto) }}" alt="{{ $device->nama }}" onclick="openImagePreview('{{ asset('uploads/' . $device->foto) }}')" class="w-16 h-16 object-cover rounded-lg shadow-sm border border-slate-200 dark:border-slate-600 cursor-pointer hover:opacity-80 transition-opacity">
                            @else
                                <div class="w-16 h-16 bg-slate-100 dark:bg-slate-700 rounded-lg flex items-center justify-center border border-slate-200 dark:border-slate-600">
                                    <i class="fas fa-image text-slate-400"></i>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300">
                                {{ $device->kategori }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $device->nama }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-slate-900 dark:text-white">{{ $device->rasio ?: '-' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-slate-900 dark:text-white">{{ $device->redaman ? $device->redaman . ' dB' : '-' }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if(in_array($device->kategori, ['Router', 'HTB']))
                                <div class="text-sm text-slate-900 dark:text-white font-mono">{{ $device->ip_address ?: '-' }}</div>
                            @else
                                <div class="text-sm text-slate-400 dark:text-slate-500">-</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-slate-900 dark:text-white">{{ $device->keterangan ?: '-' }}</div>
                            @if($device->latitude && $device->longitude)
                            <div class="text-xs text-slate-500 mt-1">
                                <a href="https://maps.google.com/?q={{ $device->latitude }},{{ $device->longitude }}" target="_blank" class="text-blue-500 hover:underline">
                                    <i class="fas fa-map-marker-alt"></i> {{ substr($device->latitude, 0, 6) }}, {{ substr($device->longitude, 0, 6) }}
                                </a>
                            </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button type="button" onclick="openDeviceModal({{ $device->toJson() }})" class="text-[#352f99] hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3 transition-colors">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form action="{{ route('devices.destroy', $device->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus perangkat ini?');">
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
                        <td colspan="9" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                            <div class="flex flex-col items-center justify-center">
                                <i class="fas fa-microchip text-4xl mb-3 text-slate-300 dark:text-slate-600"></i>
                                <p>Belum ada data perangkat.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Perangkat -->
<div id="deviceModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-slate-900/75 dark:bg-slate-900/90 backdrop-blur-sm" aria-hidden="true" onclick="closeDeviceModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-slate-800 rounded-xl shadow-xl sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full sm:p-6 border border-slate-200 dark:border-slate-700">
            <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white" id="modal-title">Tambah Data Perangkat</h3>
                <button type="button" onclick="closeDeviceModal()" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form id="deviceForm" action="{{ route('devices.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Column 1: Info & Map -->
                    <div class="space-y-4">
                        <div>
                            <label for="nama" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Nama Perangkat <span class="text-red-500">*</span></label>
                            <input type="text" name="nama" id="nama" required
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                        </div>

                        <div>
                            <label for="kategori" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Kategori <span class="text-red-500">*</span></label>
                            <select name="kategori" id="kategori" required onchange="toggleCustomerField()"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                                <option value="ODP">ODP</option>
                                <option value="Router">Router</option>
                                <option value="HTB">HTB</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <div id="customer_container" class="hidden">
                            <label for="customer_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Pelanggan</label>
                            <select name="customer_id" id="customer_id" onchange="fetchCustomerIp()"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                                <option value="">-- Pilih Pelanggan --</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->internet_number }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div id="ip_address_container" class="hidden">
                            <label for="ip_address" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">IP Address</label>
                            <input type="text" name="ip_address" id="ip_address" readonly
                                class="block w-full px-3 py-2 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:text-white transition-colors cursor-not-allowed">
                            <p class="text-xs text-slate-500 mt-1" id="ip_status_text">Otomatis didapatkan dari Mikrotik</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="rasio" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Rasio</label>
                                <input type="text" name="rasio" id="rasio" placeholder="Contoh: 1:8"
                                    class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                            </div>
                            <div>
                                <label for="redaman" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Redaman (dB)</label>
                                <input type="text" name="redaman" id="redaman" placeholder="Contoh: 15.5"
                                    class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors">
                            </div>
                        </div>

                        <div>
                            <label for="foto" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Foto Perangkat (.png/.jpg, Max 500Kb)</label>
                            <input type="file" name="foto" id="foto" accept=".jpg,.jpeg,.png"
                                class="block w-full text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-[#352f99] hover:file:bg-indigo-100 dark:file:bg-slate-700 dark:file:text-indigo-400 dark:hover:file:bg-slate-600">
                        </div>

                        <div>
                            <label for="keterangan" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Keterangan</label>
                            <textarea name="keterangan" id="keterangan" rows="2"
                                class="block w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg shadow-sm focus:ring-[#352f99] focus:border-[#352f99] sm:text-sm dark:bg-slate-900 dark:text-white transition-colors"></textarea>
                        </div>
                    </div>

                    <!-- Column 2: Lokasi -->
                    <div class="space-y-4">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Lokasi Perangkat</label>
                        <div id="deviceMap" class="h-64 w-full rounded-lg border border-slate-300 dark:border-slate-600 mb-2 z-10"></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="latitude" class="block text-xs font-medium text-slate-500 dark:text-slate-400">Latitude</label>
                                <input type="text" name="latitude" id="latitude" readonly
                                    class="block w-full mt-1 px-3 py-1.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded shadow-sm sm:text-xs dark:text-white">
                            </div>
                            <div>
                                <label for="longitude" class="block text-xs font-medium text-slate-500 dark:text-slate-400">Longitude</label>
                                <input type="text" name="longitude" id="longitude" readonly
                                    class="block w-full mt-1 px-3 py-1.5 bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-600 rounded shadow-sm sm:text-xs dark:text-white">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <button type="button" onclick="closeDeviceModal()" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#352f99] dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-800 transition-colors">
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

<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="fixed inset-0 z-[60] hidden flex items-center justify-center bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeImagePreview()">
    <div class="relative w-[800px] max-w-[90vw] h-[600px] max-h-[90vh] flex items-center justify-center" onclick="event.stopPropagation()">
        <button type="button" onclick="closeImagePreview()" class="absolute -top-12 right-0 text-white hover:text-red-400 transition-colors bg-black/40 hover:bg-black/60 rounded-full w-10 h-10 flex items-center justify-center">
            <i class="fas fa-times text-xl"></i>
        </button>
        <img id="previewImage" src="" alt="Preview" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl bg-black/20">
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.tailwindcss.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    var map, marker;
    var defaultLat = -6.200000;
    var defaultLng = 106.816666;

    $(document).ready(function() {
        $('#devicesTable').DataTable({
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
                { orderable: false, targets: 1 }, // Thumbnail
                { orderable: false, targets: -1 } // Aksi
            ],
            drawCallback: function() {
                $('.paginate_button').addClass('dark:text-slate-300');
                $('.paginate_button.current').addClass('dark:bg-slate-700 dark:border-slate-600');
            }
        });
    });

    function initMap(lat, lng) {
        if (map) {
            map.remove();
        }

        map = L.map('deviceMap').setView([lat, lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        
        // Setup initial values
        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;

        // On marker drag
        marker.on('dragend', function (e) {
            var latlng = marker.getLatLng();
            document.getElementById('latitude').value = latlng.lat;
            document.getElementById('longitude').value = latlng.lng;
        });

        // On map click
        map.on('click', function (e) {
            marker.setLatLng(e.latlng);
            document.getElementById('latitude').value = e.latlng.lat;
            document.getElementById('longitude').value = e.latlng.lng;
        });

        // Fix map size rendering inside modal
        setTimeout(function() {
            map.invalidateSize();
        }, 200);
    }

    function openDeviceModal(device = null) {
        const form = document.getElementById('deviceForm');
        const modalTitle = document.getElementById('modal-title');
        const methodInput = document.getElementById('formMethod');

        let initLat = defaultLat;
        let initLng = defaultLng;

        if (device) {
            modalTitle.innerText = 'Edit Data Perangkat';
            form.action = `/devices/${device.id}`;
            methodInput.value = 'PUT';
            
            document.getElementById('nama').value = device.nama;
            document.getElementById('kategori').value = device.kategori || 'ODP';
            toggleCustomerField();
            if (device.customer_id) {
                document.getElementById('customer_id').value = device.customer_id;
            } else {
                document.getElementById('customer_id').value = '';
            }
            document.getElementById('ip_address').value = device.ip_address || '';
            document.getElementById('rasio').value = device.rasio || '';
            document.getElementById('redaman').value = device.redaman || '';
            document.getElementById('keterangan').value = device.keterangan || '';
            
            if(device.latitude && device.longitude) {
                initLat = parseFloat(device.latitude);
                initLng = parseFloat(device.longitude);
            }
        } else {
            modalTitle.innerText = 'Tambah Data Perangkat';
            form.action = "{{ route('devices.store') }}";
            methodInput.value = 'POST';
            form.reset();
        }
        
        document.getElementById('deviceModal').classList.remove('hidden');
        
        // Initialize map with a small delay so DOM is visible
        setTimeout(() => {
            initMap(initLat, initLng);
        }, 100);
    }

    function closeDeviceModal() {
        document.getElementById('deviceModal').classList.add('hidden');
    }

    function toggleCustomerField() {
        const kategori = document.getElementById('kategori').value;
        const custContainer = document.getElementById('customer_container');
        const ipContainer = document.getElementById('ip_address_container');
        
        if (kategori === 'Router' || kategori === 'HTB') {
            custContainer.classList.remove('hidden');
            ipContainer.classList.remove('hidden');
        } else {
            custContainer.classList.add('hidden');
            ipContainer.classList.add('hidden');
            document.getElementById('customer_id').value = '';
            document.getElementById('ip_address').value = '';
        }
    }

    function fetchCustomerIp() {
        const customerId = document.getElementById('customer_id').value;
        const ipInput = document.getElementById('ip_address');
        const statusText = document.getElementById('ip_status_text');
        
        if (!customerId) {
            ipInput.value = '';
            statusText.innerText = 'Pilih pelanggan untuk melihat IP Address';
            return;
        }

        ipInput.value = 'Mencari...';
        statusText.innerText = 'Mengambil data dari Mikrotik...';

        fetch(`/devices/customer-ip/${customerId}`)
            .then(response => response.json())
            .then(data => {
                ipInput.value = data.ip_address;
                statusText.innerText = data.ip_address.includes('Offline') ? 'Pelanggan sedang offline atau IP tidak ditemukan.' : 'Berhasil didapatkan.';
            })
            .catch(error => {
                ipInput.value = '';
                statusText.innerText = 'Gagal mengambil IP Address.';
            });
    }

    function openImagePreview(url) {
        document.getElementById('previewImage').src = url;
        document.getElementById('imagePreviewModal').classList.remove('hidden');
    }

    function closeImagePreview() {
        document.getElementById('imagePreviewModal').classList.add('hidden');
        document.getElementById('previewImage').src = '';
    }
</script>
@endpush
