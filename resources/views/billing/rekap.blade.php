@extends('layouts.app2')

@section('title', 'Rekap Tagihan')
@section('header', 'Rekap Tagihan')
@section('subheader', 'Ringkasan tagihan per pelanggan, saldo, dan kurang bayar.')

@section('content')

    <div>

        <!-- Filter Bar -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="bg-indigo-50 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 p-2 rounded-lg">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Rekap Tagihan</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Ringkasan seluruh pelanggan</p>
                </div>
            </div>
            @if(auth()->user()->role == 'superadmin')
                <form action="{{ route('billing.rekap') }}" method="GET"
                    class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    <select name="admin_id"
                        class="block w-full sm:w-48 rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6">
                        <option value="">Semua Admin</option>
                        @foreach($admins as $admin)
                            <option value="{{ $admin->id }}" {{ $selectedAdminId == $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }}{{ $admin->id == auth()->id() ? ' (Self)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit"
                        class="inline-flex justify-center items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 transition-all">
                        <i class="fas fa-search mr-2"></i> Tampilkan
                    </button>
                </form>
            @endif
        </div>

        <!-- Stats Overview -->
        <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 p-6 shadow-lg shadow-indigo-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-sm font-medium text-indigo-100">Total Tagihan (Semua Periode)</dt>
                <dd class="mt-2 text-3xl font-bold tracking-tight text-white">
                    Rp {{ number_format($grandTotalTagihan, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-file-invoice-dollar fa-3x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-6 shadow-lg shadow-emerald-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-sm font-medium text-emerald-100">Total Sudah Bayar</dt>
                <dd class="mt-2 text-3xl font-bold tracking-tight text-white">
                    Rp {{ number_format($grandTotalBayar, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-check-circle fa-3x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-600 p-6 shadow-lg shadow-blue-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-sm font-medium text-blue-100">Total Saldo Pelanggan</dt>
                <dd class="mt-2 text-3xl font-bold tracking-tight text-white">
                    Rp {{ number_format($grandTotalSaldo, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-wallet fa-3x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 p-6 shadow-lg shadow-orange-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-sm font-medium text-orange-100">Total Kurang Bayar</dt>
                <dd class="mt-2 text-3xl font-bold tracking-tight text-white">
                    Rp {{ number_format($grandTotalKurangBayar, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-exclamation-triangle fa-3x"></i>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div
            class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm ring-1 ring-slate-900/5 dark:ring-slate-700/50 overflow-hidden">
            <div class="overflow-x-auto p-4">
                <table id="tableRekap" class="w-full text-left border-collapse">
                    <thead>
                        <tr>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 rounded-l-lg">
                                Pelanggan</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                                Paket</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                                Total Tagihan</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                                Total Bayar</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                                Saldo</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                                Kurang Bayar</th>
                            <th
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                                Status</th>
                            @if(in_array(auth()->user()->role, ['admin', 'superadmin']))
                                <th
                                    class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 rounded-r-lg text-right">
                                    Aksi</th>
                            @else
                                <th
                                    class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 rounded-r-lg text-right">
                                    Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($rekap as $item)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        {{ $item['customer']->name }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $item['customer']->internet_number ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm text-slate-600 dark:text-slate-300">
                                    Rp {{ number_format($item['customer']->monthly_price ?? 0, 0, ',', '.') }}/bln
                                </td>
                                <td class="px-4 py-3 align-middle font-medium text-slate-700 dark:text-slate-200">
                                    Rp {{ number_format($item['total_tagihan'], 0, ',', '.') }}
                                    <div class="text-[10px] text-slate-400">{{ $item['total_invoices'] }} invoice</div>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm text-emerald-600 dark:text-emerald-400 font-medium">
                                    Rp {{ number_format($item['total_bayar'], 0, ',', '.') }}
                                    <div class="text-[10px] text-slate-400">{{ $item['paid_count'] }} lunas</div>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <span class="text-sm font-semibold {{ $item['saldo'] > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400' }}">
                                        Rp {{ number_format($item['saldo'], 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    @if($item['kurang_bayar'] > 0)
                                        <span class="text-sm font-semibold text-orange-500">
                                            Rp {{ number_format($item['kurang_bayar'], 0, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="text-sm text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    @if($item['unpaid_count'] > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                            {{ $item['unpaid_count'] }} belum bayar
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                            Lunas
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-right">
                                    <div class="flex justify-end gap-1">
                                        <button type="button"
                                            onclick="showDetailTagihan({{ $item['customer']->id }})"
                                            class="p-1.5 rounded-md text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-900/20 hover:bg-indigo-100 dark:hover:bg-indigo-900/40 transition-colors"
                                            title="Detail Tagihan">
                                            <i class="fas fa-list"></i>
                                        </button>
                                        @if(in_array(auth()->user()->role, ['admin', 'superadmin']))
                                            <button type="button"
                                                onclick="openTopUpModal({{ $item['customer']->id }}, '{{ addslashes($item['customer']->name) }}')"
                                                class="p-1.5 rounded-md text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors"
                                                title="Top Up Saldo">
                                                <i class="fas fa-plus-circle"></i>
                                            </button>
                                            <button type="button"
                                                onclick="openEditBalanceModal({{ $item['customer']->id }}, '{{ addslashes($item['customer']->name) }}', {{ $item['saldo'] }})"
                                                class="p-1.5 rounded-md text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition-colors"
                                                title="Edit Saldo">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button"
                                                onclick="confirmDeleteBalance({{ $item['customer']->id }}, '{{ addslashes($item['customer']->name) }}')"
                                                class="p-1.5 rounded-md text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                                                title="Hapus Saldo">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
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
            $('#tableRekap').DataTable({
                responsive: true,
                order: [[0, 'asc']],
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ pelanggan",
                    infoEmpty: "Tidak ada data",
                    zeroRecords: "Data tidak ditemukan",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "»",
                        previous: "«"
                    }
                }
            });
        });

        function openTopUpModal(customerId, customerName) {
            Swal.fire({
                title: 'Top Up Saldo',
                html: `
                    <p class="text-sm text-gray-500 mb-4">Tambahkan saldo untuk <b>${customerName}</b></p>
                    <div class="text-left">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Top Up (Rp)</label>
                        <input type="number" id="swal_topup_amount" class="swal2-input !m-0 w-full" placeholder="Contoh: 50000" style="height: 40px; font-size: 1rem;">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-plus-circle"></i> Top Up',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#64748b',
                preConfirm: () => {
                    const amount = document.getElementById('swal_topup_amount').value;
                    if (!amount || amount <= 0) {
                        Swal.showValidationMessage('Jumlah top up tidak valid');
                        return false;
                    }
                    return amount;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    processSweetAlertTopUp(customerId, result.value);
                }
            });
        }

        function processSweetAlertTopUp(customerId, amount) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Mohon tunggu',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: '{{ route("billing.topup") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: JSON.stringify({
                    customer_id: customerId,
                    amount: parseFloat(amount)
                }),
                contentType: 'application/json',
                success: function(res) {
                    if (res.status) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message,
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Gagal', res.message, 'error');
                    }
                },
                error: function(err) {
                    let msg = 'Terjadi kesalahan jaringan';
                    if (err.responseJSON && err.responseJSON.message) {
                        msg = err.responseJSON.message;
                    }
                    Swal.fire('Gagal', msg, 'error');
                }
            });
        }

        function showDetailTagihan(customerId) {
            Swal.fire({
                title: 'Memuat data...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: `/billing/rekap/${customerId}/invoices`,
                method: 'GET',
                success: function(data) {
                    Swal.close();
                    
                    let tableRows = '';
                    if (data.invoices.length === 0) {
                        tableRows = '<tr><td colspan="4" class="text-center py-4 text-gray-500">Tidak ada tagihan</td></tr>';
                    } else {
                        data.invoices.forEach(inv => {
                            let statusBadge = inv.status === 'paid' 
                                ? '<span class="px-2 py-1 text-[10px] font-semibold rounded-full bg-green-100 text-green-800">Lunas</span>'
                                : '<span class="px-2 py-1 text-[10px] font-semibold rounded-full bg-red-100 text-red-800">Unpaid</span>';
                            
                            let underpaymentInfo = inv.underpayment > 0 
                                ? `<br><span class="text-[10px] text-orange-500">Kurang: ${inv.underpayment_formatted}</span>` 
                                : '';

                            tableRows += `
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-2 px-2 text-left text-xs font-mono">${inv.invoice_number}</td>
                                    <td class="py-2 px-2 text-left text-xs">${inv.due_date}</td>
                                    <td class="py-2 px-2 text-right text-xs font-medium">${inv.price_formatted}${underpaymentInfo}</td>
                                    <td class="py-2 px-2 text-center">${statusBadge}</td>
                                </tr>
                            `;
                        });
                    }

                    let htmlContent = `
                        <div class="max-h-96 overflow-y-auto mt-2">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-100 sticky top-0">
                                    <tr>
                                        <th class="py-2 px-2 text-left text-xs text-gray-600">No. Invoice</th>
                                        <th class="py-2 px-2 text-left text-xs text-gray-600">Jatuh Tempo</th>
                                        <th class="py-2 px-2 text-right text-xs text-gray-600">Tagihan</th>
                                        <th class="py-2 px-2 text-center text-xs text-gray-600">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableRows}
                                </tbody>
                            </table>
                        </div>
                    `;

                    Swal.fire({
                        title: `Detail Tagihan: ${data.customer_name}`,
                        html: htmlContent,
                        width: '600px',
                        confirmButtonText: 'Tutup',
                        confirmButtonColor: '#64748b'
                    });
                },
                error: function() {
                    Swal.fire('Error', 'Gagal memuat detail tagihan', 'error');
                }
            });
        }

        function openEditBalanceModal(customerId, customerName, currentBalance) {
            Swal.fire({
                title: 'Edit Saldo',
                html: `
                    <p class="text-sm text-gray-500 mb-4">Ubah saldo untuk <b>${customerName}</b></p>
                    <div class="text-left">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Saldo Saat Ini (Rp)</label>
                        <input type="number" id="swal_edit_balance" class="swal2-input !m-0 w-full" value="${currentBalance}" placeholder="Contoh: 50000" style="height: 40px; font-size: 1rem;">
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-save"></i> Simpan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#64748b',
                preConfirm: () => {
                    const amount = document.getElementById('swal_edit_balance').value;
                    if (amount === '' || amount < 0) {
                        Swal.showValidationMessage('Jumlah saldo tidak valid');
                        return false;
                    }
                    return amount;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    processSweetAlertUpdateBalance(customerId, result.value);
                }
            });
        }

        function confirmDeleteBalance(customerId, customerName) {
            Swal.fire({
                title: 'Kosongkan Saldo?',
                html: `Anda yakin ingin menghapus/mengosongkan saldo pelanggan <b>${customerName}</b>?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Kosongkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    processSweetAlertUpdateBalance(customerId, 0);
                }
            });
        }

        function processSweetAlertUpdateBalance(customerId, amount) {
            Swal.fire({
                title: 'Memproses...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: '{{ route("billing.updateBalance") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: JSON.stringify({
                    customer_id: customerId,
                    balance: parseFloat(amount)
                }),
                contentType: 'application/json',
                success: function(res) {
                    if (res.status) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: res.message,
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Gagal', res.message, 'error');
                    }
                },
                error: function(err) {
                    let msg = 'Terjadi kesalahan jaringan';
                    if (err.responseJSON && err.responseJSON.message) {
                        msg = err.responseJSON.message;
                    }
                    Swal.fire('Gagal', msg, 'error');
                }
            });
        }
    </script>
@endpush
