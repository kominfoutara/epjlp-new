<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ExecutiveAnalytics from '@/Components/ExecutiveAnalytics.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    role: String,
    userSeksi: String,
    currentMonthLabel: String,
    // PJLP Props (fallback for PJLP role)
    todayAttendance: Object,
    attendanceStats: Object,
    activityStats: Object,
    pjlpMonthlyStats: Array,
    availableYears: Array,
    recentActivities: Array,
    recentDinasLuar: Array,
    // Kasi & Admin Props
    totalPjlp: Number,
    filters: Object,
    kpiStats: Object,
    monthlyStats: Array,
    todayStats: Object,
    activities: Object,
    pjlpList: Array,
    categories: Array,
    pendingDinasLuar: Array,
    pendingDinasLuarCount: Number,
    systemStatus: Object,
    superadminData: Object,
});

const page = usePage();
const isSuperadmin = computed(() => props.role === 'Superadmin');
const isPjlp = computed(() => props.role === 'PJLP');
const isApprovedActivity = (status) => ['Disetujui', 'Diterima'].includes(status);
const changeExecutivePeriod = (event) => {
    const periode = event.target.value;
    if (!/^\d{4}-\d{2}$/.test(periode)) return;
    isRefreshing.value = true;
    router.get(route('dashboard'), { periode }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => { isRefreshing.value = false; },
    });
};

// ==========================================
// STATE & REACTIVE FILTERS
// ==========================================
const selectedYear = ref(props.filters?.tahun || new Date().getFullYear());
const selectedMonth = ref(props.filters?.bulan !== undefined ? String(props.filters.bulan) : 'all');
const selectedStatus = ref(props.filters?.status || 'all');
const selectedUserId = ref(props.filters?.user_id || '');
const selectedCategory = ref(props.filters?.kategori || 'all');
const searchQuery = ref(props.filters?.search || '');

// Sync filters when props update
watch(
    () => props.filters,
    (newFilters) => {
        if (newFilters) {
            if (newFilters.tahun !== undefined) selectedYear.value = newFilters.tahun;
            if (newFilters.bulan !== undefined) selectedMonth.value = String(newFilters.bulan);
            selectedStatus.value = newFilters.status || 'all';
            selectedUserId.value = newFilters.user_id || '';
            selectedCategory.value = newFilters.kategori || 'all';
            searchQuery.value = newFilters.search || '';
        }
    },
    { deep: true }
);

// Realtime & Sync State
const isRefreshing = ref(false);
const lastUpdatedTime = ref('Baru saja');
const autoSyncEnabled = ref(true);
let autoSyncTimer = null;
let lastUpdateSeconds = 0;
let secondsCounterTimer = null;

// UI Modals & Drawers
const showPresensiDrawer = ref(false);
const showDetailModal = ref(false);
const showRejectModal = ref(false);
const selectedActivity = ref(null);
const rejectionNote = ref('');
const rejectReasonPreset = ref('');
const previewPhotoUrl = ref(null);

const formatPhotoUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) {
        return path;
    }
    const cleanPath = path.replace(/^\/?storage\//, '').replace(/^\//, '');
    return '/storage/' + cleanPath;
};

// Batch Selection
const selectedIds = ref([]);
const isBatchProcessing = ref(false);

// Toast Notification
const toastMessage = ref('');
const toastType = ref('success');
const showToast = ref(false);

const triggerToast = (msg, type = 'success') => {
    toastMessage.value = msg;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 4000);
};

// ==========================================
// REFRESH & INERTIA FILTER APPLICATION
// ==========================================
const applyFilters = () => {
    isRefreshing.value = true;
    router.get(
        route('dashboard'),
        {
            tahun: selectedYear.value,
            bulan: selectedMonth.value,
            status: selectedStatus.value,
            user_id: selectedUserId.value || undefined,
            kategori: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: isPjlp.value
                ? ['activityStats', 'pjlpMonthlyStats', 'attendanceStats', 'filters', 'currentMonthLabel', 'availableYears']
                : ['kpiStats', 'monthlyStats', 'todayStats', 'activities', 'filters', 'currentMonthLabel', 'pjlpList', 'totalPjlp', 'categories', 'pendingDinasLuar', 'pendingDinasLuarCount'],
            onFinish: () => {
                isRefreshing.value = false;
                lastUpdateSeconds = 0;
                lastUpdatedTime.value = 'Baru saja';
            },
        }
    );
};

// Quick status filter by clicking cards
const filterByStatus = (status) => {
    if (selectedStatus.value === status) {
        selectedStatus.value = 'all'; // toggle back to all
    } else {
        selectedStatus.value = status;
    }
    applyFilters();
};

// Quick month filter by clicking bar chart
const filterByMonth = (monthIndex) => {
    if (selectedMonth.value === String(monthIndex)) {
        selectedMonth.value = 'all';
    } else {
        selectedMonth.value = String(monthIndex);
    }
    applyFilters();
};

// Reset all filters
const resetAllFilters = () => {
    selectedYear.value = new Date().getFullYear();
    selectedMonth.value = 'all';
    selectedStatus.value = 'all';
    selectedUserId.value = '';
    selectedCategory.value = 'all';
    searchQuery.value = '';
    applyFilters();
};

// PJLP Diagram Batang scaling by Percentage (0 - 100%)
const persenDisetujui = computed(() => {
    const total = props.activityStats?.total || 0;
    if (total === 0) return 0;
    return Math.round(((props.activityStats?.disetujui || 0) / total) * 1000) / 10;
});

const persenMenunggu = computed(() => {
    const total = props.activityStats?.total || 0;
    if (total === 0) return 0;
    return Math.round(((props.activityStats?.menunggu || 0) / total) * 1000) / 10;
});

const persenDitolak = computed(() => {
    const total = props.activityStats?.total || 0;
    if (total === 0) return 0;
    return Math.round(((props.activityStats?.ditolak || 0) / total) * 1000) / 10;
});

const maxPjlpMonthlyVolume = computed(() => {
    if (!props.pjlpMonthlyStats || props.pjlpMonthlyStats.length === 0) return 20;
    const maxVal = Math.max(...props.pjlpMonthlyStats.map((m) => Math.max(m.disetujui || 0, m.menunggu || 0, m.ditolak || 0, m.lainnya || 0)));
    return maxVal > 0 ? maxVal : 20;
});

// Realtime Timer
onMounted(() => {
    // Relative time ticker
    secondsCounterTimer = setInterval(() => {
        lastUpdateSeconds += 5;
        if (lastUpdateSeconds < 60) {
            lastUpdatedTime.value = `${lastUpdateSeconds} detik yang lalu`;
        } else {
            const mins = Math.floor(lastUpdateSeconds / 60);
            lastUpdatedTime.value = `${mins} menit yang lalu`;
        }
    }, 5000);

    // Auto-sync polling every 30 seconds
    autoSyncTimer = setInterval(() => {
        if (autoSyncEnabled.value && !isRefreshing.value) {
            router.reload({
                only: isSuperadmin.value ? ['superadminData'] : isPjlp.value
                    ? ['activityStats', 'pjlpMonthlyStats', 'attendanceStats', 'recentActivities', 'recentDinasLuar']
                    : ['kpiStats', 'monthlyStats', 'todayStats', 'activities', 'pjlpList', 'totalPjlp', 'categories', 'pendingDinasLuar', 'pendingDinasLuarCount'],
                onSuccess: () => {
                    lastUpdateSeconds = 0;
                    lastUpdatedTime.value = 'Baru saja';
                },
            });
        }
    }, 30000);
});

onUnmounted(() => {
    clearInterval(secondsCounterTimer);
    clearInterval(autoSyncTimer);
});

// ==========================================
// QUICK VALIDATION ACTIONS (REALTIME)
// ==========================================
const quickValidateSingle = (activity, status, note = null) => {
    // Optimistic UI update
    const oldStatus = activity.status_validasi;
    activity.status_validasi = status;

    if (props.kpiStats) {
        if (oldStatus === 'Menunggu' && status === 'Disetujui') {
            props.kpiStats.menunggu = Math.max(0, props.kpiStats.menunggu - 1);
            props.kpiStats.diterima += 1;
        } else if (oldStatus === 'Menunggu' && status === 'Ditolak') {
            props.kpiStats.menunggu = Math.max(0, props.kpiStats.menunggu - 1);
            props.kpiStats.ditolak += 1;
        }
    }

    router.post(
        route('activity.quick-validate', activity.id),
        { status, catatan: note },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                triggerToast(`Aktivitas berhasil diubah menjadi ${status}.`, 'success');
                if (showDetailModal.value) showDetailModal.value = false;
                if (showRejectModal.value) showRejectModal.value = false;
            },
            onError: () => {
                activity.status_validasi = oldStatus;
                triggerToast('Gagal memproses validasi aktivitas.', 'error');
            },
        }
    );
};

// Batch Validate Selected
const handleBatchValidate = (status) => {
    if (selectedIds.value.length === 0) return;

    isBatchProcessing.value = true;
    router.post(
        route('activity.validate-checkbox'),
        {
            activity_ids: selectedIds.value,
            status: status,
            catatan: status === 'Disetujui' ? 'Validasi massal via Dashboard Kasi' : 'Ditolak via Dashboard Kasi',
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                triggerToast(`${selectedIds.value.length} aktivitas berhasil divalidasi (${status}).`, 'success');
                selectedIds.value = [];
                isBatchProcessing.value = false;
            },
            onError: () => {
                triggerToast('Gagal memproses validasi massal.', 'error');
                isBatchProcessing.value = false;
            },
        }
    );
};

// Select All Checkbox Handler
const isAllSelected = computed(() => {
    const list = props.activities?.data || [];
    if (list.length === 0) return false;
    return list.every((item) => selectedIds.value.includes(item.id));
});

const toggleSelectAll = () => {
    const list = props.activities?.data || [];
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = list.map((item) => item.id);
    }
};

// Open Modals
const openDetail = (act) => {
    selectedActivity.value = act;
    showDetailModal.value = true;
};

const openRejectPrompt = (act) => {
    selectedActivity.value = act;
    rejectionNote.value = '';
    rejectReasonPreset.value = '';
    showRejectModal.value = true;
};

const submitRejectWithReason = () => {
    if (!selectedActivity.value) return;
    const finalNote = rejectionNote.value || rejectReasonPreset.value || 'Catatan perbaikan verifikator';
    quickValidateSingle(selectedActivity.value, 'Ditolak', finalNote);
};

// Formatting helpers
const formatNumber = (num) => {
    if (num === null || num === undefined) return '0';
    return Number(num).toLocaleString('id-ID');
};

const formatDateIndo = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
};

const getInitials = (name) => {
    if (!name) return 'PJ';
    const parts = name.trim().split(' ').filter(Boolean);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[1][0]).toUpperCase();
};

// Maximum bar volume for monthly chart height scaling
const maxMonthlyVolume = computed(() => {
    if (!props.monthlyStats || props.monthlyStats.length === 0) return 1000;
    const maxVal = Math.max(...props.monthlyStats.map((m) => m.total || 0));
    return maxVal > 0 ? maxVal : 1000;
});

// Month Names map
const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// ==========================================
// SUPERADMIN TATA KELOLA SPBE: STATE & FILTERS
// ==========================================
const superadminTab = ref('satker'); // 'satker' | 'pegawai' | 'telemetri'
const superadminSeksiSearch = ref('');
const superadminUserSearch = ref('');
const superadminRoleFilter = ref('all');

const filteredSuperadminSeksi = computed(() => {
    const list = props.superadminData?.seksiOverview || [];
    if (!superadminSeksiSearch.value.trim()) return list;
    const q = superadminSeksiSearch.value.toLowerCase().trim();
    return list.filter((s) =>
        (s.nama && s.nama.toLowerCase().includes(q)) ||
        (s.kode && s.kode.toLowerCase().includes(q)) ||
        (s.nama_kasi && s.nama_kasi.toLowerCase().includes(q))
    );
});

const filteredSuperadminUsers = computed(() => {
    const list = props.superadminData?.recentUsers || [];
    return list.filter((u) => {
        const matchesRole = superadminRoleFilter.value === 'all' || u.role === superadminRoleFilter.value;
        if (!matchesRole) return false;
        if (!superadminUserSearch.value.trim()) return true;
        const q = superadminUserSearch.value.toLowerCase().trim();
        return (
            (u.nama_lengkap && u.nama_lengkap.toLowerCase().includes(q)) ||
            (u.nik && u.nik.toLowerCase().includes(q)) ||
            (u.pin && String(u.pin).toLowerCase().includes(q)) ||
            (u.username && u.username.toLowerCase().includes(q)) ||
            (u.seksi && u.seksi.toLowerCase().includes(q)) ||
            (u.posisi_pekerjaan && u.posisi_pekerjaan.toLowerCase().includes(q))
        );
    });
});
</script>

<template>
    <Head :title="isSuperadmin ? `Dashboard Eksekutif ${superadminData?.periodLabel || ''}` : 'Dashboard Kinerja'" />

    <AuthenticatedLayout>
        <!-- ============================================================== -->
        <!-- FLOATING REALTIME TOAST -->
        <!-- ============================================================== -->
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="showToast" class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-2xl text-white font-medium text-xs sm:text-sm border"
                :class="toastType === 'success' ? 'bg-emerald-600 border-emerald-500' : 'bg-rose-600 border-rose-500'">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ toastMessage }}</span>
            </div>
        </transition>

        <!-- ============================================================== -->
        <!-- VIEW 1: SUPERADMIN (STANDAR TATA KELOLA SPBE & KEPEGAWAIAN) -->
        <!-- ============================================================== -->
        <div v-if="isSuperadmin" class="space-y-6 max-w-[1600px] mx-auto text-slate-800">
            <!-- 1. EXECUTIVE CONTEXT HEADER -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-1">
                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                            Dashboard Eksekutif Superadmin
                        </h1>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Pemantauan Terpadu
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">
                        Pusat Pengawasan Tata Kelola Kinerja & Kepegawaian PJLP • Suku Dinas Komunikasi, Informatika dan Statistik Kota Adm. Jakarta Utara
                    </p>
                </div>

                <!-- Primary Action Controls: Responsive Stack on Mobile -->
                <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap w-full sm:w-auto">
                    <label class="text-xs text-slate-500">
                        <span class="sr-only">Periode aktivitas dashboard</span>
                        <input type="month" :value="superadminData?.period" :disabled="isRefreshing" @change="changeExecutivePeriod" class="rounded-xl border-slate-200 bg-white py-2 text-xs text-slate-700" />
                    </label>
                    <Link :href="route('users.index')"
                        class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-slate-900 hover:bg-slate-800 text-white shadow-xs border border-transparent whitespace-nowrap transition active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Kelola Akun Pegawai</span>
                    </Link>
                    <Link :href="route('seksi.index')"
                        class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl text-xs font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-xs whitespace-nowrap transition active:scale-95">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span>Master Data Seksi</span>
                    </Link>
                </div>
            </div>

            <ExecutiveAnalytics v-if="superadminData" :data="superadminData" />

            <!-- 3. MAIN ENTERPRISE GOVERNANCE WORKSPACE (SEGMENTED TABS & DATA TABLES) -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
                <!-- Segmented Control Navigation Bar -->
                <div class="border-b border-slate-200 px-4 sm:px-6 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3 bg-slate-50/70">
                    <!-- Segmented Pill Buttons -->
                    <div class="inline-flex p-1 rounded-lg bg-slate-200/80 border border-slate-300/70 self-start">
                        <button type="button" @click="superadminTab = 'satker'"
                            class="px-3.5 py-1.5 rounded-md text-xs font-semibold transition-all flex items-center gap-2"
                            :class="superadminTab === 'satker'
                                ? 'bg-white text-slate-900 shadow-xs border border-slate-200'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                            <span>Struktur Satker & Kehadiran</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                {{ superadminData?.seksiOverview?.length || 0 }}
                            </span>
                        </button>

                        <button type="button" @click="superadminTab = 'pegawai'"
                            class="px-3.5 py-1.5 rounded-md text-xs font-semibold transition-all flex items-center gap-2"
                            :class="superadminTab === 'pegawai'
                                ? 'bg-white text-slate-900 shadow-xs border border-slate-200'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                            <span>Direktori Akun & Hak Akses</span>
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                {{ superadminData?.userBreakdown?.total || 0 }}
                            </span>
                        </button>

                        <button type="button" @click="superadminTab = 'telemetri'"
                            class="px-3.5 py-1.5 rounded-md text-xs font-semibold transition-all flex items-center gap-2"
                            :class="superadminTab === 'telemetri'
                                ? 'bg-white text-slate-900 shadow-xs border border-slate-200'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <span>Telemetri Server SPBE</span>
                        </button>
                    </div>

                    <!-- Right Metadata Tag -->
                    <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                        <span>Standar SPBE Pemprov DKI Jakarta • TA 2026</span>
                    </div>
                </div>

                <!-- TAB 1: STRUKTUR SATKER & PRESENSI KEHADIRAN -->
                <div v-show="superadminTab === 'satker'" class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 tracking-tight">
                                Rekapitulasi Struktur Seksi & Tingkat Kehadiran Personel
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Jumlah PJLP terdaftar per seksi. Hadir hari ini dihitung dari PJLP yang memiliki scan masuk atau keluar tersimpan.
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="relative w-full sm:w-64">
                                <input v-model="superadminSeksiSearch" type="text" placeholder="Cari seksi atau kode..."
                                    class="w-full pl-9 pr-3 py-1.5 rounded-lg text-xs border border-slate-300 bg-white text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-500 focus:border-slate-500" />
                                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                            <Link :href="route('seksi.index')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-900 hover:bg-slate-800 text-white border border-transparent shadow-xs transition whitespace-nowrap">
                                <span>Master Seksi</span>
                            </Link>
                        </div>
                    </div>

                    <div class="hidden md:block border border-slate-200 rounded-lg overflow-x-auto bg-white">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-200">
                                <tr>
                                    <th class="py-3 px-4 w-12 text-center">No</th>
                                    <th class="py-3 px-4 w-28">Kode Seksi</th>
                                    <th class="py-3 px-4">Satuan Kerja / Seksi</th>
                                    <th class="py-3 px-4">Kepala Seksi / Pengawas</th>
                                    <th class="py-3 px-4 text-center w-28">Alokasi PJLP</th>
                                    <th class="py-3 px-4 text-center w-32">Hadir Hari Ini</th>
                                    <th class="py-3 px-4 w-44">Tingkat Kehadiran</th>
                                    <th class="py-3 px-4 text-center w-24">Status</th>
                                    <th class="py-3 px-4 text-center w-28">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="(seksi, idx) in filteredSuperadminSeksi" :key="seksi.id" class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded font-mono text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ seksi.kode }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-slate-900">{{ seksi.nama }}</div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1">{{ seksi.deskripsi || 'Seksi operasional Sudin Kominfotik JU' }}</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-slate-900">{{ seksi.nama_kasi }}</div>
                                        <div class="text-[10px] text-slate-400">Pejabat Pengawas</div>
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono font-semibold text-slate-900">
                                        {{ seksi.total_pjlp }} Pegawai
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono font-semibold"
                                        :class="seksi.hadir_hari_ini > 0 ? 'text-slate-900' : 'text-slate-400'">
                                        {{ seksi.hadir_hari_ini }} Hadir
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2.5">
                                            <div class="flex-1 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                                <div class="bg-slate-700 h-1.5 rounded-full transition-all duration-300"
                                                    :style="{ width: `${seksi.total_pjlp > 0 ? Math.min(100, Math.round((seksi.hadir_hari_ini / seksi.total_pjlp) * 100)) : 0}%` }">
                                                </div>
                                            </div>
                                            <span class="font-mono text-[11px] font-semibold text-slate-600 w-9 text-right">
                                                {{ seksi.total_pjlp > 0 ? Math.round((seksi.hadir_hari_ini / seksi.total_pjlp) * 100) : 0 }}%
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-medium border"
                                            :class="seksi.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200/80' : 'bg-slate-100 text-slate-600 border-slate-200'">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="seksi.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                            {{ seksi.is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <Link :href="route('seksi.index')"
                                            class="inline-flex items-center px-2.5 py-1 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-[11px] font-medium border border-slate-200 shadow-2xs transition">
                                            Detail
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="filteredSuperadminSeksi.length === 0">
                                    <td colspan="9" class="py-8 text-center text-slate-400 text-xs">
                                        Tidak ada data seksi yang sesuai dengan pencarian "{{ superadminSeksiSearch }}".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Adaptive Cards Feed for Seksi Monitoring -->
                    <div class="block md:hidden space-y-3 pt-1">
                        <div v-if="filteredSuperadminSeksi.length === 0" class="py-8 text-center text-slate-400 text-xs bg-white rounded-xl border border-slate-200 p-4">
                            Tidak ada data seksi yang sesuai dengan pencarian "{{ superadminSeksiSearch }}".
                        </div>

                        <div v-for="(seksi, idx) in filteredSuperadminSeksi" :key="'mob-seksi-' + seksi.id"
                            class="p-4 rounded-2xl border border-slate-200 bg-white shadow-2xs space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ seksi.kode }}
                                        </span>
                                        <span class="font-bold text-xs text-slate-900">{{ seksi.nama }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-1">
                                        Pengawas: <strong>{{ seksi.nama_kasi }}</strong>
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-medium border flex-shrink-0"
                                    :class="seksi.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200/80' : 'bg-slate-100 text-slate-600 border-slate-200'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="seksi.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                    {{ seksi.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>

                            <!-- Progress & Kehadiran Bar -->
                            <div class="space-y-1.5 pt-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-500 text-[11px]">Kehadiran Hari Ini:</span>
                                    <span class="font-bold font-mono text-slate-900">
                                        {{ seksi.hadir_hari_ini }} / {{ seksi.total_pjlp }} Pegawai ({{ seksi.total_pjlp > 0 ? Math.round((seksi.hadir_hari_ini / seksi.total_pjlp) * 100) : 0 }}%)
                                    </span>
                                </div>
                                <div class="bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-emerald-600 h-2 rounded-full transition-all duration-300"
                                        :style="{ width: `${seksi.total_pjlp > 0 ? Math.min(100, Math.round((seksi.hadir_hari_ini / seksi.total_pjlp) * 100)) : 0}%` }">
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-100 flex justify-end">
                                <Link :href="route('seksi.index')"
                                    class="w-full py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold flex items-center justify-center gap-1.5 transition active:scale-95">
                                    <span>Kelola Seksi & Formasi</span>
                                    <span>→</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: DIREKTORI AKUN & HAK AKSES SPBE -->
                <div v-show="superadminTab === 'pegawai'" class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 tracking-tight">
                                Direktori Akun & Hak Akses Pengguna Terdaftar
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Verifikasi identitas pegawai, NIK, PIN absensi fingerprint, satuan kerja, dan peran hak akses dalam sistem.
                            </p>
                        </div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <select v-model="superadminRoleFilter" class="px-3 py-1.5 rounded-lg text-xs border border-slate-300 bg-white text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-500">
                                <option value="all">Semua Peran</option>
                                <option value="Superadmin">Superadmin</option>
                                <option value="Kasudin">Kasudin</option>
                                <option value="Kasi">Kasi</option>
                                <option value="PJLP">PJLP</option>
                            </select>
                            <div class="relative w-full sm:w-60">
                                <input v-model="superadminUserSearch" type="text" placeholder="Cari nama, NIK, PIN, seksi..."
                                    class="w-full pl-9 pr-3 py-1.5 rounded-lg text-xs border border-slate-300 bg-white text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-500 focus:border-slate-500" />
                                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                            <Link :href="route('users.index')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-900 hover:bg-slate-800 text-white border border-transparent shadow-xs transition whitespace-nowrap">
                                <span>Buka Seluruh Akun ({{ superadminData?.userBreakdown?.total || 0 }})</span>
                            </Link>
                        </div>
                    </div>

                    <div class="hidden md:block border border-slate-200 rounded-lg overflow-x-auto bg-white">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider text-[11px] border-b border-slate-200">
                                <tr>
                                    <th class="py-3 px-4 w-12 text-center">No</th>
                                    <th class="py-3 px-4">Identitas Pegawai</th>
                                    <th class="py-3 px-4 w-32">PIN / Fingerprint</th>
                                    <th class="py-3 px-4">Unit Kerja / Seksi</th>
                                    <th class="py-3 px-4">Formasi Tugas KAK</th>
                                    <th class="py-3 px-4 text-center w-28">Hak Akses</th>
                                    <th class="py-3 px-4 text-center w-24">Status</th>
                                    <th class="py-3 px-4 text-center w-24">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="(user, idx) in filteredSuperadminUsers" :key="user.id" class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 text-center font-mono text-slate-400">{{ idx + 1 }}</td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center font-semibold text-xs uppercase flex-shrink-0">
                                                {{ getInitials(user.nama_lengkap) }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-slate-900">{{ user.nama_lengkap }}</div>
                                                <div class="text-[11px] text-slate-500 font-mono">
                                                    NIK: {{ user.nik || '-' }} • ID: {{ user.username }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-slate-800">
                                        <span v-if="user.pin" class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 font-semibold text-[11px]">
                                            {{ user.pin }}
                                        </span>
                                        <span v-else class="text-slate-400 italic text-[11px]">Tidak Ada</span>
                                    </td>
                                    <td class="py-3 px-4 font-medium text-slate-800">
                                        {{ user.seksi || 'Subbag Tata Usaha' }}
                                    </td>
                                    <td class="py-3 px-4 text-slate-600">
                                        {{ user.posisi_pekerjaan || 'Staf / Administrator' }}
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2.5 py-0.5 rounded text-[10px] font-mono tracking-wide uppercase"
                                            :class="user.role === 'Superadmin'
                                                ? 'bg-slate-900 text-white font-bold'
                                                : 'bg-slate-100 text-slate-800 border border-slate-200 font-medium'">
                                            {{ user.role }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                            <span class="w-1.5 h-1.5 rounded-full" :class="user.status_user === 'Aktif' ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                            {{ user.status_user }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <Link :href="route('users.index')"
                                            class="inline-flex items-center px-2.5 py-1 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-[11px] font-medium border border-slate-200 shadow-2xs transition">
                                            Kelola
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="filteredSuperadminUsers.length === 0">
                                    <td colspan="8" class="py-8 text-center text-slate-400 text-xs">
                                        Tidak ada akun pegawai yang sesuai dengan parameter filter.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Adaptive Cards Feed for User Directory -->
                    <div class="block md:hidden space-y-3 pt-1">
                        <div v-if="filteredSuperadminUsers.length === 0" class="py-8 text-center text-slate-400 text-xs bg-white rounded-xl border border-slate-200 p-4">
                            Tidak ada akun pegawai yang sesuai dengan parameter filter.
                        </div>

                        <div v-for="(user, idx) in filteredSuperadminUsers" :key="'mob-sa-user-' + user.id"
                            class="p-4 rounded-2xl border border-slate-200 bg-white shadow-2xs space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-full bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center font-semibold text-xs uppercase flex-shrink-0">
                                        {{ getInitials(user.nama_lengkap) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-xs text-slate-900 truncate">{{ user.nama_lengkap }}</div>
                                        <div class="text-[11px] text-slate-500 font-mono mt-0.5 truncate">
                                            ID: {{ user.username }}
                                        </div>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono tracking-wide uppercase font-bold"
                                    :class="user.role === 'Superadmin'
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-slate-100 text-slate-800 border border-slate-200'">
                                    {{ user.role }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                                <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="text-[10px] text-slate-400 uppercase font-bold">Unit Kerja</div>
                                    <div class="font-semibold text-slate-800 truncate mt-0.5">{{ user.seksi || 'Subbag TU' }}</div>
                                </div>
                                <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                                    <div class="text-[10px] text-slate-400 uppercase font-bold">PIN Absensi</div>
                                    <div class="font-mono font-bold text-slate-800 truncate mt-0.5">{{ user.pin || '-' }}</div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="user.status_user === 'Aktif' ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                    {{ user.status_user }}
                                </span>

                                <Link :href="route('users.index')"
                                    class="py-1.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold flex items-center gap-1 transition active:scale-95">
                                    <span>Kelola Akun</span>
                                    <span>→</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: TELEMETRI SPBE & AUDIT TRAIL -->
                <div v-show="superadminTab === 'telemetri'" class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        <!-- Left: Server & Platform Specification (5 cols) -->
                        <div class="lg:col-span-5 space-y-4">
                            <div class="border border-slate-200 rounded-xl p-5 bg-slate-50/60 shadow-2xs">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 pb-3 border-b border-slate-200 flex items-center justify-between">
                                    <span>Spesifikasi Server & SPBE</span>
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                </h4>
                                <div class="space-y-2.5 mt-3 text-xs">
                                    <div class="flex justify-between py-1 border-b border-slate-200/70">
                                        <span class="text-slate-500">Nama Sistem:</span>
                                        <span class="font-semibold text-slate-900">e-Kinerja PJLP Jakarta Utara</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-slate-200/70">
                                        <span class="text-slate-500">Penyelenggara:</span>
                                        <span class="font-semibold text-slate-900 text-right">Sudin Kominfotik Adm. Jakut</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-slate-200/70">
                                        <span class="text-slate-500">Framework Core:</span>
                                        <span class="font-mono font-semibold text-slate-900">Laravel {{ superadminData?.systemInfo?.laravel_version }}</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-slate-200/70">
                                        <span class="text-slate-500">PHP Runtime:</span>
                                        <span class="font-mono font-semibold text-slate-900">v{{ superadminData?.systemInfo?.php_version }}</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-slate-200/70">
                                        <span class="text-slate-500">Database Driver:</span>
                                        <span class="font-mono font-semibold text-slate-900 uppercase">{{ superadminData?.systemInfo?.database }}</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-slate-200/70">
                                        <span class="text-slate-500">Platform OS:</span>
                                        <span class="font-mono font-semibold text-slate-900">{{ superadminData?.systemInfo?.server_os }}</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-slate-200/70">
                                        <span class="text-slate-500">Lingkungan:</span>
                                        <span class="inline-flex items-center gap-1 font-semibold text-slate-800 uppercase">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            {{ superadminData?.systemInfo?.app_env }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between py-1">
                                        <span class="text-slate-500">Waktu Acuan Server:</span>
                                        <span class="font-mono text-[11px] font-semibold text-slate-900">{{ superadminData?.systemInfo?.current_time }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Audit Trail Pelaporan Terkini Lintas Seksi (7 cols) -->
                        <div class="lg:col-span-7 space-y-4">
                            <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-2xs">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                        Audit Trail Pelaporan Kinerja Lintas Seksi
                                    </h4>
                                    <Link :href="route('activity.index')" class="text-xs font-semibold text-slate-600 hover:text-slate-900 transition">
                                        Buka Seluruh Laporan →
                                    </Link>
                                </div>

                                <div class="space-y-3">
                                    <div v-for="act in superadminData?.recentActivities || []" :key="act.id"
                                        class="p-3.5 rounded-lg border border-slate-200 bg-slate-50/50 hover:border-slate-300 transition text-xs">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <span class="font-semibold text-slate-900">{{ act.user?.nama_lengkap || 'Pegawai PJLP' }}</span>
                                                <span class="text-slate-500 text-[11px] ml-1.5">({{ act.user?.seksi || 'Seksi' }})</span>
                                            </div>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                                <span class="w-1.5 h-1.5 rounded-full"
                                                    :class="{
                                                        'bg-emerald-500': isApprovedActivity(act.status_validasi),
                                                        'bg-amber-500': act.status_validasi === 'Menunggu',
                                                        'bg-rose-500': act.status_validasi === 'Ditolak',
                                                    }"></span>
                                                {{ act.status_validasi }}
                                            </span>
                                        </div>
                                        <div class="text-slate-600 mt-1 line-clamp-1 text-[11px]">
                                            {{ act.uraian }}
                                        </div>
                                        <div class="flex items-center justify-between text-[10px] text-slate-400 mt-2.5 pt-2 border-t border-slate-200/70">
                                            <span>Kategori: <strong class="text-slate-700">{{ act.kategori }}</strong></span>
                                            <span class="font-mono text-slate-500">{{ formatDateIndo(act.tanggal) }} ({{ act.jam_mulai?.slice(0, 5) || '08:00' }} - {{ act.jam_selesai?.slice(0, 5) || '16:00' }})</span>
                                        </div>
                                    </div>
                                    <div v-if="!superadminData?.recentActivities || superadminData?.recentActivities?.length === 0" class="py-8 text-center text-slate-400 text-xs">
                                        Belum ada aktivitas yang tercatat untuk audit trail.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- VIEW 2: KASI / KASUDIN (MONITORING & VALIDASI AKTIVITAS) -->
        <!-- ============================================================== -->
        <div v-else-if="!isPjlp" class="space-y-6 max-w-[1600px] mx-auto">
            <!-- 1. HEADER SECTION -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 sm:gap-5">
                <div>
                    <div class="flex items-center gap-2.5 mb-1.5 flex-wrap">
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                            Dashboard Kinerja
                        </h1>
                        <span class="px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-700">
                            TA {{ selectedYear }}
                        </span>
                        <span v-if="userSeksi" class="px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 ring-1 ring-slate-200 dark:ring-slate-700">
                            {{ userSeksi }}
                        </span>
                        <span class="px-2.5 py-0.5 rounded-md text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 inline-flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Sinkronisasi Aktif
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-3xl leading-relaxed">
                        Ringkasan data capaian, verifikasi presensi, dan pelaporan aktivitas pegawai PJLP Pemerintah Kota Administrasi Jakarta Utara.
                    </p>
                </div>

                <!-- Right Header Controls -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full lg:w-auto">
                    <!-- Period Filter Selector -->
                    <div class="flex-1 sm:flex-initial flex items-center justify-between sm:justify-start gap-2 bg-slate-50 dark:bg-slate-800/80 px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-slate-500 font-medium">Periode:</span>
                        </div>
                        <select v-model="selectedMonth" @change="applyFilters" class="bg-transparent border-0 p-0 text-xs font-bold text-slate-800 dark:text-slate-200 focus:ring-0 cursor-pointer">
                            <option value="all">Januari – Desember {{ selectedYear }}</option>
                            <option v-for="(mName, idx) in monthNames" :key="idx" :value="String(idx + 1)">
                                {{ mName }} {{ selectedYear }}
                            </option>
                        </select>
                    </div>

                    <!-- Staff PJLP Filter (Cross-linked) -->
                    <input v-model="selectedYear" @change="applyFilters" type="number" min="1900" max="9999" aria-label="Tahun dashboard" class="w-24 rounded-xl border-slate-200 text-xs font-semibold" />
                    <div v-if="pjlpList && pjlpList.length > 0" class="flex-1 sm:flex-initial flex items-center justify-between sm:justify-start gap-2 bg-slate-50 dark:bg-slate-800/80 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs">
                        <select v-model="selectedUserId" @change="applyFilters" class="w-full bg-transparent border-0 p-0 text-xs font-medium text-slate-700 dark:text-slate-300 focus:ring-0 cursor-pointer">
                            <option value="">Semua Pegawai PJLP ({{ totalPjlp }})</option>
                            <option v-for="p in pjlpList" :key="p.id" :value="p.id">
                                {{ p.nama_lengkap }} ({{ p.posisi || 'PJLP' }})
                            </option>
                        </select>
                    </div>

                    <!-- Instant Refresh Button -->
                    <button @click="applyFilters"
                        type="button"
                        :title="`Terakhir diperbarui: ${lastUpdatedTime}`"
                        class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 hover:text-emerald-600 transition shadow-2xs flex items-center justify-center active:scale-95"
                        :class="{ 'opacity-50 pointer-events-none': isRefreshing }">
                        <svg class="w-4 h-4" :class="{ 'animate-spin text-emerald-600': isRefreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- 2. TOP 4 KPI CARDS (INTERACTIVE CLICK-TO-FILTER SYSTEM) -->
            <div class="grid grid-cols-2 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
                <!-- Card 1: MENUNGGU VALIDASI -->
                <div @click="filterByStatus('Menunggu')"
                    class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 sm:p-5 border transition-all duration-200 cursor-pointer shadow-sm hover:shadow-md relative group select-none active:scale-95"
                    :class="selectedStatus === 'Menunggu'
                        ? 'border-amber-500 ring-2 ring-amber-500/20 bg-amber-50/20 dark:bg-amber-950/20'
                        : 'border-slate-200/80 dark:border-slate-800 hover:border-amber-300'">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-[10px] sm:text-[11px] font-bold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
                                Menunggu
                            </div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 mt-1 sm:mt-2">
                                {{ formatNumber(kpiStats?.menunggu || 0) }}
                            </div>
                        </div>
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800/60 flex items-center justify-center text-amber-600 dark:text-amber-400 flex-shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 sm:mt-4 pt-2.5 sm:pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] sm:text-xs">
                        <span class="inline-flex items-center gap-1.5 text-amber-600 dark:text-amber-400 font-medium truncate">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            Perlu verifikasi
                        </span>
                        <span class="font-bold text-amber-600 hover:text-amber-700 hidden sm:inline-flex items-center gap-1">
                            Tinjau →
                        </span>
                    </div>
                </div>

                <!-- Card 2: AKTIVITAS DITERIMA -->
                <div @click="filterByStatus('Disetujui')"
                    class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 sm:p-5 border transition-all duration-200 cursor-pointer shadow-sm hover:shadow-md relative group select-none active:scale-95"
                    :class="selectedStatus === 'Disetujui'
                        ? 'border-emerald-500 ring-2 ring-emerald-500/20 bg-emerald-50/20 dark:bg-emerald-950/20'
                        : 'border-slate-200/80 dark:border-slate-800 hover:border-emerald-300'">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-[10px] sm:text-[11px] font-bold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
                                Diterima
                            </div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 mt-1 sm:mt-2">
                                {{ formatNumber(kpiStats?.diterima || 0) }}
                            </div>
                        </div>
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 flex items-center justify-center text-emerald-600 dark:text-emerald-400 flex-shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 sm:mt-4 pt-2.5 sm:pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] sm:text-xs">
                        <span class="text-slate-500 font-medium truncate">
                            <strong class="text-slate-800 dark:text-slate-200">{{ kpiStats?.persen_diterima ?? 0 }}%</strong> total
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
                            Valid
                        </span>
                    </div>
                </div>

                <!-- Card 3: AKTIVITAS DITOLAK -->
                <div @click="filterByStatus('Ditolak')"
                    class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 sm:p-5 border transition-all duration-200 cursor-pointer shadow-sm hover:shadow-md relative group select-none active:scale-95"
                    :class="selectedStatus === 'Ditolak'
                        ? 'border-rose-500 ring-2 ring-rose-500/20 bg-rose-50/20 dark:bg-rose-950/20'
                        : 'border-slate-200/80 dark:border-slate-800 hover:border-rose-300'">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-[10px] sm:text-[11px] font-bold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
                                Ditolak
                            </div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 mt-1 sm:mt-2">
                                {{ formatNumber(kpiStats?.ditolak || 0) }}
                            </div>
                        </div>
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800/60 flex items-center justify-center text-rose-600 dark:text-rose-400 flex-shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 sm:mt-4 pt-2.5 sm:pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] sm:text-xs">
                        <span class="text-slate-500 font-medium truncate">
                            <strong class="text-slate-800 dark:text-slate-200">{{ kpiStats?.persen_ditolak || '0.0' }}%</strong> ditolak
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                            Revisi
                        </span>
                    </div>
                </div>

                <!-- Card 4: TOTAL AKTIVITAS -->
                <div @click="filterByStatus('all')"
                    class="bg-white dark:bg-slate-900 rounded-2xl p-3.5 sm:p-5 border transition-all duration-200 cursor-pointer shadow-sm hover:shadow-md relative group select-none active:scale-95"
                    :class="selectedStatus === 'all'
                        ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50/20 dark:bg-blue-950/20'
                        : 'border-slate-200/80 dark:border-slate-800 hover:border-blue-300'">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-[10px] sm:text-[11px] font-bold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
                                Total Laporan
                            </div>
                            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 mt-1 sm:mt-2">
                                {{ formatNumber(kpiStats?.total || 0) }}
                            </div>
                        </div>
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800/60 flex items-center justify-center text-blue-600 dark:text-blue-400 flex-shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 sm:mt-4 pt-2.5 sm:pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] sm:text-xs">
                        <span class="text-slate-500 font-medium truncate">{{ currentMonthLabel }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300">
                            Data tercatat
                        </span>
                    </div>
                </div>
            </div>

            <!-- 3. INTEGRATED REAL-TIME PRESENSI & DINAS LUAR STATUS BAR -->
            <div class="bg-slate-900 rounded-xl p-5 text-white shadow-sm border border-slate-800">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs uppercase tracking-wider text-emerald-400 font-bold flex items-center gap-2">
                                <span>Presensi Terintegrasi Realtime (Hari Ini)</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                            </div>
                            <div class="text-sm text-slate-300 mt-0.5">
                                Catatan presensi hari ini dari database lokal. Pembaruan dashboard tidak menarik data mesin otomatis.
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Metrics Pills -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            WFO: {{ todayStats?.total_hadir || 0 }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                            <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                            Dinas Luar: {{ todayStats?.total_dl || 0 }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                            Cuti/Sakit: {{ (todayStats?.total_sakit || 0) + (todayStats?.total_cuti || 0) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                            <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                            Belum Hadir: {{ todayStats?.total_belum_hadir || 0 }}
                        </span>

                        <button @click="showPresensiDrawer = !showPresensiDrawer"
                            type="button"
                            class="px-3 py-1 rounded-lg text-xs font-bold bg-white text-slate-900 hover:bg-slate-100 transition shadow-sm ml-1">
                            {{ showPresensiDrawer ? 'Tutup Detail' : 'Detail Presensi' }}
                        </button>
                    </div>
                </div>

                <!-- Expanded Presensi Drawer Table -->
                <div v-if="showPresensiDrawer" class="mt-4 pt-4 border-t border-slate-800 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        <div v-for="person in todayStats?.list || []" :key="person.id" class="p-3 rounded-xl bg-slate-800/60 border border-slate-700/80 flex items-center justify-between">
                            <div>
                                <div class="font-bold text-slate-200">{{ person.nama_lengkap }}</div>
                                <div class="text-[11px] text-slate-400">{{ person.posisi || 'PJLP' }} • PIN: {{ person.pin }}</div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold"
                                    :class="{
                                        'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': person.status === 'WFO',
                                        'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30': person.status.startsWith('DL'),
                                        'bg-rose-500/20 text-rose-300 border border-rose-500/30': person.status === 'BELUM HADIR',
                                    }">
                                    {{ person.status }}
                                </span>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">In: {{ person.masuk }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. MIDDLE SECTION: 2 SYNCHRONIZED CHARTS -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- CHART 1: STATISTIK AKTIVITAS BULANAN (BAR CHART) - 2 COLS -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                            <div>
                                <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                                    Statistik Aktivitas Bulanan
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Perbandingan volume dan status verifikasi per bulan (T.A {{ selectedYear }})
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                Tahun: {{ selectedYear }}
                            </span>
                        </div>

                        <!-- Chart Legends -->
                        <div class="flex items-center gap-4 text-xs font-medium text-slate-600 dark:text-slate-400 mb-6 flex-wrap">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-sm bg-[#1e293b]"></span> Total Aktivitas
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-sm bg-[#0d9488]"></span> Diterima
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-sm bg-[#f59e0b]"></span> Menunggu
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="w-3 h-3 rounded-sm bg-[#f43f5e]"></span> Ditolak
                            </span>
                            <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-slate-400"></span>Status lainnya</span>
                        </div>

                        <!-- Interactive SVG Bar Chart -->
                        <div class="h-64 sm:h-72 w-full flex items-end justify-between gap-1 sm:gap-2 px-2 pb-2 pt-6 border-b border-slate-100 dark:border-slate-800 relative">
                            <!-- Background Grid Lines -->
                            <div class="absolute inset-x-0 top-0 border-b border-slate-100 dark:border-slate-800/60"></div>
                            <div class="absolute inset-x-0 top-1/3 border-b border-slate-100 dark:border-slate-800/60"></div>
                            <div class="absolute inset-x-0 top-2/3 border-b border-slate-100 dark:border-slate-800/60"></div>

                            <!-- 12 Month Bars -->
                            <div v-for="m in monthlyStats" :key="m.bulan"
                                @click="filterByMonth(m.bulan)"
                                class="flex-1 flex flex-col items-center h-full justify-end group cursor-pointer relative z-10 transition-transform hover:-translate-y-1">
                                <!-- Tooltip on Hover -->
                                <div class="absolute -top-12 opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity bg-slate-900 text-white text-[10px] rounded-lg px-2.5 py-1.5 shadow-xl whitespace-nowrap z-20">
                                    <div class="font-bold">{{ m.nama }}: {{ formatNumber(m.total) }} entri</div>
                                    <div class="text-emerald-400">Diterima: {{ formatNumber(m.diterima) }}</div>
                                    <div class="text-amber-400">Menunggu: {{ formatNumber(m.menunggu) }}</div>
                                </div>

                                <!-- Bars Group -->
                                <div class="w-full max-w-[28px] flex items-end justify-center gap-0.5 h-full relative"
                                    :class="selectedMonth === String(m.bulan) ? 'ring-2 ring-emerald-500 rounded-t-md' : ''">
                                    <!-- Bar Total (Dark Slate) -->
                                    <div class="w-1/2 bg-slate-800 dark:bg-slate-700 rounded-t-sm transition-all duration-300"
                                        :style="{ height: `${Math.min(100, (m.total / maxMonthlyVolume) * 100)}%` }">
                                    </div>
                                    <!-- Bar Diterima (Teal/Emerald) -->
                                    <div class="w-1/2 bg-teal-600 dark:bg-teal-500 rounded-t-sm transition-all duration-300"
                                        :style="{ height: `${Math.min(100, (m.diterima / maxMonthlyVolume) * 100)}%` }">
                                    </div>
                                    <div class="w-1/2 rounded-t-sm bg-amber-500" :style="{ height: `${m.menunggu / maxMonthlyVolume * 100}%` }" :title="`Menunggu: ${m.menunggu}`"></div>
                                    <div class="w-1/2 rounded-t-sm bg-rose-500" :style="{ height: `${m.ditolak / maxMonthlyVolume * 100}%` }" :title="`Ditolak: ${m.ditolak}`"></div>
                                    <div class="w-1/2 rounded-t-sm bg-slate-400" :style="{ height: `${(m.lainnya || 0) / maxMonthlyVolume * 100}%` }" :title="`Status lainnya: ${m.lainnya || 0}`"></div>
                                </div>

                                <!-- X-Axis Label -->
                                <span class="text-[11px] font-semibold mt-2 transition"
                                    :class="selectedMonth === String(m.bulan)
                                        ? 'text-emerald-600 dark:text-emerald-400 font-bold'
                                        : 'text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-200'">
                                    {{ m.label }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Chart Footer Insight Note matching screenshot -->
                    <div class="mt-4 pt-3 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-700 dark:text-slate-300">Catatan:</span>
                            <span>{{ kpiStats?.insight_text || 'Belum ada aktivitas untuk filter yang dipilih.' }}</span>
                        </div>
                        <button @click="applyFilters()" type="button" class="font-bold text-slate-700 dark:text-slate-300 hover:text-emerald-600 transition">
                            Lihat Rincian Bulanan →
                        </button>
                    </div>
                </div>

                <!-- CHART 2: PROPORSI STATUS (DONUT CHART) - 1 COL -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="mb-4">
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                                Proporsi Status
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ currentMonthLabel }} · Diterima mencakup Disetujui dan Diterima pada data lama
                            </p>
                        </div>

                        <!-- Donut Chart Representation -->
                        <div class="relative w-48 h-48 mx-auto my-3 flex items-center justify-center">
                            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                                <!-- Background Circle -->
                                <circle cx="50" cy="50" r="38" fill="transparent" stroke="#f1f5f9" stroke-width="12" class="dark:stroke-slate-800" />
                                <!-- Diterima Arc (Emerald) -->
                                <circle cx="50" cy="50" r="38" fill="transparent" stroke="#0d9488" stroke-width="12"
                                    :stroke-dasharray="`${(kpiStats?.persen_diterima ?? 0) * 2.387} 238.7`"
                                    stroke-dashoffset="0"
                                    stroke-linecap="butt"
                                    class="transition-all duration-700 cursor-pointer hover:opacity-90" />
                                <!-- Menunggu Arc (Amber) -->
                                <circle cx="50" cy="50" r="38" fill="transparent" stroke="#f59e0b" stroke-width="12"
                                    :stroke-dasharray="`${(kpiStats?.persen_menunggu ?? 0) * 2.387} 238.7`"
                                    :stroke-dashoffset="`-${(kpiStats?.persen_diterima ?? 0) * 2.387}`"
                                    stroke-linecap="butt"
                                    class="transition-all duration-700 cursor-pointer hover:opacity-90" />
                                <circle cx="50" cy="50" r="38" fill="transparent" stroke="#f43f5e" stroke-width="12"
                                    :stroke-dasharray="`${(kpiStats?.persen_ditolak ?? 0) * 2.387} 238.7`"
                                    :stroke-dashoffset="`-${((kpiStats?.persen_diterima ?? 0) + (kpiStats?.persen_menunggu ?? 0)) * 2.387}`" />
                            </svg>

                            <!-- Center KPI -->
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span class="text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight">
                                    {{ formatNumber(kpiStats?.total ?? 0) }}
                                </span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">
                                    Total Aktivitas
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Legend Items with Counts and Percentages -->
                    <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800 text-xs">
                        <!-- Aktivitas Diterima -->
                        <div @click="filterByStatus('Disetujui')"
                            class="flex items-center justify-between p-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition select-none"
                            :class="selectedStatus === 'Disetujui' ? 'bg-emerald-50 dark:bg-emerald-950/40 ring-1 ring-emerald-300' : ''">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-sm bg-teal-600"></span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">Aktivitas Diterima</span>
                            </div>
                            <div class="flex items-center gap-2 font-mono">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatNumber(kpiStats?.diterima ?? 0) }}</span>
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ kpiStats?.persen_diterima ?? 0 }}%
                                </span>
                            </div>
                        </div>

                        <!-- Menunggu Validasi -->
                        <div @click="filterByStatus('Menunggu')"
                            class="flex items-center justify-between p-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition select-none"
                            :class="selectedStatus === 'Menunggu' ? 'bg-amber-50 dark:bg-amber-950/40 ring-1 ring-amber-300' : ''">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-sm bg-amber-500"></span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">Menunggu Validasi</span>
                            </div>
                            <div class="flex items-center gap-2 font-mono">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatNumber(kpiStats?.menunggu ?? 0) }}</span>
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                    {{ kpiStats?.persen_menunggu ?? 0 }}%
                                </span>
                            </div>
                        </div>

                        <!-- Aktivitas Ditolak -->
                        <div @click="filterByStatus('Ditolak')"
                            class="flex items-center justify-between p-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition select-none"
                            :class="selectedStatus === 'Ditolak' ? 'bg-rose-50 dark:bg-rose-950/40 ring-1 ring-rose-300' : ''">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-sm bg-rose-500"></span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">Aktivitas Ditolak</span>
                            </div>
                            <div class="flex items-center gap-2 font-mono">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ formatNumber(kpiStats?.ditolak || 0) }}</span>
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                    {{ kpiStats?.persen_ditolak || '0.0' }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="kpiStats?.lainnya" class="rounded-lg bg-slate-100 p-3 text-xs text-slate-600">{{ kpiStats.lainnya }} aktivitas memiliki status lain/kosong. Bagian abu-abu pada diagram mewakili status tersebut.</p>
            <p v-if="!kpiStats?.total" class="rounded-lg bg-slate-100 p-3 text-xs text-slate-600">Belum ada aktivitas untuk periode dan filter yang dipilih.</p>
            <p class="text-xs text-slate-500">Ringkasan dan donut mengikuti periode, pegawai, kategori, dan pencarian. Tren menunjukkan 12 bulan pada tahun terpilih. Pilihan status hanya menyaring tabel.</p>
            <!-- 5. WORK QUEUE TABLE (AKTIVITAS TERBARU YANG DIAJUKAN) -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
                <!-- Table Header & Filter Toolbar -->
                <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                                Aktivitas Terbaru yang Diajukan
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Daftar entri pekerjaan harian pegawai yang memerlukan tindak lanjut atau verifikasi terkini
                            </p>
                        </div>
                    </div>

                    <!-- Search and Filter Bar -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
                        <!-- Search Input -->
                        <div class="relative flex-1 sm:flex-initial">
                            <input v-model="searchQuery"
                                @keyup.enter="applyFilters"
                                type="text"
                                placeholder="Cari uraian atau nomor..."
                                class="w-full sm:w-72 pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-emerald-500 transition" />
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        <!-- Category Filter Dropdown -->
                        <select v-model="selectedCategory" @change="applyFilters"
                            class="py-2 pl-3 pr-8 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 cursor-pointer w-full sm:w-auto">
                            <option value="all">Semua Kategori</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>

                        <!-- Reset Filter Button -->
                        <button v-if="selectedStatus !== 'all' || selectedMonth !== 'all' || selectedCategory !== 'all' || selectedUserId || searchQuery"
                            @click="resetAllFilters"
                            type="button"
                            class="px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition text-center">
                            Reset Filter ✕
                        </button>
                    </div>
                </div>

                <!-- Batch Actions Bar (Shows when rows are selected) -->
                <div v-if="selectedIds.length > 0" class="bg-emerald-50 dark:bg-emerald-950/40 px-4 sm:px-6 py-3 border-b border-emerald-200 dark:border-emerald-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-xs font-medium text-emerald-900 dark:text-emerald-200">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                        <strong>{{ selectedIds.length }} aktivitas dipilih</strong>
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button @click="handleBatchValidate('Disetujui')"
                            :disabled="isBatchProcessing"
                            type="button"
                            class="flex-1 sm:flex-initial px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-500 transition shadow-sm text-center active:scale-95">
                            ✓ Setujui ({{ selectedIds.length }})
                        </button>
                        <button @click="handleBatchValidate('Ditolak')"
                            :disabled="isBatchProcessing"
                            type="button"
                            class="flex-1 sm:flex-initial px-3 py-1.5 rounded-lg bg-rose-600 text-white font-bold hover:bg-rose-500 transition shadow-sm text-center active:scale-95">
                            ✕ Tolak ({{ selectedIds.length }})
                        </button>
                    </div>
                </div>

                <!-- Desktop Data Table (MD and UP) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 text-[11px]">
                            <tr>
                                <th class="py-3.5 px-4 w-10 text-center">
                                    <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
                                </th>
                                <th class="py-3.5 px-4">Waktu & Tanggal</th>
                                <th class="py-3.5 px-4">Uraian Tugas / Output</th>
                                <th class="py-3.5 px-4">Kategori Pelayanan</th>
                                <th class="py-3.5 px-4">Volume</th>
                                <th class="py-3.5 px-4">Status Validasi</th>
                                <th class="py-3.5 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <!-- Empty State -->
                            <tr v-if="!activities?.data || activities.data.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="text-sm font-semibold">Tidak ada aktivitas yang sesuai dengan filter.</div>
                                    <p class="text-xs mt-1">Silakan sesuaikan filter bulan, status, atau kata kunci pencarian.</p>
                                </td>
                            </tr>

                            <!-- Row Entries -->
                            <tr v-for="act in activities?.data || []" :key="act.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition group">
                                <!-- Checkbox -->
                                <td class="py-4 px-4 text-center">
                                    <input type="checkbox" :value="act.id" v-model="selectedIds" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
                                </td>

                                <!-- Waktu & Tanggal -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ formatDateIndo(act.tanggal) }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono mt-0.5">{{ act.jam_mulai }} - {{ act.jam_selesai }} WIB</div>
                                    <div v-if="act.user" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium line-clamp-1 mt-0.5">
                                        {{ act.user.nama_lengkap }}
                                    </div>
                                </td>

                                <!-- Uraian Tugas / Output -->
                                <td class="py-4 px-4 max-w-md">
                                    <div class="font-bold text-slate-900 dark:text-slate-100 line-clamp-1">
                                        {{ act.uraian.split('.')[0] }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 mt-0.5">
                                        {{ act.uraian }}
                                    </div>
                                    <div v-if="act.nama_unit" class="text-[10px] text-slate-400 mt-1 flex items-center gap-1">
                                        <span>Unit: {{ act.nama_unit }}</span>
                                        <span v-if="act.lokasi">• {{ act.lokasi }}</span>
                                    </div>
                                </td>

                                <!-- Kategori Pelayanan -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 ring-1 ring-slate-200/60 dark:ring-slate-700">
                                        {{ act.kategori }}
                                    </span>
                                </td>

                                <!-- Volume -->
                                <td class="py-4 px-4 whitespace-nowrap font-medium text-slate-800 dark:text-slate-200">
                                    {{ act.no || '1 Laporan' }}
                                </td>

                                <!-- Status Validasi Pill matching screenshot -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <span v-if="act.status_validasi === 'Menunggu'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        Menunggu Validasi
                                    </span>
                                    <span v-else-if="isApprovedActivity(act.status_validasi)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        {{ act.status_validasi }}
                                    </span>
                                    <span v-else-if="act.status_validasi === 'Ditolak'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        Ditolak
                                    </span>
                                    <span v-else class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">
                                        {{ act.status_validasi || 'Status belum tersedia' }}
                                    </span>
                                </td>

                                <!-- Aksi Buttons -->
                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <!-- Detail Modal Trigger -->
                                        <button @click="openDetail(act)"
                                            type="button"
                                            title="Lihat Detail Aktivitas"
                                            class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <!-- HANYA BISA MEMILIH JIKA STATUS MASIH MENUNGGU -->
                                        <div v-if="act.status_validasi === 'Menunggu'" class="flex items-center gap-1">
                                            <!-- Quick Approve (Check) -->
                                            <button @click="quickValidateSingle(act, 'Disetujui')"
                                                type="button"
                                                title="Setujui Aktivitas"
                                                class="p-1.5 rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>

                                            <!-- Quick Reject (Cross) -->
                                            <button @click="openRejectPrompt(act)"
                                                type="button"
                                                title="Tolak Aktivitas"
                                                class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950 transition cursor-pointer">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                        <span v-else-if="isApprovedActivity(act.status_validasi)" class="text-xs font-bold text-emerald-600">
                                            ✓ {{ act.status_validasi }}
                                        </span>
                                        <span v-else-if="act.status_validasi === 'Ditolak'" class="text-xs font-bold text-rose-600">
                                            ✕ Ditolak
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Adaptive Card Feed (Block on mobile, hidden on md+) -->
                <div class="block md:hidden p-3.5 space-y-3">
                    <!-- Mobile Select All Bar -->
                    <div v-if="activities?.data && activities.data.length > 0"
                        class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 flex items-center justify-between text-xs">
                        <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-700 dark:text-slate-300">
                            <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
                            <span>Pilih Semua Halaman Ini</span>
                        </label>
                        <span class="text-[11px] font-mono text-slate-500">{{ activities.data.length }} Aktivitas</span>
                    </div>

                    <!-- Empty State Mobile -->
                    <div v-if="!activities?.data || activities.data.length === 0" class="py-10 text-center text-slate-400">
                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mb-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Tidak ada aktivitas</div>
                        <p class="text-xs mt-1">Sesuaikan filter pencarian atau periode.</p>
                    </div>

                    <!-- Activity Card per Item -->
                    <div v-for="act in activities?.data || []" :key="'mob-' + act.id"
                        class="p-3.5 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xs space-y-2.5 transition active:bg-slate-50/50">
                        <!-- Top: Checkbox, Name, and Status Badge -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2.5 min-w-0">
                                <input type="checkbox" :value="act.id" v-model="selectedIds" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer mt-1" />
                                <div class="min-w-0">
                                    <div v-if="act.user" class="font-bold text-xs text-slate-900 dark:text-slate-100 truncate">
                                        {{ act.user.nama_lengkap }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono mt-0.5">
                                        {{ formatDateIndo(act.tanggal) }} • {{ act.jam_mulai }} - {{ act.jam_selesai }}
                                    </div>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <span v-if="act.status_validasi === 'Menunggu'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                Menunggu
                            </span>
                            <span v-else-if="isApprovedActivity(act.status_validasi)"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 flex-shrink-0">
                                ✓ {{ act.status_validasi }}
                            </span>
                            <span v-else-if="act.status_validasi === 'Ditolak'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800 flex-shrink-0">
                                ✕ Ditolak
                            </span>
                            <span v-else class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] text-slate-600">
                                {{ act.status_validasi || 'Status belum tersedia' }}
                            </span>
                        </div>

                        <!-- Middle: Description & Category Tag -->
                        <div class="text-xs text-slate-700 dark:text-slate-300">
                            <div class="font-semibold text-slate-900 dark:text-slate-100 line-clamp-1">
                                {{ act.uraian.split('.')[0] }}
                            </div>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1 line-clamp-2 leading-relaxed">
                                {{ act.uraian }}
                            </p>
                            <div class="mt-2 flex items-center gap-2 flex-wrap text-[10px]">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium">
                                    {{ act.kategori }}
                                </span>
                                <span class="text-slate-400 font-mono">
                                    Vol: {{ act.no || '1 Laporan' }}
                                </span>
                                <span v-if="act.nama_unit" class="text-slate-400 truncate max-w-[140px]">
                                    Unit: {{ act.nama_unit }}
                                </span>
                            </div>
                        </div>

                        <!-- Bottom Action Controls -->
                        <div class="pt-2.5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
                            <button @click="openDetail(act)"
                                type="button"
                                class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition flex items-center gap-1.5 active:scale-95">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span>Lihat Detail</span>
                            </button>

                            <!-- Quick Validate Buttons (Only when Menunggu) -->
                            <div v-if="act.status_validasi === 'Menunggu'" class="flex items-center gap-1.5">
                                <button @click="quickValidateSingle(act, 'Disetujui')"
                                    type="button"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-2xs flex items-center gap-1 active:scale-95">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Setujui</span>
                                </button>
                                <button @click="openRejectPrompt(act)"
                                    type="button"
                                    class="px-3 py-1.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-600 hover:bg-rose-100 transition border border-rose-200 flex items-center gap-1 active:scale-95">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span>Tolak</span>
                                </button>
                            </div>
                            <div v-else class="text-xs font-bold" :class="isApprovedActivity(act.status_validasi) ? 'text-emerald-600' : act.status_validasi === 'Ditolak' ? 'text-rose-600' : 'text-slate-500'">
                                {{ act.status_validasi || 'Status belum tersedia' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination matching screenshot -->
                <div v-if="activities?.links && activities.links.length > 3"
                    class="p-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                    <div>
                        Menampilkan <strong>{{ activities.from || 1 }} - {{ activities.to || activities.total }}</strong> dari <strong>{{ formatNumber(activities.total) }}</strong> aktivitas
                    </div>
                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in activities.links" :key="idx">
                            <Link v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                class="px-3 py-1.5 rounded-lg font-semibold transition"
                                :class="link.active
                                    ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900 shadow-sm'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'"
                                v-html="link.label" />
                            <span v-else class="px-2 py-1 text-slate-400" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <div v-else class="space-y-6 max-w-7xl mx-auto">
            <!-- QUICK ACTION SHORTCUTS FOR PJLP -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <Link :href="route('activity.index')"
                    class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-emerald-500 hover:shadow-md transition group select-none">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center group-hover:scale-110 transition mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </div>
                    <div class="font-bold text-xs sm:text-sm text-slate-900 dark:text-slate-100 group-hover:text-emerald-600">Catat Aktivitas</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Input uraian tugas harian</div>
                </Link>

                <Link :href="route('dinas-luar.index')"
                    class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-indigo-500 hover:shadow-md transition group select-none">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center group-hover:scale-110 transition mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                    <div class="font-bold text-xs sm:text-sm text-slate-900 dark:text-slate-100 group-hover:text-indigo-600">Ajukan Dinas Luar</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">DL Awal, Akhir, & Penuh</div>
                </Link>

                <Link :href="route('attendance.index')"
                    class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-amber-500 hover:shadow-md transition group select-none">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center group-hover:scale-110 transition mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div class="font-bold text-xs sm:text-sm text-slate-900 dark:text-slate-100 group-hover:text-amber-600">Log Presensi</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Riwayat kehadiran bulanan</div>
                </Link>

                <Link :href="route('attendance.recap')"
                    class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 hover:border-purple-500 hover:shadow-md transition group select-none">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center group-hover:scale-110 transition mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    </div>
                    <div class="font-bold text-xs sm:text-sm text-slate-900 dark:text-slate-100 group-hover:text-purple-600">Rekap Bulanan</div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Capaian kinerja & honor</div>
                </Link>
            </div>

            <!-- 4. PJLP 4 STATS METRIC GRID -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 dark:text-slate-400 text-xs font-bold uppercase tracking-wider">Kehadiran (WFO)</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-black text-slate-900 dark:text-slate-100">{{ attendanceStats?.total_hadir || 0 }}</span>
                        <span class="text-xs text-slate-400 font-medium">Hari</span>
                    </div>
                    <div class="mt-2 text-[11px] text-emerald-600 dark:text-emerald-400 font-medium">Bulan berjalan</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-indigo-600 dark:text-indigo-400 text-xs font-bold uppercase tracking-wider">Dinas Luar (DL)</span>
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ attendanceStats?.total_dl || 0 }}</span>
                        <span class="text-xs text-slate-400 font-medium">Hari</span>
                    </div>
                    <div class="mt-2 text-[11px] text-indigo-500 font-medium">Penugasan lapangan</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-emerald-600 dark:text-emerald-400 text-xs font-bold uppercase tracking-wider">Tervalidasi</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ activityStats?.disetujui || 0 }}</span>
                        <span class="text-xs text-slate-400 font-medium">/ {{ activityStats?.total || 0 }} entri</span>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-500 font-medium">Disetujui atasan</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-amber-600 dark:text-amber-400 text-xs font-bold uppercase tracking-wider">Menunggu</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-black text-amber-500">{{ activityStats?.menunggu || 0 }}</span>
                        <span class="text-xs text-slate-400 font-medium">Entri</span>
                    </div>
                    <div class="mt-2 text-[11px] text-amber-600 dark:text-amber-400 font-medium">Dalam proses verifikasi</div>
                </div>
            </div>

            <!-- 5. DIAGRAM BATANG: STATUS VALIDASI LAPORAN (REALTIME & DENGAN FILTER TAHUN & BULAN) -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 sm:p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm relative overflow-hidden">
                <!-- Loading overlay during filter refresh -->
                <div v-if="isRefreshing" class="absolute inset-0 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xs flex items-center justify-center rounded-2xl z-20 transition">
                    <div class="flex items-center gap-2.5 px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold shadow-xl border border-slate-700">
                        <svg class="w-4 h-4 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        <span>Memperbarui data realtime...</span>
                    </div>
                </div>

                <!-- Header: Title, Realtime Status, & Filter Controls -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                Diagram Batang
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Realtime Sinkron
                            </span>
                        </div>
                        <h4 class="text-base sm:text-lg font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            Status Validasi Laporan
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Rasio & perbandingan status verifikasi: <strong class="text-emerald-600 dark:text-emerald-400 font-semibold">Setuju</strong>, <strong class="text-rose-600 dark:text-rose-400 font-semibold">Ditolak</strong>, dan <strong class="text-amber-500 dark:text-amber-400 font-semibold">Menunggu</strong> (Periode: {{ currentMonthLabel || 'Semua Bulan' }})
                        </p>
                    </div>

                    <!-- Filter Controls (Tahun & Bulan) -->
                    <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                        <!-- Filter Bulan -->
                        <div class="flex items-center gap-1 bg-slate-50 dark:bg-slate-800/80 p-1 rounded-xl border border-slate-200 dark:border-slate-700">
                            <span class="text-[11px] font-bold text-slate-400 px-2">Bulan:</span>
                            <select
                                v-model="selectedMonth"
                                @change="applyFilters"
                                class="text-xs font-bold rounded-lg border-0 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 py-1.5 px-2.5 shadow-2xs focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                            >
                                <option value="all">Semua Bulan (1 Tahun)</option>
                                <option value="1">Januari</option>
                                <option value="2">Februari</option>
                                <option value="3">Maret</option>
                                <option value="4">April</option>
                                <option value="5">Mei</option>
                                <option value="6">Juni</option>
                                <option value="7">Juli</option>
                                <option value="8">Agustus</option>
                                <option value="9">September</option>
                                <option value="10">Oktober</option>
                                <option value="11">November</option>
                                <option value="12">Desember</option>
                            </select>
                        </div>

                        <!-- Filter Tahun -->
                        <div class="flex items-center gap-1 bg-slate-50 dark:bg-slate-800/80 p-1 rounded-xl border border-slate-200 dark:border-slate-700">
                            <span class="text-[11px] font-bold text-slate-400 px-2">Tahun:</span>
                            <select
                                v-model="selectedYear"
                                @change="applyFilters"
                                class="text-xs font-bold rounded-lg border-0 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 py-1.5 px-2.5 shadow-2xs focus:ring-2 focus:ring-emerald-500 cursor-pointer"
                            >
                                <option v-for="yr in (availableYears && availableYears.length ? availableYears : [2024, 2025, 2026, 2027])" :key="yr" :value="yr">
                                    {{ yr }}
                                </option>
                            </select>
                        </div>

                        <!-- Total Entri Counter -->
                        <div class="px-3 py-2 rounded-xl bg-slate-900 text-white dark:bg-slate-800 font-mono text-xs font-bold whitespace-nowrap shadow-xs">
                            <span class="text-slate-400 font-sans font-normal text-[11px] mr-1">Total:</span>
                            <span>{{ formatNumber(activityStats?.total || 0) }}</span>
                        </div>
                    </div>
                </div>

                <!-- DIAGRAM BATANG (BAR CHART) 1 DIAGRAM DENGAN 3 WARNA -->
                <p v-if="!activityStats?.total" class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-500">Belum ada aktivitas pada periode yang dipilih.</p>
                <p v-if="activityStats?.lainnya" class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                    {{ formatNumber(activityStats.lainnya) }} aktivitas memiliki status lain atau kosong dan tetap termasuk total. Tiga batang di bawah hanya menampilkan Setuju, Menunggu, dan Ditolak.
                </p>
                <p class="mt-3 text-[11px] text-slate-500">Setuju mencakup status Disetujui dan Diterima pada data lama.</p>
                <div class="mt-4">
                    <!-- Chart Graphic Stage Container -->
                    <div class="relative w-full max-w-2xl mx-auto pt-16 pb-2 px-4 sm:px-10">
                        <!-- Chart Grid Track Area (Fixed height for exact 0-100% scaling) -->
                        <div class="relative w-full h-60 sm:h-64 border-b-2 border-slate-300 dark:border-slate-700">
                            <!-- Horizontal Grid Lines -->
                            <div class="absolute inset-x-0 top-0 border-b border-dashed border-slate-200 dark:border-slate-800 pointer-events-none">
                                <span class="absolute -top-3.5 left-0 text-[10px] font-mono font-medium text-slate-400 dark:text-slate-500">100% Max</span>
                            </div>
                            <div class="absolute inset-x-0 top-1/2 border-b border-dashed border-slate-200 dark:border-slate-800 pointer-events-none">
                                <span class="absolute -top-3.5 left-0 text-[10px] font-mono font-medium text-slate-400 dark:text-slate-500">50%</span>
                            </div>
                            <div class="absolute left-0 bottom-0 pointer-events-none">
                                <span class="absolute -top-3.5 left-0 text-[10px] font-mono font-medium text-slate-400 dark:text-slate-500">0</span>
                            </div>

                            <!-- 3 Batang (Bars): Setuju, Menunggu, Ditolak -->
                            <div class="absolute inset-0 flex items-end justify-around gap-4 sm:gap-10 z-10 px-6 sm:px-12">
                                <!-- 1. Batang Setuju (Hijau) -->
                                <div class="flex-1 flex flex-col items-center justify-end h-full relative group">
                                    <!-- Bar Column Container with dynamic percentage height -->
                                    <div
                                        class="w-full max-w-[72px] sm:max-w-[108px] relative transition-all duration-700 ease-out flex flex-col justify-end"
                                        :style="{
                                            height: persenDisetujui > 0
                                                ? `${persenDisetujui}%`
                                                : (activityStats?.disetujui > 0 ? '3%' : '3px')
                                        }"
                                    >
                                        <!-- Floating Callout Badge Above Bar -->
                                        <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 flex flex-col items-center pointer-events-none transition-transform duration-300 group-hover:-translate-y-1 w-max">
                                            <div class="flex items-baseline gap-1">
                                                <span class="text-base sm:text-xl font-black text-emerald-600 dark:text-emerald-400 font-mono">
                                                    {{ formatNumber(activityStats?.disetujui || 0) }}
                                                </span>
                                                <span class="text-[10px] text-slate-400 font-medium">entri</span>
                                            </div>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 shadow-2xs">
                                                {{ persenDisetujui }}%
                                            </span>
                                        </div>

                                        <!-- The Vertical Bar (Hijau - Emerald) -->
                                        <div
                                            class="w-full h-full rounded-t-2xl bg-gradient-to-t from-emerald-600 via-emerald-500 to-emerald-400 shadow-md shadow-emerald-500/25 transition-all duration-300 group-hover:brightness-110 relative overflow-hidden"
                                            :class="persenDisetujui === 0 && !activityStats?.disetujui ? 'opacity-35' : ''"
                                        >
                                            <!-- Glossy top highlight cap -->
                                            <div class="w-full h-2 bg-white/40 rounded-t-2xl"></div>
                                            <div class="absolute inset-0 bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. Batang Menunggu (Kuning / Amber) -->
                                <div class="flex-1 flex flex-col items-center justify-end h-full relative group">
                                    <!-- Bar Column Container with dynamic percentage height -->
                                    <div
                                        class="w-full max-w-[72px] sm:max-w-[108px] relative transition-all duration-700 ease-out flex flex-col justify-end"
                                        :style="{
                                            height: persenMenunggu > 0
                                                ? `${persenMenunggu}%`
                                                : (activityStats?.menunggu > 0 ? '3%' : '3px')
                                        }"
                                    >
                                        <!-- Floating Callout Badge Above Bar -->
                                        <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 flex flex-col items-center pointer-events-none transition-transform duration-300 group-hover:-translate-y-1 w-max">
                                            <div class="flex items-baseline gap-1">
                                                <span class="text-base sm:text-xl font-black text-amber-500 dark:text-amber-400 font-mono">
                                                    {{ formatNumber(activityStats?.menunggu || 0) }}
                                                </span>
                                                <span class="text-[10px] text-slate-400 font-medium">entri</span>
                                            </div>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-800 shadow-2xs">
                                                {{ persenMenunggu }}%
                                            </span>
                                        </div>

                                        <!-- The Vertical Bar (Kuning - Amber) -->
                                        <div
                                            class="w-full h-full rounded-t-2xl bg-gradient-to-t from-amber-600 via-amber-500 to-amber-400 shadow-md shadow-amber-500/25 transition-all duration-300 group-hover:brightness-110 relative overflow-hidden"
                                            :class="persenMenunggu === 0 && !activityStats?.menunggu ? 'opacity-35' : ''"
                                        >
                                            <!-- Glossy top highlight cap -->
                                            <div class="w-full h-2 bg-white/40 rounded-t-2xl"></div>
                                            <div class="absolute inset-0 bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Batang Ditolak (Merah - Rose) -->
                                <div class="flex-1 flex flex-col items-center justify-end h-full relative group">
                                    <!-- Bar Column Container with dynamic percentage height -->
                                    <div
                                        class="w-full max-w-[72px] sm:max-w-[108px] relative transition-all duration-700 ease-out flex flex-col justify-end"
                                        :style="{
                                            height: persenDitolak > 0
                                                ? `${persenDitolak}%`
                                                : (activityStats?.ditolak > 0 ? '3%' : '3px')
                                        }"
                                    >
                                        <!-- Floating Callout Badge Above Bar -->
                                        <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 flex flex-col items-center pointer-events-none transition-transform duration-300 group-hover:-translate-y-1 w-max">
                                            <div class="flex items-baseline gap-1">
                                                <span class="text-base sm:text-xl font-black text-rose-600 dark:text-rose-400 font-mono">
                                                    {{ formatNumber(activityStats?.ditolak || 0) }}
                                                </span>
                                                <span class="text-[10px] text-slate-400 font-medium">entri</span>
                                            </div>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-300 dark:border-rose-800 shadow-2xs">
                                                {{ persenDitolak }}%
                                            </span>
                                        </div>

                                        <!-- The Vertical Bar (Merah - Rose) -->
                                        <div
                                            class="w-full h-full rounded-t-2xl bg-gradient-to-t from-rose-600 via-rose-500 to-rose-400 shadow-md shadow-rose-500/25 transition-all duration-300 group-hover:brightness-110 relative overflow-hidden"
                                            :class="persenDitolak === 0 && !activityStats?.ditolak ? 'opacity-35' : ''"
                                        >
                                            <!-- Glossy top highlight cap -->
                                            <div class="w-full h-2 bg-white/40 rounded-t-2xl"></div>
                                            <div class="absolute inset-0 bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- X-Axis Labels & Legend Indicators -->
                    <div class="w-full max-w-2xl mx-auto grid grid-cols-3 gap-2 sm:gap-6 pt-3 text-center">
                        <!-- Label 1: Setuju -->
                        <div class="flex flex-col items-center">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-bold">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                <span>Setuju</span>
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1">Tervalidasi Atasan</span>
                        </div>

                        <!-- Label 2: Menunggu -->
                        <div class="flex flex-col items-center">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 text-xs font-bold">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                <span>Menunggu</span>
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1">Dalam Proses Review</span>
                        </div>

                        <!-- Label 3: Ditolak -->
                        <div class="flex flex-col items-center">
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-xs font-bold">
                                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                                <span>Ditolak</span>
                            </div>
                            <span class="text-[10px] text-slate-400 mt-1">Perlu Revisi Uraian</span>
                        </div>
                    </div>
                </div>

                <!-- 12-Month Grouped Breakdown (Displayed when 'Semua Bulan' or as quick comparison) -->
                <div v-if="pjlpMonthlyStats && pjlpMonthlyStats.length > 0" class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                Rincian Tren 12 Bulan (T.A {{ selectedYear }})
                            </span>
                        </div>
                        <span class="text-[11px] text-slate-400">Klik salah satu bulan untuk memfilter data realtime</span>
                    </div>

                    <div class="grid grid-cols-3 sm:grid-cols-6 lg:grid-cols-12 gap-2">
                        <div
                            v-for="m in pjlpMonthlyStats"
                            :key="m.bulan"
                            @click="selectedMonth = String(m.bulan); applyFilters();"
                            class="p-2 rounded-xl border text-center transition cursor-pointer group select-none"
                            :class="selectedMonth === String(m.bulan)
                                ? 'bg-emerald-50/80 border-emerald-400 dark:bg-emerald-950/40 dark:border-emerald-700 ring-2 ring-emerald-500/20 shadow-xs'
                                : 'bg-slate-50/70 dark:bg-slate-800/40 border-slate-200/80 dark:border-slate-700 hover:border-emerald-300 dark:hover:border-emerald-700'"
                        >
                            <div class="text-[10px] font-bold text-slate-700 dark:text-slate-300 group-hover:text-emerald-600">
                                {{ m.label }}
                            </div>
                            <div class="text-[11px] font-black font-mono mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ m.total }}
                            </div>

                            <!-- 3 Mini Colored Bars per Month: Setuju (Hijau), Menunggu (Kuning), Ditolak (Merah) -->
                            <div class="mt-1.5 flex items-end justify-center gap-0.5 h-6">
                                <div
                                    class="w-1.5 rounded-t-xs bg-emerald-500"
                                    :style="{ height: `${Math.max(2, Math.min(100, (m.disetujui / maxPjlpMonthlyVolume) * 100))}%` }"
                                    :title="`Setuju: ${m.disetujui}`"
                                ></div>
                                <div
                                    class="w-1.5 rounded-t-xs bg-amber-500"
                                    :style="{ height: `${Math.max(2, Math.min(100, (m.menunggu / maxPjlpMonthlyVolume) * 100))}%` }"
                                    :title="`Menunggu: ${m.menunggu}`"
                                ></div>
                                <div
                                    class="w-1.5 rounded-t-xs bg-rose-500"
                                    :style="{ height: `${Math.max(2, Math.min(100, (m.ditolak / maxPjlpMonthlyVolume) * 100))}%` }"
                                    :title="`Ditolak: ${m.ditolak}`"
                                ></div>
                                <div v-if="m.lainnya" class="w-1.5 rounded-t-xs bg-slate-400"
                                    :style="{ height: `${(m.lainnya / maxPjlpMonthlyVolume) * 100}%` }"
                                    :title="`Status lainnya: ${m.lainnya}`"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. DUAL FEED SECTION: RECENT ACTIVITIES & RECENT DINAS LUAR -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Feed 1: Aktivitas Terbaru -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Aktivitas Terbaru Anda</h3>
                            </div>
                            <Link :href="route('activity.index')" class="text-xs font-bold text-emerald-600 hover:text-emerald-700">
                                Lihat Semua →
                            </Link>
                        </div>

                        <!-- Empty state -->
                        <div v-if="!recentActivities || recentActivities.length === 0" class="py-8 text-center text-slate-400 text-xs">
                            <p class="font-medium">Belum ada aktivitas tercatat bulan ini.</p>
                            <Link :href="route('activity.index')" class="inline-block mt-2 text-emerald-600 font-bold hover:underline">
                                + Tambah Aktivitas Pertama
                            </Link>
                        </div>

                        <!-- Activity List -->
                        <div v-else class="space-y-3">
                            <div v-for="act in recentActivities" :key="act.id"
                                class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-xs text-slate-900 dark:text-slate-100">{{ formatDateIndo(act.tanggal) }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">{{ act.jam_mulai }} - {{ act.jam_selesai }}</span>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-200/70 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                            {{ act.kategori }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 line-clamp-2 leading-relaxed">
                                        {{ act.uraian }}
                                    </p>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold flex-shrink-0"
                                    :class="{
                                        'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800': isApprovedActivity(act.status_validasi),
                                        'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800': act.status_validasi === 'Menunggu',
                                        'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800': act.status_validasi === 'Ditolak',
                                    }">
                                    {{ act.status_validasi }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feed 2: Riwayat Dinas Luar Terbaru -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Riwayat Dinas Luar (DL)</h3>
                            </div>
                            <Link :href="route('dinas-luar.index')" class="text-xs font-bold text-indigo-600 hover:text-indigo-700">
                                Kelola DL →
                            </Link>
                        </div>

                        <!-- Empty state -->
                        <div v-if="!recentDinasLuar || recentDinasLuar.length === 0" class="py-8 text-center text-slate-400 text-xs">
                            <p class="font-medium">Belum ada riwayat pengajuan Dinas Luar.</p>
                            <Link :href="route('dinas-luar.index')" class="inline-block mt-2 text-indigo-600 font-bold hover:underline">
                                + Ajukan Dinas Luar
                            </Link>
                        </div>

                        <!-- Dinas Luar List -->
                        <div v-else class="space-y-3">
                            <div v-for="dl in recentDinasLuar" :key="dl.id"
                                class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800 flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3 min-w-0 flex-1">
                                    <!-- Thumbnail Swafoto Dinas Luar (Dapat Diklik) -->
                                    <button
                                        v-if="dl.foto"
                                        type="button"
                                        @click="previewPhotoUrl = formatPhotoUrl(dl.foto)"
                                        class="w-12 h-12 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 shadow-xs hover:ring-2 hover:ring-indigo-500 hover:opacity-90 transition block shrink-0 cursor-pointer group relative"
                                        title="Klik untuk melihat foto swafoto ukuran penuh"
                                    >
                                        <img :src="formatPhotoUrl(dl.foto)" alt="Foto DL" class="w-full h-full object-cover group-hover:scale-110 transition duration-200" />
                                        <div class="absolute inset-0 bg-black/25 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200">
                                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </div>
                                    </button>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-xs text-slate-900 dark:text-slate-100">{{ formatDateIndo(dl.waktu) }}</span>
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300">
                                                {{ dl.tipe || 'Dinas Luar' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 line-clamp-1">
                                            {{ dl.alamat || dl.lokasi || 'Lokasi Penugasan Lapangan' }}
                                        </p>
                                        <div v-if="dl.keterangan" class="text-[11px] text-slate-400 mt-0.5 line-clamp-1">
                                            {{ dl.keterangan }}
                                        </div>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold flex-shrink-0"
                                    :class="{
                                        'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200': dl.status_verifikasi === 'Disetujui',
                                        'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200': dl.status_verifikasi === 'Menunggu',
                                        'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200': dl.status_verifikasi === 'Ditolak',
                                    }">
                                    {{ dl.status_verifikasi || 'Menunggu' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- MODAL: DETAIL & VERIFIKASI AKTIVITAS -->
        <!-- ============================================================== -->
        <div v-if="showDetailModal && selectedActivity"
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-2xl w-full border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/40">
                    <div>
                        <h4 class="font-bold text-base text-slate-900 dark:text-slate-100">Detail Aktivitas Pegawai</h4>
                        <p class="text-xs text-slate-500">Nomor: {{ selectedActivity.no || 'Entri Harian' }} • {{ formatDateIndo(selectedActivity.tanggal) }}</p>
                    </div>
                    <button @click="showDetailModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4 text-xs">
                    <!-- Staff Info Card -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-700/80 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white font-extrabold flex items-center justify-center text-sm shadow-sm">
                                {{ selectedActivity.user?.nama_lengkap ? selectedActivity.user.nama_lengkap.slice(0, 2).toUpperCase() : 'PJ' }}
                            </div>
                            <div>
                                <div class="font-bold text-sm text-slate-900 dark:text-slate-100">{{ selectedActivity.user?.nama_lengkap }}</div>
                                <div class="text-slate-500">{{ selectedActivity.user?.posisi_pekerjaan || 'PJLP Kominfotik' }} • {{ selectedActivity.user?.seksi }}</div>
                            </div>
                        </div>
                        <span v-if="isApprovedActivity(selectedActivity.status_validasi)" class="px-2.5 py-1 rounded-lg font-bold text-[11px] bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 ring-1 ring-emerald-300/40">
                            ✓ {{ selectedActivity.status_validasi }}
                        </span>
                        <span v-else-if="selectedActivity.status_validasi === 'Menunggu'" class="px-2.5 py-1 rounded-lg font-bold text-[11px] bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 ring-1 ring-amber-300/40">
                            ⏳ Menunggu Review
                        </span>
                        <span v-else-if="selectedActivity.status_validasi === 'Ditolak'" class="px-2.5 py-1 rounded-lg font-bold text-[11px] bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300 ring-1 ring-rose-300/40">
                            ✕ Ditolak
                        </span>
                        <span v-else class="px-2.5 py-1 rounded-lg font-bold text-[11px] bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            {{ selectedActivity.status_validasi || 'Status -' }}
                        </span>
                    </div>

                    <!-- Meta Grid -->
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                            <div class="text-slate-400 text-[10px] uppercase font-bold">Waktu Pelaksanaan</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ selectedActivity.jam_mulai }} - {{ selectedActivity.jam_selesai }} WIB</div>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                            <div class="text-slate-400 text-[10px] uppercase font-bold">Kategori Pelayanan</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ selectedActivity.kategori }}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                            <div class="text-slate-400 text-[10px] uppercase font-bold">Lokasi / Unit Kerja</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ selectedActivity.nama_unit || selectedActivity.lokasi }}</div>
                        </div>
                        <div class="p-3 rounded-lg bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                            <div class="text-slate-400 text-[10px] uppercase font-bold">Status Saat Ini</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ selectedActivity.status_validasi }}</div>
                        </div>
                    </div>

                    <!-- Task Description -->
                    <div>
                        <label class="text-slate-500 font-bold block mb-1">Uraian Tugas & Hasil Capaian:</label>
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700/80 text-slate-800 dark:text-slate-200 whitespace-pre-line leading-relaxed">
                            {{ selectedActivity.uraian }}
                        </div>
                    </div>

                    <!-- Rejection Reason if any -->
                    <div v-if="selectedActivity.catatan" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-800 dark:text-rose-200">
                        <strong>Catatan Verifikator:</strong> {{ selectedActivity.catatan }}
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="px-6 py-4 bg-slate-50/50 dark:bg-slate-800/40 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <button @click="showDetailModal = false" type="button" class="px-4 py-2 rounded-xl text-slate-600 dark:text-slate-400 font-bold hover:bg-slate-200 dark:hover:bg-slate-800 text-xs">
                        Tutup
                    </button>
                    <div class="flex items-center gap-2">
                        <template v-if="selectedActivity.status_validasi === 'Menunggu'">
                            <button @click="openRejectPrompt(selectedActivity)" type="button" class="px-4 py-2 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-500 text-xs transition shadow-sm cursor-pointer">
                                ✕ Tolak
                            </button>
                            <button @click="quickValidateSingle(selectedActivity, 'Disetujui')" type="button" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-500 text-xs transition shadow-sm cursor-pointer">
                                ✓ Setujui & Validasi
                            </button>
                        </template>
                        <span v-else-if="isApprovedActivity(selectedActivity.status_validasi)" class="text-xs font-bold text-emerald-600 flex items-center gap-1">
                            ✓ {{ selectedActivity.status_validasi }} (Terkunci)
                        </span>
                        <span v-else-if="selectedActivity.status_validasi === 'Ditolak'" class="text-xs font-bold text-rose-600 flex items-center gap-1">
                            ✕ Telah Ditolak (Menunggu Revisi PJLP)
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- MODAL: REJECT REASON PROMPT -->
        <!-- ============================================================== -->
        <div v-if="showRejectModal && selectedActivity"
            class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full border border-slate-200 dark:border-slate-800 shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-base text-rose-600 flex items-center gap-2">
                        <span>Alasan Penolakan Aktivitas</span>
                    </h4>
                    <button @click="showRejectModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <p class="text-xs text-slate-500">
                    Berikan alasan yang jelas agar PJLP dapat melakukan perbaikan entri laporan pekerjaannya.
                </p>

                <!-- Quick reason presets -->
                <div class="space-y-1.5 text-xs">
                    <label class="text-[11px] font-bold text-slate-400 uppercase">Pilih Template Alasan:</label>
                    <div class="space-y-1">
                        <button v-for="preset in [
                            'Dokumentasi checklist / lampiran tidak lengkap',
                            'Uraian tugas tidak memenuhi standar KAK',
                            'Jam pelaksanaan tumpang tindih dengan agenda lain',
                            'Volume output tidak sesuai dengan realisasi',
                        ]" :key="preset"
                            @click="rejectReasonPreset = preset; rejectionNote = preset"
                            type="button"
                            class="block w-full text-left px-3 py-1.5 rounded-lg border text-xs transition"
                            :class="rejectionNote === preset ? 'border-rose-500 bg-rose-50 text-rose-700 font-bold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
                            {{ preset }}
                        </button>
                    </div>
                </div>

                <!-- Custom Note -->
                <div>
                    <label class="text-[11px] font-bold text-slate-400 uppercase block mb-1">Catatan Tambahan:</label>
                    <textarea v-model="rejectionNote" rows="3" placeholder="Tuliskan catatan detail penolakan..." class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 focus:ring-rose-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button @click="showRejectModal = false" type="button" class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-500 hover:bg-slate-100">
                        Batal
                    </button>
                    <button @click="submitRejectWithReason" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 text-white hover:bg-rose-500 shadow-sm">
                        Konfirmasi Tolak
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- MODAL PREVIEW FOTO FULLSCREEN (DAPAT DIKLIK)                  -->
        <!-- ============================================================== -->
        <div
            v-if="previewPhotoUrl"
            class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 bg-black/90 backdrop-blur-md cursor-pointer select-none"
            @click="previewPhotoUrl = null"
        >
            <div class="relative max-w-4xl max-h-[94vh] flex flex-col items-center justify-center" @click.stop>
                <img
                    :src="previewPhotoUrl"
                    alt="Foto Swafoto Lapangan"
                    class="max-w-full max-h-[82vh] object-contain shadow-2xl block rounded-xl border border-white/15"
                />
                <div class="mt-3 flex items-center gap-2">
                    <a
                        :href="previewPhotoUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="px-4 py-2 rounded-xl bg-white/20 hover:bg-white/30 text-white text-xs font-bold backdrop-blur-xs transition flex items-center gap-1.5 cursor-pointer shadow-md"
                        title="Buka atau unduh file resolusi penuh di tab baru"
                    >
                        <span>Buka / Unduh Resolusi Asli</span>
                    </a>
                    <button
                        type="button"
                        @click="previewPhotoUrl = null"
                        class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-md transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <span>Tutup Pratinjau</span>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
