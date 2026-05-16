@extends('layouts.app2')

@section('title', 'Relasi Perangkat')
@section('header', 'Relasi Perangkat')
@section('subheader', 'Manajemen relasi antar perangkat')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.tailwindcss.min.css">
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

    /* Select2 Tailwind Styling */
    .select2-container--default .select2-selection--single,
    .select2-container--default .select2-selection--multiple {
        border-color: #cbd5e1 !important;
        min-height: 42px;
        padding-top: 4px;
        padding-bottom: 4px;
        border-radius: 0.5rem !important;
        background-color: #ffffff !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #334155 !important;
        line-height: 32px !important;
    }
    .select2-container--default .select2-dropdown {
        background-color: #ffffff !important;
        border-color: #cbd5e1 !important;
    }
    .select2-container--default .select2-results__option {
        color: #334155 !important;
        background-color: #ffffff !important;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #352f99 !important;
        color: #ffffff !important;
    }
    
    /* Dark Mode Styling */
    .dark .select2-container--default .select2-selection--single,
    .dark .select2-container--default .select2-selection--multiple {
        background-color: #0f172a !important;
        border-color: #334155 !important;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__rendered,
    .dark .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        color: #f8fafc !important;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8 !important;
    }
    .dark .select2-container--default .select2-dropdown {
        background-color: #1e293b !important;
        border-color: #334155 !important;
    }
    .dark .select2-container--default .select2-results__option {
        color: #f8fafc !important;
        background-color: #1e293b !important;
    }
    .dark .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #334155 !important;
    }
    .dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #352f99 !important;
        color: #ffffff !important;
    }
    .dark .select2-search__field {
        background-color: #0f172a !important;
        border-color: #334155 !important;
        color: #f8fafc !important;
    }
    
    /* Multi-select Pills Styling */
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #eef2ff !important;
        border: 1px solid #c7d2fe !important;
        color: #3730a3 !important;
        border-radius: 4px !important;
        padding: 2px 6px !important;
        margin-top: 5px !important;
    }
    .dark .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #312e81 !important;
        border: 1px solid #3730a3 !important;
        color: #c7d2fe !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #3730a3 !important;
        margin-right: 5px !important;
        border-right: 1px solid #c7d2fe !important;
        padding-right: 5px !important;
    }
    .dark .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #c7d2fe !important;
        border-right: 1px solid #3730a3 !important;
    }
</style>
@endpush

@section('content')
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <div class="p-4 sm:p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-medium text-slate-800 dark:text-white">Daftar Relasi Perangkat</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">Hubungkan perangkat asal ke banyak perangkat tujuan.</p>
        </div>
        <button type="button" onclick="openRelationModal()" class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white transition-colors border border-transparent rounded-lg bg-[#352f99] hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-600 dark:focus:ring-offset-slate-800">
            <i class="fas fa-plus mr-2"></i> Tambah Relasi
        </button>
    </div>

    @if($errors->any())
    <div class="p-4 bg-red-50 border-l-4 border-red-500 dark:bg-red-900/30">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-700 dark:text-red-400">
                    {{ $errors->first() }}
                </p>
            </div>
        </div>
    </div>
    @endif

    <div class="p-4 sm:p-6 overflow-x-auto">
        <table id="relationsTable" class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-900/50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">No</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Perangkat Asal</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Perangkat Tujuan</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700 bg-white dark:bg-slate-800">
                @php $no = 1; @endphp
                @forelse($relationsGrouped as $sourceId => $relations)
                    @php $source = $relations->first()->source; @endphp
                    @if(!$source) @continue @endif
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $no++ }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                @if($source->foto)
                                    <img src="{{ asset('uploads/' . $source->foto) }}" class="w-8 h-8 rounded object-cover mr-3 border border-slate-200 dark:border-slate-600">
                                @else
                                    <div class="w-8 h-8 rounded bg-slate-100 dark:bg-slate-700 flex items-center justify-center mr-3 border border-slate-200 dark:border-slate-600">
                                        <i class="fas fa-microchip text-slate-400 text-xs"></i>
                                    </div>
                                @endif
                                <div class="text-sm font-medium text-slate-900 dark:text-white">{{ $source->nama }}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-2">
                                @foreach($relations as $rel)
                                    @if($rel->target)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 border border-indigo-200 dark:border-indigo-800">
                                        {{ $rel->target->nama }}
                                    </span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button type="button" onclick="openRelationModal({{ $sourceId }}, {{ json_encode($relations->pluck('target_id')->toArray()) }})" class="text-[#352f99] hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-3 transition-colors">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form action="{{ route('device-relations.destroy', $sourceId) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus semua relasi untuk perangkat ini?');">
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
                        <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                            <div class="flex flex-col items-center justify-center">
                                <i class="fas fa-project-diagram text-4xl mb-3 text-slate-300 dark:text-slate-600"></i>
                                <p>Belum ada data relasi perangkat.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Relasi -->
<div id="relationModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 transition-opacity bg-slate-900/75 dark:bg-slate-900/90 backdrop-blur-sm" aria-hidden="true" onclick="closeRelationModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block px-4 pt-5 pb-4 overflow-visible text-left align-bottom transition-all transform bg-white dark:bg-slate-800 rounded-xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6 border border-slate-200 dark:border-slate-700">
            <div class="flex justify-between items-center mb-5 pb-3 border-b border-slate-100 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white" id="modal-title">Tambah Relasi Perangkat</h3>
                <button type="button" onclick="closeRelationModal()" class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form id="relationForm" action="{{ route('device-relations.store') }}" method="POST">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                
                <div class="space-y-6">
                    <div>
                        <label for="source_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Perangkat Asal <span class="text-red-500">*</span></label>
                        <select name="source_id" id="source_id" required class="select2-single w-full" onchange="filterTargets()">
                            <option value="">Pilih Perangkat Asal...</option>
                            @foreach($devices as $device)
                                <option value="{{ $device->id }}">{{ $device->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="target_ids" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Perangkat Tujuan (Bisa lebih dari 1) <span class="text-red-500">*</span></label>
                        <select name="target_ids[]" id="target_ids" required multiple class="select2-multiple w-full">
                            @foreach($devices as $device)
                                <option value="{{ $device->id }}">{{ $device->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
                    <button type="button" onclick="closeRelationModal()" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#352f99] dark:bg-slate-800 dark:text-slate-300 dark:border-slate-600 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-800 transition-colors">
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
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#relationsTable').DataTable({
            dom: '<"flex flex-col sm:flex-row justify-between items-center mb-4"lf>rt<"flex flex-col sm:flex-row justify-between items-center mt-4"ip>',
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json'
            },
            responsive: true,
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            drawCallback: function() {
                $('.paginate_button').addClass('dark:text-slate-300');
                $('.paginate_button.current').addClass('dark:bg-slate-700 dark:border-slate-600');
            }
        });

        // Initialize Select2
        $('.select2-single').select2({
            dropdownParent: $('#relationModal'),
            width: '100%'
        });
        
        $('.select2-multiple').select2({
            dropdownParent: $('#relationModal'),
            width: '100%',
            placeholder: "Pilih perangkat tujuan..."
        });
    });

    function filterTargets() {
        const sourceId = $('#source_id').val();
        
        // Disable the selected source device in the targets dropdown
        $('#target_ids option').each(function() {
            if ($(this).val() == sourceId) {
                $(this).prop('disabled', true);
                // If it was selected, unselect it
                if($(this).is(':selected')) {
                    $(this).prop('selected', false);
                }
            } else {
                $(this).prop('disabled', false);
            }
        });
        
        // Refresh select2 to show disabled states correctly
        $('#target_ids').select2({
            dropdownParent: $('#relationModal'),
            width: '100%',
            placeholder: "Pilih perangkat tujuan..."
        });
    }

    function openRelationModal(sourceId = null, targetIds = []) {
        const form = document.getElementById('relationForm');
        const modalTitle = document.getElementById('modal-title');
        const methodInput = document.getElementById('formMethod');
        
        // Reset validation states
        $('#target_ids option').prop('disabled', false);

        if (sourceId) {
            modalTitle.innerText = 'Edit Relasi Perangkat';
            form.action = `/device-relations/${sourceId}`;
            methodInput.value = 'PUT';
            
            // Set source
            $('#source_id').val(sourceId).trigger('change');
            
            // Disable source in edit to prevent breaking the group
            // But we allow it to be changed if needed, just let the backend handle it or disable it.
            // Let's make it readonly in UI by disabling it and adding a hidden input
            $('#source_id').prop('disabled', true);
            
            // Create hidden input to submit source_id since disabled select doesn't submit
            if($('#hidden_source_id').length == 0) {
                $('<input>').attr({
                    type: 'hidden',
                    id: 'hidden_source_id',
                    name: 'source_id',
                    value: sourceId
                }).appendTo('#relationForm');
            } else {
                $('#hidden_source_id').val(sourceId);
            }

            // Set targets
            $('#target_ids').val(targetIds).trigger('change');
        } else {
            modalTitle.innerText = 'Tambah Relasi Perangkat';
            form.action = "{{ route('device-relations.store') }}";
            methodInput.value = 'POST';
            
            $('#source_id').prop('disabled', false).val('').trigger('change');
            $('#hidden_source_id').remove();
            
            $('#target_ids').val(null).trigger('change');
        }
        
        filterTargets();
        document.getElementById('relationModal').classList.remove('hidden');
    }

    function closeRelationModal() {
        document.getElementById('relationModal').classList.add('hidden');
    }
</script>
@endpush
