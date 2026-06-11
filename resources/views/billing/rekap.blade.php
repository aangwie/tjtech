@extends('layouts.app2')

@section('title', 'Rekap Tagihan')
@section('header', 'Rekap Tagihan')
@section('subheader', 'Ringkasan tagihan per periode dan keseluruhan.')

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
                    <p class="text-xs text-slate-500 dark:text-slate-400">Ringkasan berdasarkan periode</p>
                </div>
            </div>
            <form action="{{ route('billing.rekap') }}" method="GET"
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

        <!-- Period Label -->
        <div class="mb-4">
            <h4 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                <i class="fas fa-calendar-alt mr-1"></i>
                Periode: {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}
            </h4>
        </div>

        <!-- Stats Baris 1: Per Periode (Bulan/Tahun) -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 p-5 shadow-lg shadow-amber-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-amber-100">Kurang Bayar</dt>
                <dd class="mt-2 text-2xl font-bold tracking-tight text-white">
                    Rp {{ number_format($periodUnderpayment, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-money-bill-wave fa-2x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-fuchsia-500 to-pink-600 p-5 shadow-lg shadow-fuchsia-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-fuchsia-100">Dibayar Manual</dt>
                <dd class="mt-2 text-2xl font-bold tracking-tight text-white">
                    Rp {{ number_format($periodDibayarManual, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-hand-holding-usd fa-2x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-cyan-500 to-blue-600 p-5 shadow-lg shadow-cyan-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-cyan-100">Kelebihan → Saldo</dt>
                <dd class="mt-2 text-2xl font-bold tracking-tight text-white">
                    Rp {{ number_format($periodExcessToBalance, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-wallet fa-2x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-5 shadow-lg shadow-emerald-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-emerald-100">Pendapatan</dt>
                <dd class="mt-2 text-2xl font-bold tracking-tight text-white">
                    Rp {{ number_format($periodRevenue, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
            </div>
        </div>

        <!-- Divider -->
        <div class="mb-4">
            <h4 class="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                <i class="fas fa-globe mr-1"></i>
                Total Keseluruhan (Semua Periode)
            </h4>
        </div>

        <!-- Stats Baris 2: Grand Total (Semua Periode) -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 p-5 shadow-lg shadow-indigo-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-indigo-100">Total Tagihan (Semua Periode)</dt>
                <dd class="mt-2 text-2xl font-bold tracking-tight text-white">
                    Rp {{ number_format($grandTotalTagihan, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-file-invoice-dollar fa-2x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 p-5 shadow-lg shadow-emerald-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-emerald-100">Total Sudah Bayar</dt>
                <dd class="mt-2 text-2xl font-bold tracking-tight text-white">
                    Rp {{ number_format($grandTotalBayar, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
            </div>
            <div
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-600 p-5 shadow-lg shadow-blue-500/20 text-white hover:shadow-xl transition-shadow group">
                <dt class="truncate text-xs font-medium text-blue-100">Total Dibayar Pakai Saldo</dt>
                <dd class="mt-2 text-2xl font-bold tracking-tight text-white">
                    Rp {{ number_format($grandTotalDibayarSaldo, 0, ',', '.') }}
                </dd>
                <div
                    class="absolute right-4 top-4 text-white/10 group-hover:text-white/20 transition-all transform group-hover:scale-110">
                    <i class="fas fa-wallet fa-2x"></i>
                </div>
            </div>
        </div>

    </div>

@endsection
