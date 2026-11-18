<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';

const props = defineProps({
    logs: Object,
    filters: Object,
    stats: Object,
    availableModules: Array,
    availableActions: Array,
    availableRoles: Array,
});

// Filter states
const search = ref(props.filters.search || '');
const filterModule = ref(props.filters.module || 'all');
const filterRole = ref(props.filters.role || 'all');
const filterAction = ref(props.filters.action || 'all');
const filterPeriod = ref(props.filters.period || 'all');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

// Active selected log for inspection modal
const selectedLog = ref(null);
const showDetailModal = ref(false);

const openDetailModal = (log) => {
    selectedLog.value = log;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    selectedLog.value = null;
    showDetailModal.value = false;
};

const applyFilter = () => {
    router.get(route('activity-logs.index'), {
        search: search.value || undefined,
        module: filterModule.value !== 'all' ? filterModule.value : undefined,
        role: filterRole.value !== 'all' ? filterRole.value : undefined,
        action: filterAction.value !== 'all' ? filterAction.value : undefined,
        period: filterPeriod.value !== 'all' ? filterPeriod.value : undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true, replace: true });
};

const selectPeriod = (period) => {
    filterPeriod.value = period;
    if (period !== 'custom') {
        dateFrom.value = '';
        dateTo.value = '';
    }
    applyFilter();
};

const resetFilter = () => {
    search.value = '';
    filterModule.value = 'all';
    filterRole.value = 'all';
    filterAction.value = 'all';
    filterPeriod.value = 'all';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilter();
};

// Badges helper based on action type
const getActionBadgeClass = (action) => {
    if (!action) return 'bg-slate-100 text-slate-700 border-slate-200';
    if (action.includes('TAMBAH') || action.includes('DISUTUJUI') || action.includes('VALIDASI')) {
        return 'bg-emerald-50 text-emerald-800 border-emerald-200';
    }
    if (action.includes('TOLAK') || action.includes('HAPUS') || action.includes('SUSPEND')) {
        return 'bg-rose-50 text-rose-800 border-rose-200';
    }
    if (action.includes('UPDATE') || action.includes('RESET') || action.includes('TOGGLE')) {
        return 'bg-amber-50 text-amber-800 border-amber-200';
    }
    if (action.includes('LOGIN') || action.includes('LOGOUT')) {
        return 'bg-sky-50 text-sky-800 border-sky-200';
    }
    return 'bg-slate-100 text-slate-700 border-slate-200';
};

// Role badge helper
const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'Superadmin':
            return 'bg-purple-50 text-purple-700 border-purple-200';
        case 'Kasudin':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'Kasi':
            return 'bg-teal-50 text-teal-700 border-teal-200';
        case 'PJLP':
            return 'bg-slate-100 text-slate-700 border-slate-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
};

// Format date helper
const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    } catch (e) {
        return dateStr;
    }
};

// Top module statistic
const topModule = computed(() => {
    if (!props.stats?.modules) return '-';
    const entries = Object.entries(props.stats.modules);
    if (entries.length === 0) return '-';
    return `${entries[0][0]} (${entries[0][1]})`;
});
</script>

<template>
    <Head title="Audit Trail & Log Aktivitas Sistem" />

    <AuthenticatedLayout>
        <!-- Page Header -->
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            Sistem Audit
                        </span>
                        <span class="text-xs text-slate-400">•</span>
                        <span class="text-xs font-medium text-slate-500">Rekam Jejak & Keamanan</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight mt-1">
                        Audit Trail & Log Aktivitas
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Rekaman transparan seluruh aktivitas pengguna, perubahan data master, dan aksi sistem secara real-time.
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <button @click="resetFilter"
                        type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-medium rounded-lg text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-xs transition">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Refresh Log</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- ============================================================ -->
            <!-- 1. STATISTIK RINGKASAN AUDIT -->
            <!-- ============================================================ -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Log -->
                <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">Total Log Tercatat</span>
                        <span class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold font-mono text-slate-900 tracking-tight">
                        {{ stats.total.toLocaleString('id-ID') }}
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">
                        Keseluruhan jejak audit sistem
                    </div>
                </div>

                <!-- Log Hari Ini -->
                <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">Aktivitas Hari Ini</span>
                        <span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold font-mono text-slate-900 tracking-tight">
                        {{ stats.today.toLocaleString('id-ID') }}
                    </div>
                    <div class="mt-1 text-[11px] text-emerald-600 font-medium">
                        Aktivitas hari ini (00:00 - sekarang)
                    </div>
                </div>

                <!-- 7 Hari Terakhir -->
                <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">7 Hari Terakhir</span>
                        <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-2 text-2xl font-bold font-mono text-slate-900 tracking-tight">
                        {{ stats.seven_days.toLocaleString('id-ID') }}
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">
                        Intensitas aktivitas mingguan
                    </div>
                </div>

                <!-- Modul Teraktif -->
                <div class="p-4 bg-white border border-slate-200 rounded-xl shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">Modul Teraktif</span>
                        <span class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-2 text-base font-bold text-slate-900 truncate">
                        {{ topModule }}
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500 truncate">
                        Berdasarkan total transaksi sistem
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- 2. FILTER & PENCARIAN MULTI-AXIS -->
            <!-- ============================================================ -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-xs p-4 sm:p-5 space-y-4">
                <!-- Baris Atas: Quick Period Pills -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-xs font-semibold text-slate-600 mr-2">Periode Waktu:</span>
                        <button v-for="pill in [
                            { key: 'all', label: 'Semua Waktu' },
                            { key: 'today', label: 'Hari Ini' },
                            { key: '7days', label: '7 Hari Terakhir' },
                            { key: 'month', label: 'Bulan Ini' },
                            { key: 'custom', label: 'Kustom Tanggal' },
                        ]"
                            :key="pill.key"
                            @click="selectPeriod(pill.key)"
                            type="button"
                            class="px-2.5 py-1 text-xs rounded-lg font-medium transition"
                            :class="filterPeriod === pill.key
                                ? 'bg-slate-900 text-white shadow-xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900'">
                            {{ pill.label }}
                        </button>
                    </div>

                    <div v-if="filterPeriod === 'custom' || dateFrom || dateTo" class="flex items-center gap-2">
                        <input v-model="dateFrom" type="date"
                            class="text-xs px-2.5 py-1 rounded-lg border-slate-200 text-slate-700 focus:ring-slate-900 focus:border-slate-900" />
                        <span class="text-xs text-slate-400">s/d</span>
                        <input v-model="dateTo" type="date"
                            class="text-xs px-2.5 py-1 rounded-lg border-slate-200 text-slate-700 focus:ring-slate-900 focus:border-slate-900" />
                        <button @click="applyFilter"
                            type="button"
                            class="px-3 py-1 bg-slate-900 text-white text-xs font-semibold rounded-lg hover:bg-slate-800 transition">
                            Terapkan
                        </button>
                    </div>
                </div>

                <!-- Baris Bawah: Inputs & Dropdowns -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                    <!-- Search Input -->
                    <div class="lg:col-span-4 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input v-model="search"
                            @keydown.enter="applyFilter"
                            type="text"
                            placeholder="Cari deskripsi, nama user, atau IP..."
                            class="block w-full pl-9 pr-3 py-2 text-xs border border-slate-200 rounded-lg bg-slate-50/50 text-slate-800 placeholder-slate-400 focus:bg-white focus:ring-1 focus:ring-slate-900 focus:border-slate-900" />
                    </div>

                    <!-- Modul Filter -->
                    <div class="lg:col-span-2">
                        <select v-model="filterModule" @change="applyFilter"
                            class="block w-full py-2 px-3 text-xs border border-slate-200 rounded-lg bg-white text-slate-700 focus:ring-slate-900 focus:border-slate-900">
                            <option value="all">Semua Modul</option>
                            <option v-for="mod in availableModules" :key="mod" :value="mod">{{ mod }}</option>
                        </select>
                    </div>

                    <!-- Role Filter -->
                    <div class="lg:col-span-2">
                        <select v-model="filterRole" @change="applyFilter"
                            class="block w-full py-2 px-3 text-xs border border-slate-200 rounded-lg bg-white text-slate-700 focus:ring-slate-900 focus:border-slate-900">
                            <option value="all">Semua Role</option>
                            <option v-for="r in availableRoles" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>

                    <!-- Aksi Filter -->
                    <div class="lg:col-span-2">
                        <select v-model="filterAction" @change="applyFilter"
                            class="block w-full py-2 px-3 text-xs border border-slate-200 rounded-lg bg-white text-slate-700 focus:ring-slate-900 focus:border-slate-900">
                            <option value="all">Semua Jenis Aksi</option>
                            <option v-for="act in availableActions" :key="act" :value="act">{{ act }}</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="lg:col-span-2 flex items-center gap-2">
                        <button @click="applyFilter"
                            type="button"
                            class="flex-1 py-2 px-3 text-xs font-semibold rounded-lg text-white bg-slate-900 hover:bg-slate-800 transition text-center shadow-xs">
                            Cari
                        </button>
                        <button @click="resetFilter"
                            type="button"
                            title="Reset Semua Filter"
                            class="py-2 px-3 text-xs font-medium rounded-lg text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- 3. TABEL LOG AKTIVITAS -->
            <!-- ============================================================ -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Daftar Transaksi Log Aktivitas</h2>
                        <p class="text-xs text-slate-500">
                            Menampilkan {{ logs.data.length }} dari total {{ logs.total }} entri log yang cocok dengan filter.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Audit Logging Aktif
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold tracking-wider uppercase text-[10px]">
                                <th class="py-3 px-4 w-36">Waktu & Tanggal</th>
                                <th class="py-3 px-4 w-48">Pengguna</th>
                                <th class="py-3 px-4 w-36">Modul & Aksi</th>
                                <th class="py-3 px-4">Deskripsi Aktivitas</th>
                                <th class="py-3 px-4 w-36">IP & Perangkat</th>
                                <th class="py-3 px-4 w-20 text-center">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-50/80 transition group">
                                <!-- Waktu -->
                                <td class="py-3 px-4 align-top">
                                    <div class="font-mono text-[11px] font-semibold text-slate-900">
                                        {{ formatDate(log.created_at) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        {{ log.created_at ? new Date(log.created_at).toLocaleTimeString('id-ID') : '-' }}
                                    </div>
                                </td>

                                <!-- Pengguna -->
                                <td class="py-3 px-4 align-top">
                                    <div class="flex items-start gap-2.5">
                                        <div class="w-7 h-7 rounded-md bg-slate-900 text-white font-bold text-[10px] flex items-center justify-center flex-shrink-0">
                                            {{ (log.user_name || 'U').slice(0, 2).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-slate-900 truncate">
                                                {{ log.user_name || (log.user ? log.user.nama_lengkap : 'Pengguna Sistem') }}
                                            </div>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="inline-block px-1.5 py-0.2 rounded text-[9px] font-semibold border"
                                                    :class="getRoleBadgeClass(log.user_role || (log.user?.role))">
                                                    {{ log.user_role || (log.user?.role || 'Guest') }}
                                                </span>
                                                <span v-if="log.user_seksi" class="text-[10px] text-slate-400 truncate">
                                                    {{ log.user_seksi }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Modul & Aksi -->
                                <td class="py-3 px-4 align-top">
                                    <div class="inline-block px-2 py-0.5 rounded text-[10px] font-bold border tracking-tight"
                                        :class="getActionBadgeClass(log.action)">
                                        {{ log.action }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-medium mt-1">
                                        {{ log.module }}
                                    </div>
                                </td>

                                <!-- Deskripsi -->
                                <td class="py-3 px-4 align-top">
                                    <div class="text-xs text-slate-800 leading-relaxed font-medium">
                                        {{ log.description }}
                                    </div>
                                    <div v-if="log.properties && Object.keys(log.properties).length > 0" class="mt-1 flex items-center gap-1.5 text-[10px] text-slate-400">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Memuat {{ Object.keys(log.properties).length }} parameter data</span>
                                    </div>
                                </td>

                                <!-- IP & Perangkat -->
                                <td class="py-3 px-4 align-top">
                                    <div class="font-mono text-[11px] text-slate-800">
                                        {{ log.ip_address || '127.0.0.1' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 truncate max-w-[140px]" :title="log.user_agent">
                                        {{ log.user_agent ? (log.user_agent.includes('Windows') ? 'Windows' : 'Mobile/Browser') : '-' }}
                                    </div>
                                </td>

                                <!-- Tombol Aksi Detail -->
                                <td class="py-3 px-4 align-top text-center">
                                    <button @click="openDetailModal(log)"
                                        type="button"
                                        class="p-1.5 text-slate-500 hover:text-slate-900 rounded-lg hover:bg-slate-100 transition"
                                        title="Lihat Detail Log">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="py-12 text-center">
                                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div class="text-sm font-semibold text-slate-800">Tidak ada log aktivitas yang cocok</div>
                                    <div class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                        Coba sesuaikan kata kunci pencarian, modul, atau rentang periode filter di atas.
                                    </div>
                                    <button @click="resetFilter"
                                        type="button"
                                        class="mt-3 px-3.5 py-1.5 bg-slate-900 text-white rounded-lg text-xs font-medium hover:bg-slate-800 transition">
                                        Reset Filter
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.links && logs.links.length > 3" class="p-4 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/50">
                    <div class="text-xs text-slate-500">
                        Menampilkan <span class="font-semibold text-slate-900">{{ logs.from || 0 }}</span> sampai <span class="font-semibold text-slate-900">{{ logs.to || 0 }}</span> dari total <span class="font-semibold text-slate-900">{{ logs.total }}</span> log
                    </div>
                    <div class="flex flex-wrap items-center gap-1">
                        <template v-for="(link, i) in logs.links" :key="i">
                            <span v-if="!link.url"
                                v-html="link.label"
                                class="px-2.5 py-1 text-xs rounded border border-slate-200 text-slate-300 bg-white cursor-not-allowed">
                            </span>
                            <Link v-else
                                :href="link.url"
                                v-html="link.label"
                                class="px-2.5 py-1 text-xs rounded border transition"
                                :class="link.active
                                    ? 'bg-slate-900 text-white border-slate-900 font-semibold'
                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                                preserve-scroll />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- 4. MODAL DETAIL AUDIT LOG -->
        <!-- ============================================================ -->
        <div v-if="showDetailModal && selectedLog"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-2xl w-full overflow-hidden">
                <!-- Modal Header -->
                <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-slate-900"></span>
                        <h3 class="font-bold text-sm text-slate-900">Detail Rekaman Log Aktivitas #{{ selectedLog.id }}</h3>
                    </div>
                    <button @click="closeDetailModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-5 space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-4 pb-4 border-b border-slate-100">
                        <div>
                            <div class="text-[11px] font-semibold text-slate-400 uppercase">Waktu Pencatatan</div>
                            <div class="mt-0.5 text-xs font-mono font-medium text-slate-900">{{ formatDate(selectedLog.created_at) }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-slate-400 uppercase">Alamat IP</div>
                            <div class="mt-0.5 text-xs font-mono font-medium text-slate-900">{{ selectedLog.ip_address || '127.0.0.1' }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-slate-400 uppercase">Pengguna & Peran</div>
                            <div class="mt-0.5 text-xs font-medium text-slate-900">
                                {{ selectedLog.user_name || (selectedLog.user ? selectedLog.user.nama_lengkap : 'Sistem') }}
                                <span class="ml-1 text-slate-500 font-normal">({{ selectedLog.user_role || (selectedLog.user?.role) }})</span>
                            </div>
                        </div>
                        <div>
                            <div class="text-[11px] font-semibold text-slate-400 uppercase">Modul & Aksi</div>
                            <div class="mt-0.5 flex items-center gap-1.5">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold border" :class="getActionBadgeClass(selectedLog.action)">
                                    {{ selectedLog.action }}
                                </span>
                                <span class="text-xs font-medium text-slate-600">• {{ selectedLog.module }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="text-[11px] font-semibold text-slate-400 uppercase">Deskripsi Aktivitas</div>
                        <div class="mt-1 p-3 rounded-lg bg-slate-50 border border-slate-200 text-slate-800 text-xs font-medium leading-relaxed">
                            {{ selectedLog.description }}
                        </div>
                    </div>

                    <div>
                        <div class="text-[11px] font-semibold text-slate-400 uppercase">Perangkat (User Agent)</div>
                        <div class="mt-1 p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-[11px] font-mono text-slate-600 break-all">
                            {{ selectedLog.user_agent || '-' }}
                        </div>
                    </div>

                    <div v-if="selectedLog.properties && Object.keys(selectedLog.properties).length > 0">
                        <div class="text-[11px] font-semibold text-slate-400 uppercase">Payload Data / Parameter Perubahan</div>
                        <pre class="mt-1 p-3 rounded-lg bg-slate-900 text-slate-200 font-mono text-[11px] overflow-x-auto max-h-48">{{ JSON.stringify(selectedLog.properties, null, 2) }}</pre>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-5 py-3 border-t border-slate-200 bg-slate-50 flex items-center justify-end">
                    <button @click="closeDetailModal"
                        type="button"
                        class="px-4 py-2 text-xs font-semibold rounded-lg text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
