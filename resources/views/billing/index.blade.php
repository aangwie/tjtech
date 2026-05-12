@extends('layouts.app2')

@section('title', 'Billing & Tagihan')
@section('header', 'Billing & Kasir')
@section('subheader', 'Kelola tagihan pelanggan, pembayaran, dan invoice.')

@section('content')

    <div x-data="{ 
                                                showCreateModal: false, 
                                                showGenerateModal: false,
                                                showPayModal: false,
                                                showDeleteModal: false,
                                                showDueDateModal: false,
                                                showDeleteModal: false,
                                                showDueDateModal: false,
                                                selectedInvoices: [],
                                                toggleAll() {
                                                    if (this.selectedInvoices.length === {{ count($invoices->where('status', 'unpaid')) }}) {
                                                        this.selectedInvoices = [];
                                                    } else {
                                                        this.selectedInvoices = [
                                                            @foreach($invoices as $inv)
                                                                @if($inv->status == 'unpaid')
                                                                    {{ $inv->id }},
                                                                @endif
                                                            @endforeach
                                                        ];
                                                    }
                                                }
                                            }">

        <!-- Filter Bar -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <div class="bg-indigo-50 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 p-2 rounded-lg">
                    <i class="fas fa-filter"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white">Filter Tagihan</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Tampilkan berdasarkan periode</p>
                </div>
            </div>
            <form action="{{ route('billing.index') }}" method="GET"
                class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                @if(auth()->user()->role == 'superadmin')
                    <select name="admin_id"
                        class="block w-full sm:w-48 rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6">
                        <option value="">Semua Admin</option>
                        @foreach($admins as $admin)
                            <option value="{{ $admin->id }}" {{ $selectedAdminId == $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }}{{ $admin->id == auth()->id() ? ' (Self)' : '' }}
                            </option>
                        @endforeach
                    </select>
                @endif
                @if(in_array(auth()->user()->role, ['admin', 'superadmin']) && $operators->count() > 0)
                    <select name="operator_id"
                        class="block w-full sm:w-48 rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6">
                        <option value="">Semua Operator</option>
                        @foreach($operators as $op)
                            <option value="{{ $op->id }}" {{ ($selectedOperatorId ?? '') == $op->id ? 'selected' : '' }}>
                                {{ $op->name }} ({{ ucfirst($op->role) }})
                            </option>
                        @endforeach
                    </select>
                @endif
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

        <!-- Stats Overview -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 p-4 shadow-lg shadow-indigo-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-indigo-100">Total Tagihan</dt>
                <dd class="mt-1 text-xl font-bold tracking-tight text-white">
                    Rp {{ number_format($total_bill ?? 0, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-3 top-3 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-file-invoice-dollar fa-2x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-rose-500 to-pink-600 p-4 shadow-lg shadow-rose-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-rose-100">Belum Dibayar</dt>
                <dd class="mt-1 text-xl font-bold tracking-tight text-white">
                    Rp {{ number_format($unpaid_bill ?? 0, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-3 top-3 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-4 shadow-lg shadow-emerald-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-emerald-100">Sudah Dibayar (Manual)</dt>
                <dd class="mt-1 text-xl font-bold tracking-tight text-white">
                    Rp {{ number_format($paid_bill ?? 0, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-3 top-3 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 p-4 shadow-lg shadow-cyan-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-cyan-100">Kelebihan → Saldo</dt>
                <dd class="mt-1 text-xl font-bold tracking-tight text-white">
                    Rp {{ number_format($total_excess_to_balance ?? 0, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-3 top-3 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-wallet fa-2x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-fuchsia-500 to-pink-600 p-4 shadow-lg shadow-fuchsia-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-fuchsia-100">Pendapatan</dt>
                <dd class="mt-1 text-xl font-bold tracking-tight text-white">
                    Rp {{ number_format($total_revenue ?? 0, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-3 top-3 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-hand-holding-usd fa-2x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 p-4 shadow-lg shadow-amber-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-amber-100">Kurang Bayar</dt>
                <dd class="mt-1 text-xl font-bold tracking-tight text-white">
                    Rp {{ number_format($total_underpayment ?? 0, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-3 top-3 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-money-bill-wave fa-2x"></i>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex gap-2">
                <button @click="showCreateModal = true"
                    class="inline-flex items-center rounded-lg bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 shadow-sm hover:bg-slate-50 dark:hover:bg-slate-600 transition-all">
                    <i class="fas fa-plus mr-2"></i> Buat Manual
                </button>
                @if(auth()->user()->role == 'admin' || auth()->user()->role == 'superadmin')
                    <button @click="showGenerateModal = true"
                        class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 transition-all">
                        <i class="fas fa-magic mr-2"></i> Generate Massal
                    </button>
                    <button type="button" onclick="confirmRollbackGenerate()"
                        class="inline-flex items-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 transition-all"
                        title="Batalkan generate tagihan bulan ini dan kembalikan saldo">
                        <i class="fas fa-undo-alt mr-2"></i> Batalkan Generate
                    </button>
                @endif
                <button @click="showPayModal = true" x-show="selectedInvoices.length > 0"
                    class="inline-flex items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 transition-all ml-2"
                    style="display: none;" x-transition>
                    <i class="fas fa-check-double mr-2"></i> Bayar Sekaligus (<span
                        x-text="selectedInvoices.length"></span>)
                </button>

                <button @click="showDueDateModal = true" x-show="selectedInvoices.length > 0"
                    class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 transition-all ml-2"
                    style="display: none;" x-transition>
                    <i class="fas fa-calendar-alt mr-2"></i> Ubah Jatuh Tempo (<span
                        x-text="selectedInvoices.length"></span>)
                </button>

                <button @click="showDeleteModal = true" x-show="selectedInvoices.length > 0"
                    class="inline-flex items-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 transition-all ml-2"
                    style="display: none;" x-transition>
                    <i class="fas fa-trash-alt mr-2"></i> Hapus Tagihan (<span x-text="selectedInvoices.length"></span>)
                </button>
            </div>
        </div>

        <!-- Table Card -->
        <div
            class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm ring-1 ring-slate-900/5 dark:ring-slate-700/50 overflow-hidden">
            <div class="overflow-x-auto p-4">
                <table id="tableBilling" class="w-full text-left border-collapse">
                    <thead>
                        <th
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 rounded-l-lg">
                            <input type="checkbox" @click="toggleAll()"
                                :checked="selectedInvoices.length > 0 && selectedInvoices.length === {{ count($invoices->where('status', 'unpaid')) }}"
                                class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-600">
                        </th>
                        <th
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                            No. Invoice</th>
                        <th
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                            Pelanggan</th>
                        <th
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 hidden sm:table-cell">
                            Bulan/Tahun</th>
                        <th
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                            Tagihan</th>
                        <th
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50">
                            Status</th>
                        <th
                            class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider py-3 px-4 bg-slate-50 dark:bg-slate-700/50 rounded-r-lg text-right">
                            Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($invoices as $inv)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
                                <td class="px-4 py-3 align-middle font-mono text-sm text-slate-600 dark:text-slate-300">
                                    @if($inv->status == 'unpaid')
                                        <input type="checkbox" value="{{ $inv->id }}" x-model.number="selectedInvoices"
                                            class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-600">
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle font-mono text-sm text-slate-600 dark:text-slate-300">
                                    #INV-{{ str_pad($inv->id, 5, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        {{ $inv->customer->name ?? 'Deleted User' }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $inv->customer->internet_number ?? '-' }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 align-middle hidden sm:table-cell text-sm text-slate-600 dark:text-slate-300">
                                    {{ \Carbon\Carbon::parse($inv->due_date)->isoFormat('MMMM Y') }}
                                </td>
                                <td class="px-4 py-3 align-middle font-medium text-slate-700 dark:text-slate-200">
                                    @php
                                        $displayPrice = $inv->price > 0 ? $inv->price : ($inv->customer->monthly_price ?? 0);
                                        $custArrears = $arrearsByCustomer[$inv->customer_id] ?? [];
                                        // Exclude current invoice from arrears display
                                        $custArrears = array_filter($custArrears, fn($a) => $a->id !== $inv->id);
                                    @endphp
                                    Rp {{ number_format($displayPrice, 0, ',', '.') }}
                                    @if(count($custArrears) > 0 && $inv->status == 'unpaid')
                                        <div class="mt-1 space-y-0.5">
                                            @foreach($custArrears as $arrear)
                                                <div
                                                    class="flex items-center gap-1 text-[10px] text-orange-600 dark:text-orange-400 font-medium">
                                                    <i class="fas fa-exclamation-circle text-[8px]"></i>
                                                    <span>{{ \Carbon\Carbon::parse($arrear->due_date)->isoFormat('MMM Y') }}: -Rp
                                                        {{ number_format($arrear->underpayment, 0, ',', '.') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    @if($inv->status == 'paid')
                                        <div class="flex flex-col items-center justify-center gap-1">
                                            <span class="h-3 w-3 rounded-full bg-green-500" title="Lunas" alt="Lunas"></span>
                                            @if(isset($excessByInvoice[$inv->id]) && $excessByInvoice[$inv->id] > 0)
                                                <span class="text-[10px] text-cyan-500 font-semibold whitespace-nowrap">
                                                    Sisa: Rp {{ number_format($excessByInvoice[$inv->id], 0, ',', '.') }}
                                                </span>
                                            @endif
                                            @if(isset($paymentAdminByInvoice[$inv->id]))
                                                <span class="text-[10px] text-indigo-500 dark:text-indigo-400 font-medium whitespace-nowrap">
                                                    <i class="fas fa-user-shield text-[8px]"></i> input by {{ $paymentAdminByInvoice[$inv->id] }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="flex flex-col items-center justify-center gap-1">
                                            @if($inv->amount_paid > 0)
                                                <span class="h-3 w-3 rounded-full bg-yellow-500" title="Dibayar Sebagian"
                                                    alt="Dibayar Sebagian"></span>
                                            @else
                                                <span class="h-3 w-3 rounded-full bg-red-500" title="Belum Bayar"
                                                    alt="Belum Bayar"></span>
                                            @endif
                                            @if($inv->underpayment > 0)
                                                <span class="text-[10px] text-orange-500 font-semibold whitespace-nowrap">
                                                    Kurang: Rp {{ number_format($inv->underpayment, 0, ',', '.') }}
                                                </span>
                                            @endif
                                            @if(isset($paymentAdminByInvoice[$inv->id]))
                                                <span class="text-[10px] text-indigo-500 dark:text-indigo-400 font-medium whitespace-nowrap">
                                                    <i class="fas fa-user-shield text-[8px]"></i> input by {{ $paymentAdminByInvoice[$inv->id] }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('billing.print', $inv->id) }}" target="_blank"
                                            class="p-1.5 text-blue-600 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-md transition-colors"
                                            title="Print Invoice">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @if($inv->status == 'unpaid')
                                            <button type="button" onclick="openSweetAlertPayment({{ $inv->id }})"
                                                class="p-1.5 text-green-600 hover:text-green-700 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-md transition-colors"
                                                title="Bayar Manual">
                                                <i class="fas fa-check-double"></i>
                                            </button>
                                        @else
                                            <form id="form-cancel-{{ $inv->id }}" action="{{ route('billing.cancel', $inv->id) }}"
                                                method="POST" class="inline-block m-0 p-0">
                                                @csrf
                                                <button type="button" onclick="confirmCancel({{ $inv->id }})"
                                                    class="p-1.5 text-orange-500 hover:text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20 rounded-md transition-colors"
                                                    title="Batalkan Bayar">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form id="form-delete-{{ $inv->id }}" action="{{ route('billing.destroy', $inv->id) }}"
                                            method="POST" class="inline-block m-0 p-0">
                                            @csrf @method('DELETE')
                                            <button type="button" onclick="confirmDelete({{ $inv->id }})"
                                                class="p-1.5 text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors"
                                                title="Hapus Invoice">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL CREATE MANUAL (Alpine) -->
        <div x-show="showCreateModal" id="showCreateModalContainer" class="relative z-500" style="display:none;">
            <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm"></div>
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:w-full sm:max-w-lg"
                        @click.away="showCreateModal = false">
                        <form action="{{ route('billing.store') }}" method="POST">
                            @csrf
                            <div class="bg-white dark:bg-slate-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                <h3 class="text-xl font-bold leading-6 text-slate-900 dark:text-white mb-6">Buat Tagihan
                                    Manual</h3>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-900 dark:text-slate-300">Pilih
                                            Pelanggan</label>
                                        <select name="customer_id" id="manualCustomerId"
                                            class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 ring-1 ring-inset ring-slate-300 dark:ring-slate-600 focus:ring-2 focus:ring-primary-600 sm:text-sm sm:leading-6 select2-modal">
                                            @foreach($customers as $c)
                                                <option value="{{ $c->id }}">{{ $c->name }} - {{ $c->internet_number }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Customer Info Panel -->
                                    <div id="manualCustomerInfo" class="rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 p-3 space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-slate-500 dark:text-slate-400">Saldo:</span>
                                            <span id="manualBalanceDisplay" class="font-bold text-emerald-600 dark:text-emerald-400">Rp 0</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-500 dark:text-slate-400">Harga Paket:</span>
                                            <span id="manualPriceDisplay" class="font-semibold text-slate-700 dark:text-slate-200">Rp 0</span>
                                        </div>
                                        <div id="manualArrearsContainer" style="display:none;">
                                            <div class="border-t border-slate-200 dark:border-slate-600 pt-2 mt-1">
                                                <span class="text-xs font-semibold text-orange-600 dark:text-orange-400"><i class="fas fa-exclamation-triangle mr-1"></i>Tunggakan Sebelumnya:</span>
                                                <div id="manualArrearsList" class="mt-1 space-y-0.5"></div>
                                                <div class="flex justify-between mt-1 pt-1 border-t border-dashed border-orange-300 dark:border-orange-600">
                                                    <span class="text-xs text-orange-600 dark:text-orange-400 font-semibold">Total Tunggakan:</span>
                                                    <span id="manualArrearsTotal" class="text-xs font-bold text-orange-600 dark:text-orange-400">Rp 0</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-900 dark:text-slate-300">Bulan</label>
                                            <select id="manualMonth"
                                                class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 ring-1 ring-inset ring-slate-300 dark:ring-slate-600 sm:text-sm">
                                                @for($i = 1; $i <= 12; $i++)
                                                    <option value="{{ $i }}" {{ date('n') == $i ? 'selected' : '' }}>
                                                        {{ DateTime::createFromFormat('!m', $i)->format('F') }}
                                                    </option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div>
                                            <label
                                                class="block text-sm font-medium text-slate-900 dark:text-slate-300">Tahun</label>
                                            <select id="manualYear"
                                                class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 ring-1 ring-inset ring-slate-300 dark:ring-slate-600 sm:text-sm">
                                                @for($y = date('Y') + 1; $y >= date('Y') - 1; $y--)
                                                    <option value="{{ $y }}" {{ date('Y') == $y ? 'selected' : '' }}>{{ $y }}
                                                    </option>
                                                @endfor
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-900 dark:text-slate-300">Jatuh
                                            Tempo (Due Date)</label>
                                        <input type="date" name="due_date" id="manualDueDate" value="{{ date('Y-m-d') }}"
                                            required
                                            class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 ring-1 ring-inset ring-slate-300 dark:ring-slate-600 focus:ring-2 focus:ring-primary-600 sm:text-sm sm:leading-6">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-900 dark:text-slate-300">Nominal
                                            Tagihan
                                            (Opsional)</label>
                                        <input type="number" name="price" id="manualPriceInput"
                                            class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 ring-1 ring-inset ring-slate-300 dark:ring-slate-600 placeholder:text-slate-400 focus:ring-2 focus:ring-primary-600 sm:text-sm sm:leading-6"
                                            placeholder="Kosongkan untuk harga default user">
                                    </div>
                                </div>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                                <button type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-primary-600 px-3 py-2 text-sm font-bold text-white shadow-sm hover:bg-primary-500 sm:ml-3 sm:w-auto">Simpan</button>
                                <button type="button" @click="showCreateModal = false"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-slate-700 px-3 py-2 text-sm font-semibold text-slate-900 dark:text-slate-200 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 sm:mt-0 sm:w-auto">Batal</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL GENERATE MASSAL (Alpine) -->
        <div x-show="showGenerateModal" class="relative z-500" style="display:none;">
            <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm"></div>
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:w-full sm:max-w-lg"
                        @click.away="showGenerateModal = false">
                        <div class="bg-white dark:bg-slate-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div
                                class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/50 mb-4">
                                <i class="fas fa-magic text-primary-600 dark:text-primary-400 text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold leading-6 text-center text-slate-900 dark:text-white mb-2">
                                Generate Tagihan Massal</h3>
                            <p id="genDesc" class="text-sm text-center text-slate-500 dark:text-slate-400 mb-6">Sistem akan
                                membuat
                                tagihan otomatis
                                untuk pelanggan aktif @if(auth()->user()->role == 'superadmin') **Admin yang terpilih**
                                @else **Anda** @endif.
                            </p>

                            <!-- Initial Form -->
                            <div id="genInitial" class="space-y-4">
                                @if(auth()->user()->role == 'superadmin')
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-slate-900 dark:text-slate-300">Pilih
                                            Admin</label>
                                        <select id="genAdminId"
                                            class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 ring-1 ring-inset ring-slate-300 dark:ring-slate-600 sm:text-sm">
                                            <option value="">-- Pilih Admin --</option>
                                            @foreach($admins as $admin)
                                                <option value="{{ $admin->id }}">
                                                    {{ $admin->name }}{{ $admin->id == auth()->id() ? ' (Self)' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-slate-900 dark:text-slate-300">Bulan</label>
                                        <select id="genMonth"
                                            class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 ring-1 ring-inset ring-slate-300 dark:ring-slate-600 sm:text-sm">
                                            @for($i = 1; $i <= 12; $i++)
                                                <option value="{{ $i }}" {{ date('n') == $i ? 'selected' : '' }}>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-slate-900 dark:text-slate-300">Tahun</label>
                                        <select id="genYear"
                                            class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 ring-1 ring-inset ring-slate-300 dark:ring-slate-600 sm:text-sm">
                                            @for($y = date('Y') + 1; $y >= date('Y') - 1; $y--)
                                                <option value="{{ $y }}" {{ date('Y') == $y ? 'selected' : '' }}>{{ $y }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-slate-900 dark:text-slate-300">Jatuh Tempo
                                        (Tanggal Tagihan)</label>
                                    <input type="date" id="genDueDate" value="{{ date('Y-m-d') }}" required
                                        class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 ring-1 ring-inset ring-slate-300 dark:ring-slate-600 focus:ring-2 focus:ring-primary-600 sm:text-sm sm:leading-6">
                                </div>
                                <button type="button" onclick="startGenerate()"
                                    class="mt-6 inline-flex w-full justify-center rounded-md bg-primary-600 px-3 py-2 text-sm font-bold text-white shadow-sm hover:bg-primary-500 w-full">Mulai
                                    Generate</button>
                            </div>

                            <!-- Progress UI -->
                            <div id="genProgress" style="display:none;" class="mt-6 space-y-4">
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5">
                                    <div id="genProgressBar"
                                        class="bg-primary-600 h-2.5 rounded-full transition-all duration-300"
                                        style="width: 0%"></div>
                                </div>
                                <div class="text-xs text-center text-slate-500 dark:text-slate-400 font-mono"
                                    id="genStatusText">Menghubungkan...</div>
                                <ul id="genLog"
                                    class="h-48 overflow-y-auto text-left text-xs bg-slate-50 dark:bg-slate-900 p-3 rounded-lg border border-slate-200 dark:border-slate-700 space-y-1 text-slate-600 dark:text-slate-400">
                                </ul>
                            </div>

                            <!-- Done UI -->
                            <div id="genDone" style="display:none;" class="mt-6">
                                <div class="text-center py-4">
                                    <div
                                        class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/50 mb-4">
                                        <i class="fas fa-check text-green-600 dark:text-green-400 text-xl"></i>
                                    </div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">Proses Selesai!</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" id="genSummaryText"></p>
                                </div>
                                <button onclick="location.reload()"
                                    class="mt-4 inline-flex w-full justify-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 w-full">
                                    Selesai & Refresh
                                </button>
                            </div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="button" @click="showGenerateModal = false" id="btnCancelGen"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-slate-700 px-3 py-2 text-sm font-semibold text-slate-900 dark:text-slate-200 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 sm:mt-0 sm:w-auto">Batal</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- MODAL MASS PAYMENT (Alpine) -->
        <div x-show="showPayModal" class="relative z-500" style="display:none;">
            <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm"></div>
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:w-full sm:max-w-lg"
                        @click.away="showPayModal = false">
                        <div class="bg-white dark:bg-slate-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div
                                class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50 mb-4">
                                <i class="fas fa-check-double text-green-600 dark:text-green-400 text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold leading-6 text-center text-slate-900 dark:text-white mb-2">
                                Pembayaran Massal</h3>
                            <p id="payDesc" class="text-sm text-center text-slate-500 dark:text-slate-400 mb-6">
                                Anda akan memproses pembayaran untuk <span class="font-bold flex-inline"
                                    x-text="selectedInvoices.length"></span> tagihan terpilih.
                            </p>

                            <!-- Pay Initial UI -->
                            <div id="payInitial">
                                <button type="button" @click="startMassPayment(selectedInvoices)"
                                    class="mt-4 inline-flex w-full justify-center rounded-md bg-green-600 px-3 py-2 text-sm font-bold text-white shadow-sm hover:bg-green-500 w-full">
                                    Mulai Proses Pembayaran
                                </button>
                            </div>

                            <!-- Pay Progress UI -->
                            <div id="payProgress" style="display:none;" class="mt-6 space-y-4">
                                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-2.5">
                                    <div id="payProgressBar"
                                        class="bg-green-600 h-2.5 rounded-full transition-all duration-300"
                                        style="width: 0%"></div>
                                </div>
                                <div class="text-xs text-center text-slate-500 dark:text-slate-400 font-mono"
                                    id="payStatusText">Menyiapkan...</div>
                                <ul id="payLog"
                                    class="h-48 overflow-y-auto text-left text-xs bg-slate-50 dark:bg-slate-900 p-3 rounded-lg border border-slate-200 dark:border-slate-700 space-y-1 text-slate-600 dark:text-slate-400">
                                </ul>
                            </div>

                            <!-- Pay Done UI -->
                            <div id="payDone" style="display:none;" class="mt-6">
                                <div class="text-center py-4">
                                    <div
                                        class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/50 mb-4">
                                        <i class="fas fa-check text-green-600 dark:text-green-400 text-xl"></i>
                                    </div>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white">Proses Selesai!</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1" id="paySummaryText"></p>
                                </div>
                                <button onclick="location.reload()"
                                    class="mt-4 inline-flex w-full justify-center rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-500 w-full">
                                    Selesai & Refresh
                                </button>
                            </div>

                        </div>
                        <div class="bg-slate-50 dark:bg-slate-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="button" @click="showPayModal = false" id="btnCancelPay"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-slate-700 px-3 py-2 text-sm font-semibold text-slate-900 dark:text-slate-200 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 sm:mt-0 sm:w-auto">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL MASS DELETE (Alpine) -->
        <div x-show="showDeleteModal" class="relative z-500" style="display:none;">
            <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm"></div>
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:w-full sm:max-w-lg"
                        @click.away="showDeleteModal = false">
                        <div class="bg-white dark:bg-slate-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div
                                class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/50 mb-4">
                                <i class="fas fa-trash-alt text-red-600 dark:text-red-400 text-xl"></i>
                            </div>
                            <h3 class="text-xl font-bold leading-6 text-center text-slate-900 dark:text-white mb-2">Hapus
                                Tagihan Massal</h3>
                            <p class="text-sm text-center text-slate-500 dark:text-slate-400 mb-6">Pilih metode penghapusan
                                tagihan yang Anda inginkan. Tindakan ini tidak dapat dibatalkan.</p>

                            <div class="space-y-4">
                                <!-- Option 1: Delete Selected -->
                                <form action="{{ route('billing.bulkDestroy') }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="type" value="selected">
                                    @foreach($customers as $c)
                                        <!-- We need to pass IDs, so we use JS to append selected IDs to this form or simpler: just loop IDs in hidden inputs -->
                                    @endforeach
                                    <!-- A more efficient way: use x-data to bind selectedInvoices to a hidden input array -->
                                    <template x-for="id in selectedInvoices">
                                        <input type="hidden" name="ids[]" :value="id">
                                    </template>

                                    <button type="submit" :disabled="selectedInvoices.length === 0"
                                        class="w-full rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/20 px-4 py-4 text-left shadow-sm hover:bg-red-100 dark:hover:bg-red-900/30 transition-all group disabled:opacity-50 disabled:cursor-not-allowed">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-semibold text-red-700 dark:text-red-400">Hapus <span
                                                        x-text="selectedInvoices.length"></span> Tagihan Terpilih</p>
                                                <p class="text-xs text-red-600/70 dark:text-red-400/70 mt-1">Hanya menghapus
                                                    item yang Anda centang.</p>
                                            </div>
                                            <i
                                                class="fas fa-check-circle text-red-300 group-hover:text-red-500 transition-colors text-xl"></i>
                                        </div>
                                    </button>
                                </form>

                                <!-- Option 2: Delete All in Month -->
                                <form action="{{ route('billing.bulkDestroy') }}" method="POST"
                                    onsubmit="return confirm('PERINGATAN: Semua tagihan pada bulan ini akan dihapus permanen! Lanjutkan?');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="type" value="all">
                                    <input type="hidden" name="month" value="{{ $month }}">
                                    <input type="hidden" name="year" value="{{ $year }}">

                                    <button type="submit"
                                        class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-700 px-4 py-4 text-left shadow-sm hover:bg-slate-50 dark:hover:bg-slate-600 transition-all group">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-semibold text-slate-800 dark:text-white">Hapus Semua Tagihan
                                                    Bulan Ini</p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                                    Bulan: {{ DateTime::createFromFormat('!m', $month)->format('F') }}
                                                    {{ $year }}
                                                </p>
                                            </div>
                                            <i
                                                class="fas fa-calendar-times text-slate-300 group-hover:text-slate-500 transition-colors text-xl"></i>
                                        </div>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="button" @click="showDeleteModal = false"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-slate-700 px-3 py-2 text-sm font-semibold text-slate-900 dark:text-slate-200 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 sm:mt-0 sm:w-auto">Batal</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL MASS UPDATE DUE DATE (Alpine) -->
        <div x-show="showDueDateModal" class="relative z-500" style="display:none;">
            <div class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm"></div>
            <div class="fixed inset-0 z-10 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 text-left shadow-xl transition-all sm:w-full sm:max-w-lg"
                        @click.away="showDueDateModal = false">
                        <form action="{{ route('billing.bulkUpdateDueDate') }}" method="POST">
                            @csrf
                            <div class="bg-white dark:bg-slate-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                <div
                                    class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/50 mb-4">
                                    <i class="fas fa-calendar-edit text-blue-600 dark:text-blue-400 text-xl"></i>
                                </div>
                                <h3 class="text-xl font-bold leading-6 text-center text-slate-900 dark:text-white mb-2">
                                    Ubah Jatuh Tempo Massal</h3>
                                <p class="text-sm text-center text-slate-500 dark:text-slate-400 mb-6">
                                    Anda akan mengubah tanggal FALL DUE/TANGGAL BAYAR pada <span class="font-bold"
                                        x-text="selectedInvoices.length"></span> tagihan terpilih.
                                </p>

                                <div class="space-y-4">
                                    <template x-for="id in selectedInvoices">
                                        <input type="hidden" name="ids[]" :value="id">
                                    </template>

                                    <div>
                                        <label class="block text-sm font-medium text-slate-900 dark:text-slate-300">Pilih
                                            Tanggal Baru</label>
                                        <input type="date" name="due_date" required
                                            class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 dark:text-white dark:bg-slate-700 ring-1 ring-inset ring-slate-300 dark:ring-slate-600 focus:ring-2 focus:ring-primary-600 sm:text-sm sm:leading-6">
                                    </div>
                                </div>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                                <button type="submit"
                                    class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-500 sm:ml-3 sm:w-auto">Update</button>
                                <button type="button" @click="showDueDateModal = false"
                                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-slate-700 px-3 py-2 text-sm font-semibold text-slate-900 dark:text-slate-200 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600 sm:mt-0 sm:w-auto">Batal</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

@endsection

    @push('styles')
        <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.tailwindcss.css">
        <!-- Select2 styling if used previously, we can replace with basic select for simplicity or keep Select2 -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <style>
            /* Select2 Tailwind Fix */
            .select2-container .select2-selection--single {
                height: 38px;
                border-color: #d1d5db;
                border-radius: 0.375rem;
                padding-top: 5px;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                top: 6px;
            }

            /* Dark Mode Fix for Select2 */
            .dark .select2-container--default .select2-selection--single {
                background-color: #334155;
                /* slate-700 */
                border-color: #475569;
                /* slate-600 */
                color: #f8fafc;
                /* slate-50 */
            }

            .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
                color: #f8fafc;
            }

            .dark .select2-dropdown {
                background-color: #1e293b;
                /* slate-800 */
                border-color: #475569;
                /* slate-600 */
                color: #f8fafc;
            }

            .dark .select2-container--default .select2-results__option {
                color: #cbd5e1;
                /* slate-300 */
            }

            .dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
                background-color: #4f46e5;
                /* primary-600 */
                color: white;
            }

            .dark .select2-container--default .select2-search--dropdown .select2-search__field {
                background-color: #334155;
                border-color: #475569;
                color: white;
            }

            /* General Visibility Fix */
            .select2-results__option {
                padding: 8px 12px;
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
        <script src="https://cdn.datatables.net/2.1.8/js/dataTables.tailwindcss.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
            $(document).ready(function () {
                $('#tableBilling').DataTable({ responsive: true });
                // Init Select2 inside modal
                $('.select2-modal').select2({
                    width: '100%',
                    dropdownParent: $('#showCreateModalContainer')
                });

                // Customer data map for manual invoice modal
                const customerDataMap = {
                    @foreach($customers as $c)
                    {{ $c->id }}: {
                        balance: {{ (float) $c->balance }},
                        price: {{ (float) $c->monthly_price }},
                        arrears: [
                            @php
                                $custArrList = \App\Models\Invoice::where('customer_id', $c->id)
                                    ->where('underpayment', '>', 0)
                                    ->orderBy('due_date', 'asc')
                                    ->get();
                            @endphp
                            @foreach($custArrList as $arr)
                            { period: '{{ \Carbon\Carbon::parse($arr->due_date)->isoFormat("MMM Y") }}', amount: {{ (float) $arr->underpayment }} },
                            @endforeach
                        ]
                    },
                    @endforeach
                };

                function updateManualCustomerInfo(customerId) {
                    const data = customerDataMap[customerId];
                    if (!data) return;

                    const fmtRp = (v) => 'Rp ' + Number(v).toLocaleString('id-ID');

                    $('#manualBalanceDisplay').text(fmtRp(data.balance));
                    $('#manualPriceDisplay').text(fmtRp(data.price));

                    // Update balance color
                    if (data.balance > 0) {
                        $('#manualBalanceDisplay').removeClass('text-red-500 dark:text-red-400').addClass('text-emerald-600 dark:text-emerald-400');
                    } else {
                        $('#manualBalanceDisplay').removeClass('text-emerald-600 dark:text-emerald-400').addClass('text-red-500 dark:text-red-400');
                    }

                    // Arrears
                    const arrearsContainer = $('#manualArrearsContainer');
                    const arrearsList = $('#manualArrearsList');
                    arrearsList.empty();

                    if (data.arrears.length > 0) {
                        let totalArrears = 0;
                        data.arrears.forEach(function(arr) {
                            totalArrears += arr.amount;
                            arrearsList.append(`
                                <div class="flex justify-between text-[11px]">
                                    <span class="text-orange-500 dark:text-orange-400"><i class="fas fa-exclamation-circle text-[8px] mr-1"></i>${arr.period}</span>
                                    <span class="font-medium text-orange-600 dark:text-orange-400">${fmtRp(arr.amount)}</span>
                                </div>
                            `);
                        });
                        $('#manualArrearsTotal').text(fmtRp(totalArrears));
                        arrearsContainer.show();
                    } else {
                        arrearsContainer.hide();
                    }
                }

                // Listen to Select2 change + native change
                $('#manualCustomerId').on('change', function() {
                    updateManualCustomerInfo($(this).val());
                });

                // Trigger on first load
                const firstCustomerId = $('#manualCustomerId').val();
                if (firstCustomerId) {
                    updateManualCustomerInfo(firstCustomerId);
                }

                // Sync Manual Due Date when Month/Year changes
                $('#manualMonth, #manualYear').on('change', function () {
                    const month = $('#manualMonth').val();
                    const year = $('#manualYear').val();
                    // Get current day from manualDueDate or default to today's day
                    const currentVal = $('#manualDueDate').val();
                    let day = new Date().getDate();
                    if (currentVal) {
                        day = new Date(currentVal).getDate();
                    }

                    // Format YYYY-MM-DD
                    const formattedMonth = month.toString().padStart(2, '0');
                    const formattedDay = day.toString().padStart(2, '0');
                    $('#manualDueDate').val(`${year}-${formattedMonth}-${formattedDay}`);
                });
            });

            async function startGenerate() {
                const adminId = $('#genAdminId').val();
                const month = $('#genMonth').val();
                const year = $('#genYear').val();
                const dueDate = $('#genDueDate').val();
                const log = $('#genLog');

                @if(auth()->user()->role == 'superadmin')
                    if (!adminId) {
                        alert('Pilih Admin terlebih dahulu!');
                        return;
                    }
                @endif

                                        if (!dueDate) {
                    alert('Pilih tanggal jatuh tempo!');
                    return;
                }

                // UI Switch
                $('#genInitial').hide();
                $('#genDesc').hide();
                $('#genProgress').show();
                $('#btnCancelGen').hide();
                log.empty().append('<li><span class="text-blue-500">[INFO]</span> Mengambil daftar pelanggan...</li>');

                try {
                    // 1. Get List
                    const adminIdParam = adminId ? `&admin_id=${adminId}` : '';
                    const listResp = await fetch(`{{ route('billing.list') }}?month=${month}&year=${year}${adminIdParam}`);
                    const listData = await listResp.json();
                    const customers = listData.customers;
                    const total = customers.length;

                    if (total === 0) {
                        log.append('<li><span class="text-yellow-500">[WARN]</span> Tidak ada pelanggan aktif ditemukan.</li>');
                        $('#genStatusText').text('Tidak ada data.');
                        $('#btnCancelGen').show();
                        return;
                    }

                    log.append(`<li><span class="text-blue-500">[INFO]</span> Ditemukan ${total} pelanggan. Memulai proses...</li>`);

                    let created = 0;
                    let skipped = 0;
                    let error = 0;

                    // 2. Process One by One
                    for (let i = 0; i < total; i++) {
                        const customer = customers[i];
                        const progress = Math.round(((i + 1) / total) * 100);

                        $('#genProgressBar').css('width', progress + '%');
                        $('#genStatusText').text(`Memproses ${i + 1}/${total} (${progress}%)`);

                        try {
                            const res = await fetch(`{{ route('billing.process') }}`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    customer_id: customer.id,
                                    admin_id: adminId, // Send admin_id for superadmin check
                                    month,
                                    year,
                                    due_date: dueDate
                                })
                            });

                            const data = await res.json();
                            if (data.status === 'created') {
                                created++;
                                log.append(`<li><span class="text-green-500">[OK]</span> ${customer.name}: Tagihan dibuat.</li>`);
                            } else if (data.status === 'auto_paid') {
                                created++;
                                log.append(`<li><span class="text-emerald-500">[AUTO LUNAS]</span> ${customer.name}: Tagihan dibuat & lunas otomatis dari saldo.${data.message || ''}</li>`);
                            } else if (data.status === 'partial_balance') {
                                created++;
                                log.append(`<li><span class="text-amber-500">[SALDO TERPAKAI]</span> ${customer.name}: Tagihan dibuat.${data.message || ''}</li>`);
                            } else if (data.status === 'skipped') {
                                skipped++;
                                log.append(`<li><span class="text-slate-400">[SKIP]</span> ${customer.name}: Sudah ada tagihan.</li>`);
                            } else {
                                error++;
                                log.append(`<li><span class="text-red-500">[ERR]</span> ${customer.name}: ${data.message || 'Gagal memproses.'}</li>`);
                            }
                        } catch (e) {
                            error++;
                            log.append(`<li><span class="text-red-500">[ERR]</span> ${customer.name}: Error koneksi.</li>`);
                        }

                        // Auto scroll log
                        log.scrollTop(log[0].scrollHeight);
                    }

                    // 3. Finalize
                    $('#genProgress').hide();
                    $('#genDone').show();
                    $('#genSummaryText').text(`Selesai: ${created} dibuat, ${skipped} dilewati, ${error} gagal.`);

                } catch (err) {
                    log.append(`<li><span class="text-red-500">[FATAL]</span> Sistem error: ${err.message}</li>`);
                    $('#genStatusText').text('Gagal!');
                    $('#btnCancelGen').show();
                }
            }

            async function startMassPayment(invoiceIds) {
                const log = $('#payLog');

                // UI Switch
                $('#payInitial').hide();
                $('#payDesc').hide();
                $('#payProgress').show();
                $('#btnCancelPay').hide();
                log.empty().append('<li><span class="text-blue-500">[INFO]</span> Memulai pembayaran massal...</li>');

                const total = invoiceIds.length;
                let success = 0;
                let skipped = 0;
                let error = 0;

                for (let i = 0; i < total; i++) {
                    const invId = invoiceIds[i];
                    const progress = Math.round(((i + 1) / total) * 100);

                    $('#payProgressBar').css('width', progress + '%');
                    $('#payStatusText').text(`Memproses ${i + 1}/${total} (${progress}%)`);

                    try {
                        const res = await fetch(`/billing/${invId}/pay-ajax`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });

                        const data = await res.json();

                        if (data.status === 'success') {
                            success++;
                            log.append(`<li><span class="text-green-500">[OK]</span> ${data.customer}: Lunas.</li>`);
                        } else if (data.status === 'skipped') {
                            skipped++;
                            log.append(`<li><span class="text-yellow-500">[SKIP]</span> ${data.customer}: Sudah lunas.</li>`);
                        } else {
                            error++;
                            log.append(`<li><span class="text-red-500">[ERR]</span> ${data.customer || 'Unknown'}: ${data.message}</li>`);
                        }

                    } catch (e) {
                        error++;
                        log.append(`<li><span class="text-red-500">[ERR]</span> ID ${invId}: Koneksi Gagal.</li>`);
                    }

                    // Auto scroll log
                    log.scrollTop(log[0].scrollHeight);
                }

                // Finalize
                $('#payProgress').hide();
                $('#payDone').show();
                $('#paySummaryText').text(`Selesai: ${success} Sukses, ${skipped} Dilewati, ${error} Gagal.`);
            }
            // === SWEETALERT2 SINGLE PAYMENT LOGIC ===
            function openSweetAlertPayment(invoiceId) {
                Swal.fire({
                    title: 'Memuat data...',
                    text: 'Silakan tunggu sebentar',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Fetch invoice info
                $.ajax({
                    url: `/billing/${invoiceId}/info`,
                    method: 'GET',
                    success: function (data) {
                        Swal.close();

                        // Build arrears HTML section
                        let arrearsHtml = '';
                        if (data.arrears && data.arrears.length > 0) {
                            arrearsHtml = `
                                    <div class="text-left mt-4 mb-4">
                                        <label class="block text-sm font-bold text-gray-800 mb-2">
                                            <i class="fas fa-exclamation-triangle text-orange-500 mr-1"></i> Kurang Bayar Sebelumnya
                                        </label>
                                        <div class="space-y-2 max-h-48 overflow-y-auto" id="swal_arrears_container">
                                `;
                            data.arrears.forEach((arr, idx) => {
                                arrearsHtml += `
                                        <div class="border border-orange-200 rounded-lg p-3 bg-orange-50">
                                            <label class="flex items-start gap-2 cursor-pointer">
                                                <input type="checkbox" class="arrear-checkbox mt-0.5 w-4 h-4 text-orange-600 rounded focus:ring-orange-500"
                                                    data-arrear-id="${arr.id}"
                                                    data-arrear-amount="${arr.underpayment}"
                                                    data-arrear-idx="${idx}"
                                                    onchange="toggleArrearInput(this)">
                                                <div class="flex-1">
                                                    <div class="flex justify-between items-center">
                                                        <span class="text-sm font-medium text-gray-700">${arr.period}</span>
                                                        <span class="text-sm font-bold text-orange-600">${arr.underpayment_formatted}</span>
                                                    </div>
                                                    <div class="text-xs text-gray-500">Sudah dibayar: Rp ${Number(arr.amount_paid).toLocaleString('id-ID')}</div>
                                                    <div class="mt-2 hidden" id="arrear_input_wrapper_${idx}">
                                                        <label class="block text-xs font-medium text-gray-600 mb-1">Jumlah yang dibayar (Rp)</label>
                                                        <input type="number" id="arrear_amount_${idx}" class="w-full px-3 py-1.5 text-sm border border-orange-300 rounded-md focus:ring-orange-500 focus:border-orange-500"
                                                            value="${arr.underpayment}" min="1" max="${arr.underpayment}"
                                                            onchange="updateArrearTotal()" oninput="updateArrearTotal()">
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    `;
                            });
                            arrearsHtml += `
                                        </div>
                                        <div class="mt-2 text-right text-xs font-semibold text-orange-700" id="swal_arrear_total_text" style="display:none;">Total Tunggakan: <span id="swal_arrear_total_value">Rp 0</span></div>
                                    </div>
                                `;
                        }

                        let htmlContent = `
                                <div class="text-left mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200 text-sm">
                                    <div class="flex justify-between mb-1"><span class="text-gray-500">Pelanggan:</span> <span class="font-semibold">${data.customer_name}</span></div>
                                    <div class="flex justify-between mb-1"><span class="text-gray-500">Total Tagihan:</span> <span class="font-bold text-red-600">${data.price_formatted}</span></div>
                                    ${data.amount_paid > 0 ? `<div class="flex justify-between mb-1"><span class="text-gray-500">Sudah Dibayar (Saldo):</span> <span class="font-medium text-green-600">${data.amount_paid_formatted}</span></div>` : ''}
                                    <div class="flex justify-between mb-1"><span class="text-gray-500">Sisa Tagihan:</span> <span class="font-bold ${data.remaining_to_pay > 0 ? 'text-orange-600' : 'text-green-600'}">${data.remaining_to_pay_formatted}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Saldo:</span> <span class="${data.balance_sufficient ? 'text-green-600 font-bold' : 'text-gray-600'}">${data.balance_formatted}</span></div>
                                </div>

                                <div class="mb-4 text-left">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Metode Pembayaran</label>
                                    <div class="flex flex-col gap-2">
                                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                            <input type="radio" name="swal_pay_method" value="manual" class="w-4 h-4 text-blue-600 focus:ring-blue-500" checked onchange="toggleSwalPayMethod()">
                                            <span class="ml-2 font-medium text-gray-700">Bayar Manual</span>
                                        </label>
                                        <label class="flex items-center p-3 border rounded-lg ${!data.balance_sufficient ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-gray-50'}">
                                            <input type="radio" name="swal_pay_method" value="balance" class="w-4 h-4 text-blue-600 focus:ring-blue-500" ${!data.balance_sufficient ? 'disabled' : ''} onchange="toggleSwalPayMethod()">
                                            <span class="ml-2 font-medium text-gray-700">Pakai Saldo</span>
                                        </label>
                                    </div>
                                </div>

                                <div id="swal_amount_container" class="text-left">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Pembayaran (Rp)</label>
                                    <input type="number" id="swal_amount" class="swal2-input !m-0 w-full" value="${data.remaining_to_pay}" style="height: 40px; font-size: 1rem;">
                                    <p class="text-xs text-orange-600 mt-1 hidden" id="swal_underpayment_warning">Jika bayar kurang, sisa akan diakumulasi ke bulan depan.</p>
                                </div>

                                ${arrearsHtml}
                            `;

                        Swal.fire({
                            title: 'Proses Pembayaran',
                            html: htmlContent,
                            showCancelButton: true,
                            confirmButtonText: '<i class="fas fa-check"></i> Proses',
                            cancelButtonText: 'Batal',
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            focusConfirm: false,
                            width: '540px',
                            didOpen: () => {
                                // Add event listener for amount to show warning
                                const amountInput = document.getElementById('swal_amount');
                                const warningText = document.getElementById('swal_underpayment_warning');
                                amountInput.addEventListener('input', function () {
                                    if (parseFloat(this.value) < data.remaining_to_pay) {
                                        warningText.classList.remove('hidden');
                                    } else {
                                        warningText.classList.add('hidden');
                                    }
                                });
                            },
                            preConfirm: () => {
                                const method = document.querySelector('input[name="swal_pay_method"]:checked').value;
                                let amount = 0;

                                if (method === 'manual') {
                                    amount = document.getElementById('swal_amount').value;
                                    if (!amount || amount <= 0) {
                                        Swal.showValidationMessage('Jumlah pembayaran tidak valid');
                                        return false;
                                    }
                                } else {
                                    amount = data.remaining_to_pay;
                                }

                                // Collect arrears payments
                                let arrearsPayments = [];
                                document.querySelectorAll('.arrear-checkbox:checked').forEach(function (cb) {
                                    const idx = cb.dataset.arrearIdx;
                                    const arrearId = cb.dataset.arrearId;
                                    const arrearAmountInput = document.getElementById('arrear_amount_' + idx);
                                    const arrearAmount = arrearAmountInput ? parseFloat(arrearAmountInput.value) : 0;
                                    if (arrearAmount > 0) {
                                        arrearsPayments.push({ id: parseInt(arrearId), amount: arrearAmount });
                                    }
                                });

                                return { method: method, amount: parseFloat(amount), arrears_payments: arrearsPayments };
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                processSweetAlertPayment(invoiceId, result.value.method, result.value.amount, result.value.arrears_payments);
                            }
                        });
                    },
                    error: function () {
                        Swal.fire('Error', 'Gagal memuat data invoice', 'error');
                    }
                });
            }

            // Toggle arrear input visibility
            window.toggleArrearInput = function (checkbox) {
                const idx = checkbox.dataset.arrearIdx;
                const wrapper = document.getElementById('arrear_input_wrapper_' + idx);
                if (checkbox.checked) {
                    wrapper.classList.remove('hidden');
                } else {
                    wrapper.classList.add('hidden');
                }
                updateArrearTotal();
            };

            // Update total arrears display
            window.updateArrearTotal = function () {
                let total = 0;
                let anyChecked = false;
                document.querySelectorAll('.arrear-checkbox:checked').forEach(function (cb) {
                    anyChecked = true;
                    const idx = cb.dataset.arrearIdx;
                    const input = document.getElementById('arrear_amount_' + idx);
                    total += input ? parseFloat(input.value) || 0 : 0;
                });
                const totalText = document.getElementById('swal_arrear_total_text');
                const totalValue = document.getElementById('swal_arrear_total_value');
                if (totalText) {
                    totalText.style.display = anyChecked ? 'block' : 'none';
                }
                if (totalValue) {
                    totalValue.textContent = 'Rp ' + total.toLocaleString('id-ID');
                }
            };

            // Global toggle helper for sweetalert content
            window.toggleSwalPayMethod = function () {
                const method = document.querySelector('input[name="swal_pay_method"]:checked').value;
                const container = document.getElementById('swal_amount_container');
                if (method === 'manual') {
                    container.style.display = 'block';
                } else {
                    container.style.display = 'none';
                }
            };

            function processSweetAlertPayment(invoiceId, method, amount, arrearsPayments = []) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mohon tunggu',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: `/billing/${invoiceId}/pay-method`,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    data: JSON.stringify({
                        payment_method: method,
                        amount_paid: amount,
                        arrears_payments: arrearsPayments
                    }),
                    contentType: 'application/json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: res.message,
                            }).then(() => {
                                location.reload();
                            });
                        } else if (res.status === 'partial') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Pembayaran Sebagian',
                                text: res.message,
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Gagal', res.message, 'error');
                        }
                    },
                    error: function (err) {
                        let msg = 'Terjadi kesalahan jaringan';
                        if (err.responseJSON && err.responseJSON.message) {
                            msg = err.responseJSON.message;
                        }
                        Swal.fire('Gagal', msg, 'error');
                    }
                });
            }

            function confirmCancel(id) {
                Swal.fire({
                    title: 'Batalkan Pembayaran?',
                    text: "Status tagihan akan dikembalikan menjadi UNPAID dan koneksi akan dinonaktifkan.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f97316',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Ya, Batalkan!',
                    cancelButtonText: 'Tutup'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Memproses...',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });
                        document.getElementById('form-cancel-' + id).submit();
                    }
                });
            }

            function confirmDelete(id) {
                Swal.fire({
                    title: 'Hapus Invoice?',
                    text: "Invoice ini akan dihapus secara permanen dan tidak dapat dikembalikan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Menghapus...',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });
                        document.getElementById('form-delete-' + id).submit();
                    }
                });
            }

            function confirmRollbackGenerate() {
                const month = {{ $month }};
                const year = {{ $year }};
                const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

                Swal.fire({
                    title: 'Batalkan Generate?',
                    html: `<p class="text-sm text-gray-600">Semua tagihan periode <strong>${monthNames[month]} ${year}</strong> akan dihapus.</p>
                               <p class="text-sm text-orange-600 mt-2"><i class="fas fa-info-circle mr-1"></i>Saldo pelanggan yang terpotong otomatis saat generate akan dikembalikan.</p>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d97706',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: '<i class="fas fa-undo-alt mr-1"></i> Ya, Batalkan!',
                    cancelButtonText: 'Tutup'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Membatalkan generate...',
                            text: 'Menghapus tagihan & mengembalikan saldo...',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });

                        $.ajax({
                            url: '{{ route("billing.rollbackGenerate") }}',
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            data: JSON.stringify({
                                month: month,
                                year: year,
                                @if(auth()->user()->role == 'superadmin')
                                    admin_id: '{{ $selectedAdminId }}'
                                @endif
                                }),
                            contentType: 'application/json',
                                success: function(res) {
                                    if (res.status === 'success') {
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
                    });
                }

        </script>
    @endpush