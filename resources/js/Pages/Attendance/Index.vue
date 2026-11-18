<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Clock,
    Fingerprint,
    Calendar,
    Building2,
    Search,
    RefreshCw,
    FileSpreadsheet,
    FileText,
    Download,
    CheckCircle2,
    AlertCircle,
    X,
    Wifi,
    Radio,
    Users,
    UserCheck,
    UserX,
    LogIn,
    LogOut,
    ShieldCheck,
    ChevronLeft,
    ChevronRight,
    Activity as ActivityIcon
} from 'lucide-vue-next';

const props = defineProps({
    isPjlp: Boolean,
    attendances: Object, // for PJLP (paginated)
    list: Object, // for Admin (paginated)
    stats: Object, // for Admin Realtime Stats
    todayAttendance: Object, // for PJLP Realtime
    todayDate: String,
    serverTime: String,
    filters: Object,
    seksiList: Array,
    defaultFingerprintIp: String,
});

// Admin Filter state
const filterDate = ref(props.filters?.tanggal || new Date().toISOString().slice(0, 10));
const filterSeksi = ref(props.filters?.seksi || '');
const filterSearch = ref(props.filters?.search || '');

// PJLP Filter state
const filterMonth = ref(props.filters?.bulan || new Date().toISOString().slice(0, 7));

// Real-time State
const isRefreshing = ref(false);
const lastUpdated = ref(new Date().toLocaleTimeString('id-ID'));
const currentTime = ref(props.serverTime || new Date().toLocaleTimeString('id-ID'));
const liveStats = ref(props.stats || {
    total_pjlp: 0,
    hadir_masuk: 0,
    hadir_pulang: 0,
    sedang_bekerja: 0,
    terlambat: 0,
    belum_hadir: 0,
});
const todayAttendance = ref(props.todayAttendance || null);
const liveList = ref(props.list || { data: [], total: 0 });
const refreshError = ref('');
const isNavigating = ref(false);
let pollController = null;
let pollVersion = 0;
let removeStartListener = null;
let removeFinishListener = null;
const cancelPolling = () => {
    pollVersion++;
    pollController?.abort();
    pollController = null;
    isRefreshing.value = false;
};
const pollingContext = () => JSON.stringify([
    props.filters, filterDate.value, filterSeksi.value, filterSearch.value, liveList.value?.current_page || 1,
]);
watch([filterDate, filterSeksi, filterSearch], cancelPolling, { flush: 'sync' });
watch(() => props.filters, (filters) => {
    filterDate.value = filters?.tanggal || props.todayDate;
    filterSeksi.value = filters?.seksi || '';
    filterSearch.value = filters?.search || '';
    if (filters?.bulan) filterMonth.value = filters.bulan;
});

// Sinkronisasi reaktif jika props berubah dari server
watch(() => props.list, (newList) => {
    if (newList) liveList.value = newList;
}, { deep: true });

watch(() => props.stats, (newStats) => {
    if (newStats) liveStats.value = newStats;
}, { deep: true });

watch(() => props.todayAttendance, (newAtt) => {
    todayAttendance.value = newAtt;
}, { deep: true });

let clockInterval = null;
let pollInterval = null;

// Sync & Connection Test State
const isSyncing = ref(false);
const isTesting = ref(false);
const showSyncModal = ref(false);
const machineIp = ref(props.defaultFingerprintIp || '');
const connectionStatus = ref(null);

// Filter Bulan & Tahun untuk Tarik Mesin
const currentYearNow = new Date().getFullYear();
const currentMonthNow = String(new Date().getMonth() + 1).padStart(2, '0');
const syncMonth = ref(currentMonthNow);
const syncYear = ref(String(currentYearNow));
const availableYears = ref([2024, 2025, 2026, 2027]);

const monthList = [
    { value: 'all', label: 'Semua Bulan' },
    { value: '01', label: 'Januari' },
    { value: '02', label: 'Februari' },
    { value: '03', label: 'Maret' },
    { value: '04', label: 'April' },
    { value: '05', label: 'Mei' },
    { value: '06', label: 'Juni' },
    { value: '07', label: 'Juli' },
    { value: '08', label: 'Agustus' },
    { value: '09', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const getSelectedPeriodLabel = () => {
    if (syncMonth.value === 'all' && syncYear.value === 'all') {
        return 'Semua Periode';
    }
    const m = monthList.find(x => x.value === syncMonth.value);
    const mLabel = m ? m.label : syncMonth.value;
    if (syncMonth.value === 'all') {
        return `Semua Bulan (${syncYear.value})`;
    }
    if (syncYear.value === 'all') {
        return `${mLabel} (Semua Tahun)`;
    }
    return `${mLabel} ${syncYear.value}`;
};

const openSyncModal = () => {
    const selectedDate = filterDate.value || props.todayDate;
    syncMonth.value = selectedDate.slice(5, 7);
    syncYear.value = selectedDate.slice(0, 4);
    const selectedYear = Number(syncYear.value);
    if (!availableYears.value.includes(selectedYear)) {
        availableYears.value = [...availableYears.value, selectedYear].sort((a, b) => a - b);
    }
    connectionStatus.value = null;
    showSyncModal.value = true;
};

// Presensi Mandiri State
const isSubmittingPresensi = ref(false);

// Clock ticker
const startClock = () => {
    clockInterval = setInterval(() => {
        const now = new Date();
        currentTime.value = now.toLocaleTimeString('id-ID', { hour12: false });
    }, 1000);
};

// Real-time Background Polling
const pollRealtimeData = async (silent = true) => {
    if (isNavigating.value || isSyncing.value || pollController || (!props.isPjlp && !filterDate.value)) return;
    const context = pollingContext();
    const version = ++pollVersion;
    const controller = new AbortController();
    pollController = controller;
    if (!silent) isRefreshing.value = true;
    try {
        const response = await axios.get(route('attendance.realtime-feed'), {
            signal: controller.signal,
            timeout: 15000,
            params: {
                tanggal: props.filters?.tanggal || props.todayDate,
                seksi: props.filters?.seksi || undefined,
                search: props.filters?.search || undefined,
                page: liveList.value?.current_page || 1,
            }
        });

        if (version !== pollVersion || context !== pollingContext() || isNavigating.value) return;
        if (response.data) {
            refreshError.value = '';
            currentTime.value = response.data.server_time || currentTime.value;
            lastUpdated.value = new Date().toLocaleTimeString('id-ID');

            if (props.isPjlp) {
                todayAttendance.value = response.data.today_attendance;
            } else {
                if (response.data.stats) {
                    liveStats.value = response.data.stats;
                }
                if (response.data.list) {
                    liveList.value = response.data.list;
                }
            }
        }
    } catch (e) {
        if (!axios.isCancel(e) && version === pollVersion) {
            refreshError.value = 'Pembaruan absensi gagal. Data terakhir tetap ditampilkan; silakan muat ulang.';
        }
    } finally {
        if (version === pollVersion) {
            pollController = null;
            isRefreshing.value = false;
        }
    }
};

const goToPage = (pageOrUrl) => {
    if (!pageOrUrl) return;

    let targetPage = 1;
    if (typeof pageOrUrl === 'number') {
        targetPage = pageOrUrl;
    } else if (typeof pageOrUrl === 'string') {
        try {
            const parsed = new URL(pageOrUrl, window.location.origin);
            targetPage = parsed.searchParams.get('page') || 1;
        } catch {
            const match = pageOrUrl.match(/page=(\d+)/);
            targetPage = match ? match[1] : 1;
        }
    }

    router.get(route('attendance.index'), {
        tanggal: filterDate.value,
        seksi: filterSeksi.value || undefined,
        search: filterSearch.value || undefined,
        page: targetPage,
    }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.list) liveList.value = page.props.list;
            if (page.props.stats) liveStats.value = page.props.stats;
            lastUpdated.value = new Date().toLocaleTimeString('id-ID');
        }
    });
};

const goToPagePjlp = (pageOrUrl) => {
    if (!pageOrUrl) return;

    let targetPage = 1;
    if (typeof pageOrUrl === 'number') {
        targetPage = pageOrUrl;
    } else if (typeof pageOrUrl === 'string') {
        try {
            const parsed = new URL(pageOrUrl, window.location.origin);
            targetPage = parsed.searchParams.get('page') || 1;
        } catch {
            const match = pageOrUrl.match(/page=(\d+)/);
            targetPage = match ? match[1] : 1;
        }
    }

    router.get(route('attendance.index'), {
        bulan: filterMonth.value,
        page: targetPage,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatPaginationLabel = (label) => {
    if (!label) return '';
    if (label.includes('Previous') || label.includes('&laquo;') || label.includes('pagination.previous')) {
        return '<';
    }
    if (label.includes('Next') || label.includes('&raquo;') || label.includes('pagination.next')) {
        return '>';
    }
    return label;
};

const applyAdminFilter = () => {
    if (!filterDate.value) filterDate.value = props.todayDate;
    router.get(route('attendance.index'), {
        tanggal: filterDate.value,
        seksi: filterSeksi.value || undefined,
        search: filterSearch.value || undefined,
        page: 1,
    }, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            if (page.props.list) liveList.value = page.props.list;
            if (page.props.stats) liveStats.value = page.props.stats;
            lastUpdated.value = new Date().toLocaleTimeString('id-ID');
        }
    });
};

const applyPjlpFilter = () => {
    router.get(route('attendance.index'), {
        bulan: filterMonth.value,
        page: 1,
    }, { preserveState: true, replace: true });
};

// Presensi Mandiri Realtime untuk PJLP
const submitPresensiMandiri = () => {
    if (isSubmittingPresensi.value) return;

    const actionText = !todayAttendance.value?.absen_masuk
        ? 'catat presensi MASUK'
        : 'catat presensi PULANG';

    if (!confirm(`Konfirmasi: Apakah Anda ingin melakukan ${actionText} sekarang pada pukul ${currentTime.value} WIB?`)) {
        return;
    }

    isSubmittingPresensi.value = true;
    router.post(route('attendance.presensi-mandiri'), {}, {
        onSuccess: () => {
            pollRealtimeData(false);
        },
        onFinish: () => {
            isSubmittingPresensi.value = false;
        }
    });
};

const triggerSync = () => {
    isSyncing.value = true;
    router.post(route('attendance.sync'), {
        ip: machineIp.value || undefined,
        bulan: syncMonth.value,
        tahun: syncYear.value,
        tanggal: filterDate.value,
    }, {
        preserveState: false,
        onSuccess: (page) => {
            if (page.props.filters?.tanggal) {
                filterDate.value = page.props.filters.tanggal;
            }
            if (page.props.list) liveList.value = page.props.list;
            if (page.props.stats) liveStats.value = page.props.stats;
            lastUpdated.value = new Date().toLocaleTimeString('id-ID');
        },
        onFinish: () => {
            isSyncing.value = false;
            showSyncModal.value = false;
        }
    });
};

const exportExcel = () => {
    const params = new URLSearchParams({
        tanggal: filterDate.value,
        seksi: filterSeksi.value || '',
        search: filterSearch.value || '',
    });
    window.location.href = `${route('attendance.export.excel')}?${params.toString()}`;
};

const exportPdf = () => {
    const params = new URLSearchParams({
        tanggal: filterDate.value,
        seksi: filterSeksi.value || '',
        search: filterSearch.value || '',
    });
    window.open(`${route('attendance.export.pdf')}?${params.toString()}`, '_blank');
};

const testConnection = async () => {
    isTesting.value = true;
    connectionStatus.value = null;
    try {
        const response = await fetch(route('attendance.test-connection'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ ip: machineIp.value || undefined }),
        });
        const data = await response.json();
        connectionStatus.value = data;
    } catch (err) {
        connectionStatus.value = { success: false, message: 'Gagal menghubungi server: ' + err.message };
    } finally {
        isTesting.value = false;
    }
};

const formatIndonesianDate = (dateStr) => {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    } catch (e) {
        return dateStr;
    }
};

onMounted(() => {
    removeStartListener = router.on('start', () => {
        isNavigating.value = true;
        cancelPolling();
    });
    removeFinishListener = router.on('finish', () => {
        isNavigating.value = false;
    });
    startClock();
    // Poll data realtime otomatis setiap 12 detik di latar belakang tanpa reload halaman
    pollInterval = setInterval(() => {
        pollRealtimeData(true);
    }, 12000);
});

onUnmounted(() => {
    cancelPolling();
    removeStartListener?.();
    removeFinishListener?.();
    if (clockInterval) clearInterval(clockInterval);
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <Head title="Presensi & Absensi Pegawai PJLP" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live Presensi Terpadu
                        </span>
                        <span class="text-xs text-slate-400">• Fingerprint & Real-time Log</span>
                    </div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900">
                        {{ isPjlp ? 'Presensi Real-time Saya' : 'Monitoring Presensi Pegawai PJLP' }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ isPjlp ? 'Catatan dan pemantauan realtime jam kehadiran masuk dan pulang kantor' : 'Pemantauan realtime data kehadiran fingerprint pegawai PJLP secara live & otomatis' }}
                    </p>
                </div>

                <!-- Right Action Bar: Export & Actions -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Unduh Excel Harian -->
                    <button
                        type="button"
                        @click="exportExcel"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl border border-emerald-300 bg-white hover:bg-emerald-50 text-emerald-700 shadow-xs transition"
                        title="Unduh laporan presensi tanggal ini (Excel)"
                    >
                        <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
                        <span>Unduh Excel</span>
                    </button>

                    <!-- Unduh PDF Harian -->
                    <button
                        type="button"
                        @click="exportPdf"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl border border-rose-300 bg-white hover:bg-rose-50 text-rose-700 shadow-xs transition"
                        title="Unduh laporan presensi tanggal ini (PDF)"
                    >
                        <FileText class="w-4 h-4 text-rose-600" />
                        <span>Unduh PDF</span>
                    </button>

                    <!-- Rekap Bulanan Link -->
                    <Link
                        :href="route('attendance.recap')"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 shadow-xs transition"
                    >
                        <FileSpreadsheet class="w-4 h-4 text-blue-600" />
                        <span>Rekap Bulanan</span>
                    </Link>

                    <!-- Tarik Dari Mesin Button (Admin/Kasi) -->
                    <button
                        v-if="!isPjlp"
                        type="button"
                        @click="showSyncModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl bg-blue-600 text-white shadow-xs hover:bg-blue-500 transition active:scale-95"
                    >
                        <Radio class="w-4 h-4" />
                        <span>Tarik Mesin</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- ============================================================ -->
            <!-- REALTIME NOTIFICATION STRIP                                   -->
            <!-- ============================================================ -->
            <div class="bg-white px-4 py-2.5 rounded-xl border border-slate-200/80 shadow-xs flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
                <div class="flex items-center gap-2.5">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                    <span class="font-semibold text-slate-800">
                        Sinkronisasi Real-time Otomatis
                    </span>
                    <span class="text-slate-400">•</span>
                    <span class="text-slate-500">Terakhir diperbarui: <span class="font-mono font-bold text-slate-700">{{ lastUpdated }} WIB</span></span>
                </div>
                <div class="text-[11px] text-slate-500 flex items-center gap-1.5">
                    <Calendar class="w-3.5 h-3.5 text-slate-400" />
                    <span>Hari ini: <strong class="text-slate-700">{{ formatIndonesianDate(todayDate || new Date()) }}</strong></span>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAMPILAN PRESENSI UNTUK PEGAWAI PJLP                         -->
            <!-- ============================================================ -->
            <div v-if="isPjlp" class="space-y-6">
                <!-- KARTU UTAMA: STATUS PRESENSI REALTIME HARI INI -->
                <div class="bg-gradient-to-br from-slate-900 via-slate-850 to-slate-800 text-white rounded-2xl p-6 shadow-xl border border-slate-700/60 relative overflow-hidden">
                    <!-- Background ambient glow -->
                    <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

                    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <!-- Left Info -->
                        <div class="space-y-2 max-w-lg">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                                    Status Kehadiran Hari Ini
                                </span>
                                <span class="text-xs text-slate-400">{{ formatIndonesianDate(todayDate) }}</span>
                            </div>

                            <h2 class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                                <Fingerprint class="w-6 h-6 text-emerald-400" />
                                <span>{{ $page.props.auth.user.nama_lengkap }}</span>
                            </h2>

                            <p class="text-xs text-slate-300 leading-relaxed">
                                Sistem mencatat waktu kehadiran secara realtime. Pastikan jam masuk dan pulang Anda terekam dengan akurat sesuai jadwal kerja kantor.
                            </p>

                            <!-- Live Digital Clock Display -->
                            <div class="inline-flex items-center gap-2 pt-1 font-mono text-xs text-slate-300">
                                <Clock class="w-4 h-4 text-emerald-400" />
                                <span>Jam Saat Ini:</span>
                                <span class="font-bold text-white text-sm bg-slate-800/80 px-2 py-0.5 rounded border border-slate-700">{{ currentTime }} WIB</span>
                            </div>
                        </div>

                        <!-- Right Info: Attendance Cards & Action -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                            <!-- Card Absen Masuk -->
                            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[150px]">
                                <div class="text-[10px] uppercase font-bold tracking-wider text-slate-300 flex items-center gap-1.5 mb-1">
                                    <LogIn class="w-3.5 h-3.5 text-emerald-400" />
                                    <span>Absen Masuk</span>
                                </div>
                                <div class="text-lg font-mono font-black" :class="todayAttendance?.absen_masuk ? 'text-emerald-300' : 'text-slate-400'">
                                    {{ todayAttendance?.absen_masuk || '--:--:--' }}
                                </div>
                                <div class="text-[10px] mt-1 font-medium" :class="todayAttendance?.terlambat && todayAttendance?.terlambat !== '00:00:00' ? 'text-rose-400' : 'text-emerald-400'">
                                    {{ todayAttendance?.absen_masuk ? (todayAttendance?.terlambat && todayAttendance?.terlambat !== '00:00:00' ? 'Terlambat ' + todayAttendance.terlambat : 'Tepat Waktu') : 'Belum Absen Masuk' }}
                                </div>
                            </div>

                            <!-- Card Absen Pulang -->
                            <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10 min-w-[150px]">
                                <div class="text-[10px] uppercase font-bold tracking-wider text-slate-300 flex items-center gap-1.5 mb-1">
                                    <LogOut class="w-3.5 h-3.5 text-blue-400" />
                                    <span>Absen Pulang</span>
                                </div>
                                <div class="text-lg font-mono font-black" :class="todayAttendance?.absen_pulang ? 'text-blue-300' : 'text-slate-400'">
                                    {{ todayAttendance?.absen_pulang || '--:--:--' }}
                                </div>
                                <div class="text-[10px] mt-1 text-slate-400 font-medium">
                                    {{ todayAttendance?.absen_pulang ? 'Selesai Bertugas' : 'Belum Absen Pulang' }}
                                </div>
                            </div>

                            <!-- Quick Action Button -->
                            <div class="flex flex-col justify-center">
                                <button
                                    v-if="!todayAttendance?.absen_masuk"
                                    type="button"
                                    @click="submitPresensiMandiri"
                                    :disabled="isSubmittingPresensi"
                                    class="px-5 py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-white font-bold text-xs shadow-lg transition active:scale-95 disabled:opacity-50 flex items-center justify-center gap-2"
                                >
                                    <LogIn class="w-4 h-4" />
                                    <span>{{ isSubmittingPresensi ? 'Mencatat...' : 'Presensi Masuk' }}</span>
                                </button>
                                <button
                                    v-else-if="!todayAttendance?.absen_pulang"
                                    type="button"
                                    @click="submitPresensiMandiri"
                                    :disabled="isSubmittingPresensi"
                                    class="px-5 py-3 rounded-xl bg-blue-500 hover:bg-blue-400 text-white font-bold text-xs shadow-lg transition active:scale-95 disabled:opacity-50 flex items-center justify-center gap-2"
                                >
                                    <LogOut class="w-4 h-4" />
                                    <span>{{ isSubmittingPresensi ? 'Mencatat...' : 'Presensi Pulang' }}</span>
                                </button>
                                <div
                                    v-else
                                    class="px-4 py-2.5 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-bold flex items-center gap-2"
                                >
                                    <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                                    <span>Kehadiran Lengkap</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Bulan -->
                <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Calendar class="w-4 h-4 text-slate-400" />
                        <label class="text-xs font-bold text-slate-700">Riwayat Presensi Periode Bulan:</label>
                    </div>
                    <input
                        type="month"
                        v-model="filterMonth"
                        @change="applyPjlpFilter"
                        class="rounded-lg border-slate-300 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3 font-semibold text-slate-800"
                    />
                </div>

                <!-- Tabel Riwayat Absensi Bulanan -->
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                            <Clock class="w-4 h-4 text-blue-600" />
                            <span>Daftar Log Kehadiran Bulanan</span>
                        </h3>
                        <span class="text-xs text-slate-400">Total: <strong>{{ attendances.data?.length || 0 }} hari kerja</strong></span>
                    </div>

                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 font-semibold text-[11px] uppercase tracking-wider">
                                    <th class="py-2 px-3">Tanggal</th>
                                    <th class="py-2 px-3 text-center">Jam Masuk</th>
                                    <th class="py-2 px-3 text-center">Jam Pulang</th>
                                    <th class="py-2 px-3 text-center">Scan Masuk</th>
                                    <th class="py-2 px-3 text-center">Scan Keluar</th>
                                    <th class="py-2 px-3 text-center">Terlambat</th>
                                    <th class="py-2 px-3 text-center">Pulang Cepat</th>
                                    <th class="py-2 px-3 text-center">Lembur</th>
                                    <th class="py-2 px-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="!attendances.data || attendances.data.length === 0">
                                    <td colspan="9" class="py-12 text-center text-slate-400">
                                        <Fingerprint class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                        <span>Tidak ada catatan presensi untuk bulan terpilih.</span>
                                    </td>
                                </tr>
                                <tr
                                    v-for="att in attendances.data"
                                    :key="att.id"
                                    class="hover:bg-slate-50/80 transition"
                                    :class="{ 'bg-blue-50/40': att.tanggal === todayDate }"
                                >
                                    <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span>{{ att.tanggal }}</span>
                                            <span v-if="att.tanggal === todayDate" class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-blue-100 text-blue-700 border border-blue-200">
                                                Hari Ini
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono text-slate-600">
                                        {{ att.jam_masuk || '07:30:00' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono text-slate-600">
                                        {{ att.jam_pulang || '16:00:00' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono font-bold text-emerald-600">
                                        {{ att.absen_masuk || '--:--:--' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono font-bold text-blue-600">
                                        {{ att.absen_pulang || '--:--:--' }}
                                    </td>
                                    <td
                                        class="py-2.5 px-3 text-center font-mono"
                                        :class="att.terlambat && att.terlambat !== '00:00:00' ? 'text-rose-600 font-bold' : 'text-slate-400'"
                                    >
                                        {{ att.terlambat || '-' }}
                                    </td>
                                    <td
                                        class="py-2.5 px-3 text-center font-mono"
                                        :class="att.pulang_cepat && att.pulang_cepat !== '00:00:00' ? 'text-amber-600 font-bold' : 'text-slate-400'"
                                    >
                                        {{ att.pulang_cepat || '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono text-slate-500">
                                        {{ att.lembur || att.waktu_kompensasi || '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        <span
                                            class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold"
                                            :class="{
                                                'bg-emerald-100 text-emerald-800 border border-emerald-300': att.keterangan === 'WFO',
                                                'bg-blue-100 text-blue-800 border border-blue-300': att.keterangan?.startsWith('DL'),
                                                'bg-sky-100 text-sky-800 border border-sky-300': att.keterangan === 'SAKIT' || att.keterangan?.startsWith('IZIN'),
                                                'bg-amber-100 text-amber-800 border border-amber-300': att.keterangan === 'CUTI',
                                                'bg-rose-100 text-rose-800 border border-rose-300': att.keterangan === 'ALPA',
                                            }"
                                        >
                                            {{ att.keterangan || 'WFO' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Adaptive Cards for PJLP Attendance -->
                    <div class="block md:hidden p-3.5 space-y-3 bg-slate-50/50">
                        <div v-if="!attendances.data || attendances.data.length === 0" class="py-10 text-center text-slate-400 bg-white rounded-2xl border border-dashed border-slate-200 p-6">
                            <Fingerprint class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                            <span class="text-xs">Tidak ada catatan presensi untuk bulan terpilih.</span>
                        </div>

                        <div v-for="att in attendances.data" :key="'mob-att-' + att.id"
                            class="p-4 rounded-2xl border bg-white shadow-2xs space-y-3 transition"
                            :class="att.tanggal === todayDate ? 'border-blue-400 ring-2 ring-blue-500/20' : 'border-slate-200/80'">
                            <!-- Card Header: Tanggal & Keterangan Status -->
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-black text-xs text-slate-900">{{ att.tanggal }}</span>
                                    <span v-if="att.tanggal === todayDate" class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-blue-100 text-blue-700 border border-blue-200">
                                        Hari Ini
                                    </span>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 border border-emerald-300': att.keterangan === 'WFO',
                                        'bg-blue-100 text-blue-800 border border-blue-300': att.keterangan?.startsWith('DL'),
                                        'bg-sky-100 text-sky-800 border border-sky-300': att.keterangan === 'SAKIT' || att.keterangan?.startsWith('IZIN'),
                                        'bg-amber-100 text-amber-800 border border-amber-300': att.keterangan === 'CUTI',
                                        'bg-rose-100 text-rose-800 border border-rose-300': att.keterangan === 'ALPA',
                                    }">
                                    {{ att.keterangan || 'WFO' }}
                                </span>
                            </div>

                            <!-- 2x2 Scan Timetable Grid -->
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Scan Masuk</div>
                                    <div class="font-mono font-black text-sm mt-0.5 text-emerald-600">
                                        {{ att.absen_masuk || '--:--:--' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">Jadwal: {{ att.jam_masuk || '07:30' }}</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Scan Keluar</div>
                                    <div class="font-mono font-black text-sm mt-0.5 text-blue-600">
                                        {{ att.absen_pulang || '--:--:--' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">Jadwal: {{ att.jam_pulang || '16:00' }}</div>
                                </div>
                            </div>

                            <!-- Detail Indicators: Terlambat, Pulang Cepat, Lembur -->
                            <div class="flex items-center gap-1.5 flex-wrap pt-1 text-[10px] font-mono">
                                <span v-if="att.terlambat && att.terlambat !== '00:00:00'" class="px-2 py-0.5 rounded-md font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                    Terlambat: {{ att.terlambat }}
                                </span>
                                <span v-else class="px-2 py-0.5 rounded-md font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Tepat Waktu
                                </span>

                                <span v-if="att.pulang_cepat && att.pulang_cepat !== '00:00:00'" class="px-2 py-0.5 rounded-md font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    Pulang Cepat: {{ att.pulang_cepat }}
                                </span>

                                <span v-if="att.lembur || att.waktu_kompensasi" class="px-2 py-0.5 rounded-md font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                    Lembur: {{ att.lembur || att.waktu_kompensasi }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Footer PJLP Pribadi (10 entri per halaman) -->
                    <div
                        v-if="attendances.links && attendances.links.length > 3"
                        class="px-5 py-3.5 border-t border-slate-200/80 bg-slate-50/60 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs"
                    >
                        <div class="text-slate-500 font-medium text-center sm:text-left">
                            Menampilkan <span class="font-bold text-slate-900">{{ attendances.from || 0 }}</span> sampai
                            <span class="font-bold text-slate-900">{{ attendances.to || 0 }}</span> dari total
                            <span class="font-bold text-slate-900">{{ attendances.total || 0 }}</span> hari presensi
                            <span class="text-slate-400 font-normal">(10 entri per halaman)</span>
                        </div>

                        <div class="flex items-center gap-1.5 flex-wrap justify-center">
                            <template v-for="(link, idx) in attendances.links" :key="idx">
                                <span
                                    v-if="link.label === '...'"
                                    class="min-w-8 h-8 px-2 flex items-center justify-center rounded-lg text-slate-400 font-bold select-none text-xs"
                                >
                                    ...
                                </span>
                                <span
                                    v-else-if="!link.url"
                                    class="min-w-8 h-8 px-2.5 flex items-center justify-center rounded-lg border border-slate-200/60 bg-slate-100/70 text-slate-400 cursor-not-allowed text-xs font-semibold select-none"
                                >
                                    <ChevronLeft v-if="idx === 0" class="w-4 h-4" />
                                    <ChevronRight v-else-if="idx === attendances.links.length - 1" class="w-4 h-4" />
                                    <span v-else v-html="formatPaginationLabel(link.label)"></span>
                                </span>
                                <button
                                    v-else
                                    type="button"
                                    @click="goToPagePjlp(link.url)"
                                    class="min-w-8 h-8 px-2.5 flex items-center justify-center rounded-lg text-xs font-bold transition select-none"
                                    :class="link.active
                                        ? 'bg-blue-600 text-white shadow-xs border border-blue-600'
                                        : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 hover:border-slate-300'"
                                >
                                    <ChevronLeft v-if="idx === 0" class="w-4 h-4" />
                                    <ChevronRight v-else-if="idx === attendances.links.length - 1" class="w-4 h-4" />
                                    <span v-else v-html="formatPaginationLabel(link.label)"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TAMPILAN MONITORING PRESENSI UNTUK ADMIN / KASI              -->
            <!-- ============================================================ -->
            <div v-else class="space-y-6">
                <!-- 5 REALTIME KPI STATS CARDS -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
                    <!-- Total PJLP -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center flex-shrink-0">
                            <Users class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total PJLP</div>
                            <div class="text-xl font-black text-slate-900 leading-tight">
                                {{ liveStats.total_pjlp || 0 }}
                            </div>
                        </div>
                    </div>

                    <!-- Hadir Masuk -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center flex-shrink-0">
                            <CheckCircle2 class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">Hadir Masuk</div>
                            <div class="text-xl font-black text-emerald-700 leading-tight">
                                {{ liveStats.hadir_masuk || 0 }}
                            </div>
                        </div>
                    </div>

                    <!-- Sedang Bekerja (WFO) -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 border border-sky-200 flex items-center justify-center flex-shrink-0">
                            <Building2 class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-[11px] font-bold text-sky-700 uppercase tracking-wider flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                                <span>Di Kantor</span>
                            </div>
                            <div class="text-xl font-black text-sky-700 leading-tight">
                                {{ liveStats.sedang_bekerja || 0 }}
                            </div>
                        </div>
                    </div>

                    <!-- Sudah Pulang -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center flex-shrink-0">
                            <LogOut class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-[11px] font-bold text-blue-700 uppercase tracking-wider">Sudah Pulang</div>
                            <div class="text-xl font-black text-blue-700 leading-tight">
                                {{ liveStats.hadir_pulang || 0 }}
                            </div>
                        </div>
                    </div>

                    <!-- Terlambat -->
                    <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex items-center gap-3 col-span-2 sm:col-span-1">
                        <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 border border-rose-200 flex items-center justify-center flex-shrink-0">
                            <AlertCircle class="w-5 h-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="text-[11px] font-bold text-rose-700 uppercase tracking-wider">Terlambat</div>
                            <div class="text-xl font-black text-rose-700 leading-tight">
                                {{ liveStats.terlambat || 0 }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Bar -->
                <p v-if="refreshError" role="alert" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">{{ refreshError }}</p>
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-sm text-blue-900">
                    Data tanggal <strong>{{ formatIndonesianDate(props.filters?.tanggal) }}</strong> berasal dari absensi yang tersimpan.
                    Scan kosong berarti belum ada scan tersimpan untuk tanggal tersebut. Untuk mengambil riwayat mesin, pilih Tarik Mesin dan periksa bulan/tahunnya.
                    Pembaruan otomatis hanya memuat ulang data tersimpan.
                </div>
                <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <Calendar class="w-4 h-4 text-slate-400" />
                            <label class="text-xs font-bold text-slate-600">Tanggal:</label>
                            <input
                                type="date"
                                v-model="filterDate"
                                @change="applyAdminFilter"
                                class="rounded-lg border-slate-300 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3 font-semibold"
                            />
                        </div>

                        <div class="flex items-center gap-2">
                            <Building2 class="w-4 h-4 text-slate-400" />
                            <label class="text-xs font-bold text-slate-600">Seksi:</label>
                            <select
                                v-model="filterSeksi"
                                @change="applyAdminFilter"
                                class="rounded-lg border-slate-300 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3"
                            >
                                <option value="">Semua Seksi</option>
                                <option v-for="s in seksiList" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <input
                                    type="text"
                                    v-model="filterSearch"
                                    @keyup.enter="applyAdminFilter"
                                    placeholder="Cari nama atau PIN..."
                                    class="rounded-lg border-slate-300 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 pl-8 pr-3 w-56"
                                />
                                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                            </div>
                            <button
                                type="button"
                                @click="applyAdminFilter"
                                class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-bold text-slate-700 transition"
                            >
                                Cari
                            </button>
                        </div>
                    </div>

                    <div class="text-xs text-slate-500 self-end md:self-center font-medium">
                        Daftar Pegawai: <span class="font-bold text-slate-900">{{ liveList.total || 0 }} Orang</span>
                    </div>
                </div>

                <!-- Tabel Absensi Harian Seluruh PJLP -->
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-100 border-b border-slate-200 text-slate-600 font-semibold text-[11px] uppercase tracking-wider">
                                    <th class="py-2 px-3">Nama Pegawai & Posisi</th>
                                    <th class="py-2 px-3 text-center">PIN Mesin</th>
                                    <th class="py-2 px-3">Seksi</th>
                                    <th class="py-2 px-3 text-center">Jam Masuk</th>
                                    <th class="py-2 px-3 text-center">Jam Pulang</th>
                                    <th class="py-2 px-3 text-center">Scan Masuk</th>
                                    <th class="py-2 px-3 text-center">Scan Keluar</th>
                                    <th class="py-2 px-3 text-center">Terlambat</th>
                                    <th class="py-2 px-3 text-center">Pulang Cepat</th>
                                    <th class="py-2 px-3 text-center">Lembur</th>
                                    <th class="py-2 px-3">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="!liveList.data || liveList.data.length === 0">
                                    <td colspan="11" class="py-12 text-center text-slate-400">
                                        <Fingerprint class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                        <span>Tidak ada data presensi ditemukan pada tanggal ini.</span>
                                    </td>
                                </tr>
                                <tr
                                    v-for="row in liveList.data"
                                    :key="row.user_id"
                                    class="hover:bg-slate-50/80 transition"
                                >
                                    <td class="py-2.5 px-3 font-semibold text-slate-900 whitespace-nowrap">
                                        {{ row.nama_lengkap }}
                                        <div class="text-[10px] font-normal text-slate-500">
                                            {{ row.posisi || 'Tenaga Pendukung PJLP' }}
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono text-slate-500 font-medium">
                                        {{ row.pin || '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-700">
                                        {{ row.seksi }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono text-slate-600">
                                        {{ row.jam_masuk || '07:30:00' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono text-slate-600">
                                        {{ row.jam_pulang || '16:00:00' }}
                                    </td>
                                    <td
                                        class="py-2.5 px-3 text-center font-mono font-bold"
                                        :class="row.absen_masuk ? 'text-emerald-600' : 'text-slate-400'"
                                    >
                                        {{ row.absen_masuk || '--:--:--' }}
                                    </td>
                                    <td
                                        class="py-2.5 px-3 text-center font-mono font-bold"
                                        :class="row.absen_pulang ? 'text-blue-600' : 'text-slate-400'"
                                    >
                                        {{ row.absen_pulang || '--:--:--' }}
                                    </td>
                                    <td
                                        class="py-2.5 px-3 text-center font-mono"
                                        :class="row.terlambat && row.terlambat !== '00:00:00' ? 'text-rose-600 font-bold' : 'text-slate-400'"
                                    >
                                        {{ row.terlambat || '-' }}
                                    </td>
                                    <td
                                        class="py-2.5 px-3 text-center font-mono"
                                        :class="row.pulang_cepat && row.pulang_cepat !== '00:00:00' ? 'text-amber-600 font-bold' : 'text-slate-400'"
                                    >
                                        {{ row.pulang_cepat || '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-mono text-slate-500">
                                        {{ row.lembur || row.waktu_kompensasi || '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        <!-- Live Status Badges -->
                                        <span
                                            v-if="row.absen_masuk && !row.absen_pulang"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            <span>Sedang Bekerja (WFO)</span>
                                        </span>
                                        <span
                                            v-else-if="row.absen_masuk && row.absen_pulang"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-100 text-blue-800 border border-blue-300"
                                        >
                                            <CheckCircle2 class="w-3.5 h-3.5 text-blue-600" />
                                            <span>Selesai Pulang</span>
                                        </span>
                                        <span
                                            v-else-if="row.keterangan?.startsWith('DL')"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-100 text-purple-800 border border-purple-300"
                                        >
                                            <span>{{ row.keterangan }}</span>
                                        </span>
                                        <span
                                            v-else-if="['SAKIT', 'CUTI', 'IZIN AWAL', 'IZIN AKHIR', 'IZIN PENUH'].includes(row.keterangan)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300"
                                        >
                                            <span>{{ row.keterangan }}</span>
                                        </span>
                                        <span
                                            v-else-if="row.keterangan === 'ALPA'"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-300"
                                        >
                                            <span>ALPA</span>
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-slate-100 text-slate-500"
                                        >
                                            <span>Belum Hadir</span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Adaptive Cards for Kasi/Admin Attendance -->
                    <div class="block md:hidden p-3.5 space-y-3 bg-slate-50/50">
                        <div v-if="!liveList.data || liveList.data.length === 0" class="py-10 text-center text-slate-400 bg-white rounded-2xl border border-dashed border-slate-200 p-6">
                            <Fingerprint class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                            <span class="text-xs">Tidak ada data presensi ditemukan pada tanggal ini.</span>
                        </div>

                        <div v-for="row in liveList.data" :key="'mob-kasi-att-' + row.user_id"
                            class="p-4 rounded-2xl border border-slate-200/80 bg-white shadow-2xs space-y-3 transition">
                            <!-- Card Header: Nama & Seksi + Status Pill -->
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-bold text-xs text-slate-900 truncate">{{ row.nama_lengkap }}</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5 flex items-center gap-1.5 flex-wrap">
                                        <span class="px-1.5 py-0.5 rounded bg-slate-100 font-semibold">{{ row.seksi }}</span>
                                        <span>•</span>
                                        <span class="font-mono text-slate-400">PIN: {{ row.pin || '-' }}</span>
                                    </div>
                                </div>

                                <!-- Status Badge -->
                                <span v-if="row.absen_masuk && !row.absen_pulang"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 flex-shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Sedang Bekerja</span>
                                </span>
                                <span v-else-if="row.absen_masuk && row.absen_pulang"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-300 flex-shrink-0">
                                    <CheckCircle2 class="w-3 h-3 text-blue-600" />
                                    <span>Pulang</span>
                                </span>
                                <span v-else-if="row.keterangan?.startsWith('DL')"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-300 flex-shrink-0">
                                    <span>{{ row.keterangan }}</span>
                                </span>
                                <span v-else-if="['SAKIT', 'CUTI', 'IZIN AWAL', 'IZIN AKHIR', 'IZIN PENUH'].includes(row.keterangan)"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 flex-shrink-0">
                                    <span>{{ row.keterangan }}</span>
                                </span>
                                <span v-else-if="row.keterangan === 'ALPA'"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300 flex-shrink-0">
                                    <span>ALPA</span>
                                </span>
                                <span v-else
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-500 flex-shrink-0">
                                    <span>Belum Hadir</span>
                                </span>
                            </div>

                            <!-- 2x2 Scan Timetable Grid -->
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Scan Masuk</div>
                                    <div class="font-mono font-black text-sm mt-0.5" :class="row.absen_masuk ? 'text-emerald-600' : 'text-slate-400'">
                                        {{ row.absen_masuk || '--:--:--' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">Jadwal: {{ row.jam_masuk || '07:30' }}</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Scan Keluar</div>
                                    <div class="font-mono font-black text-sm mt-0.5" :class="row.absen_pulang ? 'text-blue-600' : 'text-slate-400'">
                                        {{ row.absen_pulang || '--:--:--' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">Jadwal: {{ row.jam_pulang || '16:00' }}</div>
                                </div>
                            </div>

                            <!-- Detail Indicators: Terlambat, Pulang Cepat, Lembur -->
                            <div class="flex items-center gap-1.5 flex-wrap pt-1 text-[10px] font-mono">
                                <span v-if="row.terlambat && row.terlambat !== '00:00:00'" class="px-2 py-0.5 rounded-md font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                    Terlambat: {{ row.terlambat }}
                                </span>
                                <span v-else-if="row.absen_masuk" class="px-2 py-0.5 rounded-md font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Tepat Waktu
                                </span>

                                <span v-if="row.pulang_cepat && row.pulang_cepat !== '00:00:00'" class="px-2 py-0.5 rounded-md font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    Pulang Cepat: {{ row.pulang_cepat }}
                                </span>

                                <span v-if="row.lembur || row.waktu_kompensasi" class="px-2 py-0.5 rounded-md font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                    Lembur: {{ row.lembur || row.waktu_kompensasi }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Footer Admin Monitoring (10 entri per halaman) -->
                    <div
                        v-if="liveList.links && liveList.links.length > 3"
                        class="px-5 py-3.5 border-t border-slate-200/80 bg-slate-50/60 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs"
                    >
                        <div class="text-slate-500 font-medium text-center sm:text-left">
                            Menampilkan <span class="font-bold text-slate-900">{{ liveList.from || 0 }}</span> sampai
                            <span class="font-bold text-slate-900">{{ liveList.to || 0 }}</span> dari total
                            <span class="font-bold text-slate-900">{{ liveList.total || 0 }}</span> pegawai
                            <span class="text-slate-400 font-normal">(10 entri per halaman)</span>
                        </div>

                        <div class="flex items-center gap-1.5 flex-wrap justify-center">
                            <template v-for="(link, idx) in liveList.links" :key="idx">
                                <span
                                    v-if="link.label === '...'"
                                    class="min-w-8 h-8 px-2 flex items-center justify-center rounded-lg text-slate-400 font-bold select-none text-xs"
                                >
                                    ...
                                </span>
                                <span
                                    v-else-if="!link.url"
                                    class="min-w-8 h-8 px-2.5 flex items-center justify-center rounded-lg border border-slate-200/60 bg-slate-100/70 text-slate-400 cursor-not-allowed text-xs font-semibold select-none"
                                >
                                    <ChevronLeft v-if="idx === 0" class="w-4 h-4" />
                                    <ChevronRight v-else-if="idx === liveList.links.length - 1" class="w-4 h-4" />
                                    <span v-else v-html="formatPaginationLabel(link.label)"></span>
                                </span>
                                <button
                                    v-else
                                    type="button"
                                    @click="goToPage(link.url)"
                                    class="min-w-8 h-8 px-2.5 flex items-center justify-center rounded-lg text-xs font-bold transition select-none"
                                    :class="link.active
                                        ? 'bg-blue-600 text-white shadow-xs border border-blue-600'
                                        : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200 hover:border-slate-300'"
                                >
                                    <ChevronLeft v-if="idx === 0" class="w-4 h-4" />
                                    <ChevronRight v-else-if="idx === liveList.links.length - 1" class="w-4 h-4" />
                                    <span v-else v-html="formatPaginationLabel(link.label)"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL TARIK ABSENSI / TES KONEKSI MESIN                      -->
        <!-- ============================================================ -->
        <div
            v-if="showSyncModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-lg bg-blue-50 text-blue-600">
                            <Radio class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">
                                Sinkronisasi Mesin Absen
                            </h3>
                            <p class="text-[11px] text-slate-500">
                                Protokol SOAP / XML Service Mesin Fingerprint
                            </p>
                        </div>
                    </div>
                    <button @click="showSyncModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Sistem akan berkomunikasi langsung dengan perangkat biometrik fingerprint kantor Sudin Kominfotik untuk menarik log tapping presensi masuk dan pulang terkini.
                </p>

                <!-- Filter Bulan & Tahun Penarikan Mesin -->
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                            <Calendar class="w-4 h-4 text-blue-600" />
                            <span>Filter Bulan & Tahun</span>
                        </label>
                        <span class="text-[11px] font-bold text-blue-700 bg-blue-100/70 px-2 py-0.5 rounded-md border border-blue-200">
                            {{ getSelectedPeriodLabel() }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">
                                Pilih Bulan
                            </label>
                            <select
                                v-model="syncMonth"
                                class="w-full rounded-lg border-slate-300 bg-white text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-2 px-2.5 font-medium text-slate-800"
                            >
                                <option v-for="m in monthList" :key="m.value" :value="m.value">
                                    {{ m.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">
                                Pilih Tahun
                            </label>
                            <select
                                v-model="syncYear"
                                class="w-full rounded-lg border-slate-300 bg-white text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-2 px-2.5 font-medium text-slate-800"
                            >
                                <option value="all">Semua Tahun</option>
                                <option v-for="yr in availableYears" :key="yr" :value="String(yr)">
                                    Tahun {{ yr }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-500 leading-snug">
                        Sistem hanya akan menarik dan menyinkronkan data presensi pada <strong>{{ getSelectedPeriodLabel() }}</strong>.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                        IP Address Mesin Fingerprint
                    </label>
                    <input
                        type="text"
                        v-model="machineIp"
                        :placeholder="defaultFingerprintIp ? `Default server: ${defaultFingerprintIp}` : 'Contoh: 10.130.56.7'"
                        class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 p-2.5 font-mono text-slate-900 dark:text-slate-100"
                    />
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                        Konfigurasi aktif: <span class="font-mono font-semibold text-blue-600 dark:text-blue-400">{{ machineIp || defaultFingerprintIp || '10.130.56.7' }}</span> (Port: 80, Comm Key: 101).
                    </p>
                </div>

                <!-- Status Hasil Uji Koneksi -->
                <div
                    v-if="connectionStatus"
                    class="p-3 rounded-xl text-xs flex items-start gap-2"
                    :class="connectionStatus.success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'"
                >
                    <CheckCircle2 v-if="connectionStatus.success" class="w-4 h-4 text-emerald-600 flex-shrink-0 mt-0.5" />
                    <AlertCircle v-else class="w-4 h-4 text-rose-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <span class="font-bold">{{ connectionStatus.success ? 'Koneksi Berhasil: ' : 'Koneksi Gagal: ' }}</span>
                        <span>{{ connectionStatus.message }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <button
                        type="button"
                        @click="testConnection"
                        :disabled="isTesting"
                        class="text-xs font-bold text-blue-600 hover:text-blue-500 underline disabled:opacity-50 inline-flex items-center gap-1"
                    >
                        <Wifi class="w-3.5 h-3.5" />
                        <span>{{ isTesting ? 'Menguji koneksi...' : 'Uji Koneksi SOAP' }}</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="showSyncModal = false"
                            class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="triggerSync"
                            :disabled="isSyncing"
                            class="px-4 py-2 text-xs font-bold bg-blue-600 hover:bg-blue-500 text-white rounded-lg shadow-xs disabled:opacity-50 inline-flex items-center gap-1.5"
                        >
                            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncing }" />
                            <span>{{ isSyncing ? 'Menarik data...' : 'Tarik Sekarang' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
