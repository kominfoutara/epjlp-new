<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    pjlpUsers: Array,
    filters: Object,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isSuperadmin = computed(() => currentUser.value.role === 'Superadmin');

const filterMonth = ref(props.filters.bulan || new Date().toISOString().slice(0, 7));
const search = ref(props.filters.search || '');
const currentStatus = computed(() => props.filters?.status || '');

// Local reactive list of users with activities
const usersList = ref([...(props.pjlpUsers || [])]);

// Realtime Auto-Refresh & Polling
const isRefreshing = ref(false);
let pollInterval = null;

const refreshData = (showSpinner = true) => {
    if (showSpinner) isRefreshing.value = true;
    router.reload({
        only: ['pjlpUsers'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isRefreshing.value = false;
        }
    });
};

watch(() => props.pjlpUsers, (newVal) => {
    usersList.value = newVal ? [...newVal] : [];
    // Jika modal sedang terbuka, sinkronkan activeUser agar update realtime
    if (activeUser.value) {
        const updated = usersList.value.find(u => u.id === activeUser.value.id);
        if (updated) {
            activeUser.value = updated;
        }
    }
}, { deep: true });

onMounted(() => {
    // Polling setiap 5 detik agar data aktivitas baru dari PJLP langsung muncul realtime di Kasi
    pollInterval = setInterval(() => {
        if (document.visibilityState === 'visible') {
            refreshData(false);
        }
    }, 5000);

    window.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('keydown', handleKeydown);
});

const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        refreshData(false);
    }
};

const handleKeydown = (e) => {
    if (e.key === 'Escape') {
        if (showPhotoViewer.value) {
            closePhotoViewer();
        } else if (showValidationModal.value) {
            closeValidationModal();
        }
    }
};

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
    window.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('keydown', handleKeydown);
});

const applyFilter = (statusOverride) => {
    const statusVal = statusOverride !== undefined ? statusOverride : currentStatus.value;
    router.get(route('activity.index'), {
        bulan: filterMonth.value,
        search: search.value || undefined,
        status: statusVal || undefined,
    }, { preserveState: true, replace: true });
};

// Fitur Export Rekap Aktivitas Excel & PDF untuk Kasi / Admin
const getExportQueryString = () => {
    const params = new URLSearchParams();
    if (filterMonth.value) params.append('bulan', filterMonth.value);
    if (search.value) params.append('search', search.value);
    if (currentStatus.value) params.append('status', currentStatus.value);
    return params.toString();
};

const exportExcel = () => {
    const qs = getExportQueryString();
    const url = route('activity.export.excel') + (qs ? `?${qs}` : '');
    window.open(url, '_blank');
};

const exportPdf = () => {
    const qs = getExportQueryString();
    const url = route('activity.export.pdf') + (qs ? `?${qs}` : '');
    window.open(url, '_blank');
};

// Summary metrics across all PJLP
const statsSummary = computed(() => {
    const list = usersList.value || [];
    let totalActivities = 0, totalMenunggu = 0, totalDisetujui = 0, totalDitolak = 0;

    list.forEach(u => {
        totalActivities += Number(u.total_aktivitas || 0);
        totalMenunggu += Number(u.menunggu || 0);
        totalDisetujui += Number(u.disetujui || 0);
        totalDitolak += Number(u.ditolak || 0);
    });

    return {
        totalUsers: list.length,
        totalActivities,
        totalMenunggu,
        totalDisetujui,
        totalDitolak,
    };
});

// Status helper function for table rows
const getUserStatus = (user) => {
    if (Number(user.total_aktivitas || 0) === 0) {
        return { label: 'Belum Ada Laporan', type: 'empty' };
    }
    if (Number(user.menunggu || 0) > 0) {
        return { label: `Menunggu (${user.menunggu})`, type: 'menunggu' };
    }
    if (Number(user.ditolak || 0) > 0 && Number(user.disetujui || 0) === 0) {
        return { label: 'Ditolak', type: 'ditolak' };
    }
    if (Number(user.disetujui || 0) > 0 && Number(user.ditolak || 0) === 0) {
        return { label: 'Disetujui', type: 'disetujui' };
    }
    return { label: `Sebagian (${user.disetujui}/${user.total_aktivitas})`, type: 'sebagian' };
};

// ============================================================
// POP-UP MODAL: PERIKSA & VALIDASI AKTIVITAS
// ============================================================
const showValidationModal = ref(false);
const activeUser = ref(null);
const validatingActivityId = ref(null);
const activityNotes = ref({});

const openValidationModal = (user) => {
    activeUser.value = user;
    showValidationModal.value = true;
};

const closeValidationModal = () => {
    showValidationModal.value = false;
    activeUser.value = null;
};

// Recalculate local user counters
const updateUserCounters = (u) => {
    if (!u || !u.activities) return;
    u.menunggu = u.activities.filter(a => a.status_validasi === 'Menunggu').length;
    u.disetujui = u.activities.filter(a => a.status_validasi === 'Disetujui').length;
    u.ditolak = u.activities.filter(a => a.status_validasi === 'Ditolak').length;
    u.total_aktivitas = u.activities.length;
};

// Validasi satuan aktivitas
const validateSingleActivity = (act, status) => {
    if (validatingActivityId.value) return;
    if (act.status_validasi !== 'Menunggu') return; // Hanya bisa divalidasi jika status masih Menunggu
    validatingActivityId.value = act.id;
    const oldStatus = act.status_validasi;
    act.status_validasi = status; // optimistic

    if (activeUser.value) {
        updateUserCounters(activeUser.value);
    }

    router.post(route('activity.quick-validate', act.id), {
        status: status,
        catatan: activityNotes.value[act.id] || null,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['pjlpUsers'],
        onSuccess: () => {
            triggerToast(`Aktivitas berhasil ${status === 'Disetujui' ? 'disetujui' : 'ditolak'}.`, 'success');
        },
        onError: () => {
            act.status_validasi = oldStatus;
            if (activeUser.value) {
                updateUserCounters(activeUser.value);
            }
            triggerToast('Gagal memproses validasi aktivitas.', 'error');
        },
        onFinish: () => {
            validatingActivityId.value = null;
        }
    });
};

// Setujui semua aktivitas yang sedang menunggu pada user aktif
const approveAllPending = (user) => {
    if (!user || !user.activities) return;
    const pending = user.activities.filter(a => a.status_validasi === 'Menunggu');
    if (pending.length === 0) return;

    const ids = pending.map(a => a.id);
    pending.forEach(a => { a.status_validasi = 'Disetujui'; });
    updateUserCounters(user);

    router.post(route('activity.validate-checkbox'), {
        activity_ids: ids,
        status: 'Disetujui',
        catatan: 'Disetujui massal melalui Lembar Validasi Aktivitas',
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['pjlpUsers'],
        onSuccess: () => {
            triggerToast(`${ids.length} aktivitas berhasil divalidasi (Disetujui).`, 'success');
        },
        onError: () => {
            triggerToast('Gagal memvalidasi aktivitas massal.', 'error');
        }
    });
};

// ============================================================
// TOAST NOTIFIKASI
// ============================================================
const toast = ref({ show: false, message: '', type: 'success' });
let toastTimer = null;
const triggerToast = (msg, type = 'success') => {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { show: true, message: msg, type };
    toastTimer = setTimeout(() => {
        toast.value.show = false;
    }, 3500);
};

// ============================================================
// LIGHTBOX / PHOTO VIEWER MODAL
// ============================================================
const showPhotoViewer = ref(false);
const activePhotoViewer = ref({ url: '', title: '', index: 0, list: [] });

const openPhotoViewer = (url, title = 'Foto Lampiran', list = [], index = 0) => {
    activePhotoViewer.value = {
        url,
        title,
        index,
        list: list.length > 0 ? list : [url],
    };
    showPhotoViewer.value = true;
};

const closePhotoViewer = () => {
    showPhotoViewer.value = false;
};

const nextPhotoViewer = () => {
    if (!activePhotoViewer.value.list || activePhotoViewer.value.list.length <= 1) return;
    const nextIdx = (activePhotoViewer.value.index + 1) % activePhotoViewer.value.list.length;
    const item = activePhotoViewer.value.list[nextIdx];
    activePhotoViewer.value.index = nextIdx;
    activePhotoViewer.value.url = typeof item === 'string' ? item : item.url;
    activePhotoViewer.value.title = item.name || `Foto ${nextIdx + 1}`;
};

const prevPhotoViewer = () => {
    if (!activePhotoViewer.value.list || activePhotoViewer.value.list.length <= 1) return;
    const prevIdx = (activePhotoViewer.value.index - 1 + activePhotoViewer.value.list.length) % activePhotoViewer.value.list.length;
    const item = activePhotoViewer.value.list[prevIdx];
    activePhotoViewer.value.index = prevIdx;
    activePhotoViewer.value.url = typeof item === 'string' ? item : item.url;
    activePhotoViewer.value.title = item.name || `Foto ${prevIdx + 1}`;
};

const getFileList = (lampiranStr) => {
    if (!lampiranStr) return [];
    return lampiranStr.split(',').map(f => f.trim()).filter(Boolean);
};

const getFileType = (filename) => {
    if (!filename) return 'other';
    const clean = filename.trim().split('?')[0];
    const ext = clean.split('.').pop()?.toLowerCase();
    if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'jfif', 'avif', 'heic', 'heif'].includes(ext)) return 'image';
    if (ext === 'pdf') return 'pdf';
    if (['xlsx', 'xls', 'csv'].includes(ext)) return 'excel';
    return 'other';
};

const getPhotoUrlsFromLampiran = (lampiranStr) => {
    if (!lampiranStr) return [];
    return lampiranStr.split(',')
        .map(f => f.trim())
        .filter(f => f && getFileType(f) === 'image')
        .map(f => ({
            url: '/storage/lampiran_aktivitas/' + f,
            name: f,
        }));
};

const getPhotoIndexInLampiran = (lampiranStr, targetFilename) => {
    const list = getPhotoUrlsFromLampiran(lampiranStr);
    const idx = list.findIndex(item => item.name === targetFilename);
    return idx >= 0 ? idx : 0;
};

const formatDateIndo = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
};

const formatTimeShort = (timeStr) => {
    if (!timeStr) return '';
    return timeStr.slice(0, 5);
};
</script>

<template>
    <Head :title="isSuperadmin ? 'Rekap Laporan Aktivitas Pegawai' : 'Validasi Aktivitas Pegawai'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-300/40">
                            {{ isSuperadmin ? 'Monitoring & Rekap Kinerja' : 'Validasi & Evaluasi Kinerja' }}
                        </span>
                        <span class="text-xs text-slate-400">• Periode {{ filterMonth }}</span>
                        <!-- Realtime Status Pill -->
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>Realtime Aktif</span>
                        </span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                        {{ isSuperadmin ? 'Rekap Laporan Aktivitas Pegawai' : 'Validasi Aktivitas Pegawai' }}
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ isSuperadmin ? 'Pemantauan rekapitulasi volume dan status laporan kinerja bulanan seluruh pegawai terdaftar.' : 'Daftar pegawai dalam lingkup kerja untuk pemeriksaan capaian tugas dan persetujuan laporan bulanan.' }}
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- 1. SUMMARY STATS CARDS -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Pegawai</div>
                    <div class="text-3xl font-black text-slate-900 dark:text-slate-100 mt-2">{{ statsSummary.totalUsers }}</div>
                    <div class="text-xs text-slate-500 mt-1">Pegawai aktif</div>
                </div>

                <div @click="applyFilter(currentStatus === 'Menunggu' ? '' : 'Menunggu')"
                    class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-amber-200/60 dark:border-amber-800/40 shadow-sm cursor-pointer hover:border-amber-400 transition"
                    :class="{ 'ring-2 ring-amber-500/30 bg-amber-50/20': currentStatus === 'Menunggu' }">
                    <div class="flex items-center justify-between">
                        <div class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Menunggu Validasi</div>
                        <span v-if="statsSummary.totalMenunggu > 0" class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    </div>
                    <div class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-2">{{ statsSummary.totalMenunggu }}</div>
                    <div class="text-xs text-amber-600/80 mt-1 font-medium">Perlu review segera</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-emerald-200/60 dark:border-emerald-800/40 shadow-sm">
                    <div class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Aktivitas Disetujui</div>
                    <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-2">{{ statsSummary.totalDisetujui }}</div>
                    <div class="text-xs text-emerald-600/70 mt-1">Tervalidasi atasan</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-blue-200/60 dark:border-blue-800/40 shadow-sm">
                    <div class="text-[11px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Total Entri Tugas</div>
                    <div class="text-3xl font-black text-blue-600 dark:text-blue-400 mt-2">{{ statsSummary.totalActivities }}</div>
                    <div class="text-xs text-slate-500 mt-1">Volume laporan bulan ini</div>
                </div>
            </div>

            <!-- 2. FILTER CONTROLS BAR -->
            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Periode Bulan</label>
                        <input type="month" v-model="filterMonth" @change="applyFilter()"
                            class="rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 focus:ring-emerald-500 shadow-sm px-3.5 py-2 cursor-pointer" />
                    </div>

                    <div class="flex-1 max-w-md">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Pencarian Nama</label>
                        <div class="relative">
                            <input type="text" v-model="search" @keyup.enter="applyFilter()" placeholder="Ketik nama pegawai..."
                                class="w-full pl-9 pr-8 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs text-slate-800 dark:text-slate-200 focus:ring-emerald-500 shadow-sm py-2" />
                            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <button v-if="search" @click="search = ''; applyFilter()" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs">✕</button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mt-6">
                        <button @click="applyFilter()" type="button" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
                            Cari
                        </button>
                        <!-- Instant Refresh Button -->
                        <button @click="refreshData(true)" type="button" title="Segarkan Data Realtime"
                            class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 hover:text-emerald-600 transition shadow-xs">
                            <svg class="w-4 h-4" :class="{ 'animate-spin text-emerald-600': isRefreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Export Rekap Buttons (Excel & PDF) -->
                <div class="flex flex-wrap items-center gap-2.5 self-start sm:self-end">
                    <!-- Tombol Export Excel -->
                    <button @click="exportExcel" type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white shadow-xs shadow-emerald-600/20 transition cursor-pointer"
                        title="Unduh Rekap Aktivitas Pegawai dalam Format Excel (.xlsx)">
                        <svg class="w-4 h-4 text-emerald-100" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6zm10.7-5.5l-1.8 2.7 1.9 2.8h-1.5l-1.2-1.8-1.2 1.8h-1.5l1.9-2.8-1.8-2.7h1.5l1.1 1.7 1.1-1.7h1.6z"/>
                        </svg>
                        <span>Export Excel</span>
                    </button>

                    <!-- Tombol Export PDF -->
                    <button @click="exportPdf" type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white shadow-xs shadow-rose-600/20 transition cursor-pointer"
                        title="Unduh Rekap Aktivitas Pegawai dalam Format PDF (.pdf)">
                        <svg class="w-4 h-4 text-rose-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-6 4h4"/>
                        </svg>
                        <span>Export PDF</span>
                    </button>
                </div>
            </div>

            <!-- Active Reminder Filter Banner -->
            <div v-if="currentStatus === 'Menunggu'" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/90 dark:border-amber-800/80 text-xs shadow-xs">
                <div class="flex items-center gap-2.5 text-amber-900 dark:text-amber-200 font-medium">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>Filter Reminder Aktif: Menampilkan hanya pegawai yang memiliki aktivitas <strong>Menunggu Evaluasi</strong> ({{ usersList.length }} pegawai).</span>
                </div>
                <button type="button" @click="applyFilter('')" class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-800 hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-100 underline self-start sm:self-auto cursor-pointer">
                    <span>✕</span>
                    <span>Tampilkan Semua Pegawai</span>
                </button>
            </div>

            <!-- 3. TABEL DAFTAR PJLP -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                            {{ isSuperadmin ? 'Rekapitulasi Kinerja per Pegawai' : 'Status Validasi per Pegawai' }}
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ isSuperadmin ? 'Monitoring status pelaporan aktivitas pegawai PJLP terdaftar' : 'Klik "Periksa Aktivitas" untuk melihat detail tugas & memvalidasi laporan pegawai' }}
                        </p>
                    </div>
                    <div class="text-[11px] text-slate-400">
                        Total: <strong>{{ usersList.length }}</strong> Pegawai
                    </div>
                </div>

                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 text-[11px]">
                            <tr>
                                <th class="py-3.5 px-4">Nama Lengkap & Jabatan</th>
                                <th class="py-3.5 px-4">Seksi</th>
                                <th class="py-3.5 px-4 text-center font-bold">Total Laporan</th>
                                <th class="py-3.5 px-4 text-center font-bold">Status</th>
                                <th v-if="!isSuperadmin" class="py-3.5 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <tr v-if="!usersList || usersList.length === 0">
                                <td :colspan="isSuperadmin ? 4 : 5" class="py-12 text-center text-slate-400">
                                    <div class="text-sm font-semibold">Tidak ada data pegawai PJLP ditemukan.</div>
                                    <p class="text-xs mt-1">Coba sesuaikan kata kunci pencarian atau periode bulan.</p>
                                </td>
                            </tr>
                            <tr v-for="user in usersList" :key="user.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition group">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl text-white font-extrabold flex items-center justify-center text-xs shadow-sm flex-shrink-0"
                                            :class="user.role === 'Kasi' ? 'bg-gradient-to-br from-indigo-600 to-purple-700' : 'bg-gradient-to-br from-emerald-500 to-teal-700'">
                                            {{ user.nama_lengkap.slice(0, 2).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900 dark:text-slate-100 group-hover:text-emerald-600 transition">{{ user.nama_lengkap }}</span>
                                                <span v-if="user.role === 'Kasi'" class="px-1.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 ring-1 ring-indigo-400/40">
                                                    Kasi
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 mt-0.5">{{ user.posisi || (user.role === 'Kasi' ? 'Kepala Seksi' : 'PJLP Kominfotik') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ user.seksi || 'Pimpinan' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-black text-slate-900 dark:text-slate-100">
                                    {{ user.total_aktivitas }}
                                </td>
                                <!-- KOLOM STATUS TUNGGAL (BERUBAH MENGIKUTI VALIDASI) -->
                                <td class="py-3.5 px-4 text-center">
                                    <!-- Status: Belum Ada Laporan -->
                                    <span v-if="getUserStatus(user).type === 'empty'"
                                        class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                        Belum Ada Laporan
                                    </span>

                                    <!-- Status: Menunggu Validasi -->
                                    <span v-else-if="getUserStatus(user).type === 'menunggu'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800 shadow-xs">
                                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span>Menunggu ({{ user.menunggu }})</span>
                                    </span>

                                    <!-- Status: Disetujui -->
                                    <span v-else-if="getUserStatus(user).type === 'disetujui'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 shadow-xs">
                                        <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Disetujui</span>
                                    </span>

                                    <!-- Status: Ditolak -->
                                    <span v-else-if="getUserStatus(user).type === 'ditolak'"
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-800 shadow-xs">
                                        <svg class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span>Ditolak</span>
                                    </span>

                                    <!-- Status: Sebagian Disetujui -->
                                    <span v-else
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-800 shadow-xs">
                                        <span>Sebagian ({{ user.disetujui }}/{{ user.total_aktivitas }})</span>
                                    </span>
                                </td>

                                <!-- KOLOM AKSI: PERIKSA AKTIVITAS (MEMBUKA POP-UP) -->
                                <td v-if="!isSuperadmin" class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <button @click="openValidationModal(user)" type="button"
                                        class="inline-flex items-center gap-2 text-xs px-3.5 py-1.5 rounded-xl bg-slate-900 dark:bg-slate-800 hover:bg-emerald-600 text-white font-bold transition shadow-sm cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                        </svg>
                                        <span>Periksa Aktivitas</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Adaptive Cards Feed for PJLP List -->
                <div class="block md:hidden p-3.5 space-y-3 bg-slate-50/50 dark:bg-slate-950/20">
                    <div v-if="!usersList || usersList.length === 0" class="py-10 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 p-6">
                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mb-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Tidak ada pegawai ditemukan</div>
                        <p class="text-xs mt-1 text-slate-500">Sesuaikan pencarian atau periode bulan.</p>
                    </div>

                    <div v-for="user in usersList" :key="'mob-pjlp-' + user.id"
                        class="p-4 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xs space-y-3 transition active:bg-slate-50/50">
                        <!-- Top Header: Avatar, Name, Role & Status -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-10 h-10 rounded-xl text-white font-extrabold flex items-center justify-center text-xs shadow-sm flex-shrink-0"
                                    :class="user.role === 'Kasi' ? 'bg-gradient-to-br from-indigo-600 to-purple-700' : 'bg-gradient-to-br from-emerald-500 to-teal-700'">
                                    {{ user.nama_lengkap.slice(0, 2).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-xs text-slate-900 dark:text-slate-100 truncate flex items-center gap-1.5">
                                        <span>{{ user.nama_lengkap }}</span>
                                        <span v-if="user.role === 'Kasi'" class="px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300">
                                            Kasi
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                                        {{ user.posisi || (user.role === 'Kasi' ? 'Kepala Seksi' : 'PJLP Kominfotik') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <span v-if="getUserStatus(user).type === 'empty'"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex-shrink-0">
                                Belum Ada Laporan
                            </span>
                            <span v-else-if="getUserStatus(user).type === 'menunggu'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-800 shadow-2xs flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                <span>Menunggu ({{ user.menunggu }})</span>
                            </span>
                            <span v-else-if="getUserStatus(user).type === 'disetujui'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 shadow-2xs flex-shrink-0">
                                <span>✓ Disetujui</span>
                            </span>
                            <span v-else-if="getUserStatus(user).type === 'ditolak'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-800 shadow-2xs flex-shrink-0">
                                <span>✕ Ditolak</span>
                            </span>
                            <span v-else
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-800 shadow-2xs flex-shrink-0">
                                <span>Sebagian ({{ user.disetujui }}/{{ user.total_aktivitas }})</span>
                            </span>
                        </div>

                        <!-- Mid details: Seksi + Total Laporan stats -->
                        <div class="flex items-center justify-between text-xs pt-1 px-1">
                            <span class="px-2.5 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                🏢 {{ user.seksi || 'Pimpinan' }}
                            </span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                Total Laporan: <strong class="text-slate-900 dark:text-slate-100 font-mono">{{ user.total_aktivitas }}</strong>
                            </span>
                        </div>

                        <!-- Action Button (Min height 44px tap target) -->
                        <div v-if="!isSuperadmin" class="pt-2 border-t border-slate-100 dark:border-slate-800">
                            <button @click="openValidationModal(user)" type="button"
                                class="w-full py-2.5 px-4 rounded-xl bg-slate-900 dark:bg-slate-800 hover:bg-emerald-600 text-white font-bold text-xs transition shadow-2xs flex items-center justify-center gap-2 active:scale-95 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                <span>Periksa Aktivitas Pegawai</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- POP-UP MODAL: DAFTAR AKTIVITAS & FORM PEMILIHAN VALIDASI -->
        <!-- ============================================================ -->
        <div v-if="showValidationModal && activeUser"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200"
            @click.self="closeValidationModal">
            
            <div class="max-w-4xl w-full max-h-[90vh] bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 flex flex-col overflow-hidden animate-in zoom-in-95 duration-200">
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-start justify-between bg-slate-50/50 dark:bg-slate-800/40">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white font-black flex items-center justify-center text-base shadow-sm">
                            {{ activeUser.nama_lengkap.slice(0, 2).toUpperCase() }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-slate-100">
                                    {{ activeUser.nama_lengkap }}
                                </h3>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                    {{ activeUser.seksi || 'Seksi ASTIK' }}
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ activeUser.posisi || 'PJLP Kominfotik' }} • Periode {{ filterMonth }}
                            </div>
                        </div>
                    </div>

                    <button @click="closeValidationModal" type="button"
                        class="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Sub-bar Status & Quick Actions -->
                <div class="px-6 py-3 bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3 font-medium">
                        <span class="text-slate-600 dark:text-slate-300">
                            Total: <strong>{{ activeUser.total_aktivitas }}</strong> Laporan
                        </span>
                        <span class="text-slate-300 dark:text-slate-700">|</span>
                        <span class="text-amber-600 font-bold">
                            Menunggu: {{ activeUser.menunggu }}
                        </span>
                        <span class="text-slate-300 dark:text-slate-700">|</span>
                        <span class="text-emerald-600 font-bold">
                            Disetujui: {{ activeUser.disetujui }}
                        </span>
                        <span class="text-slate-300 dark:text-slate-700">|</span>
                        <span class="text-rose-600 font-bold">
                            Ditolak: {{ activeUser.ditolak }}
                        </span>
                    </div>

                    <!-- Tombol Setujui Semua yang Menunggu -->
                    <button v-if="activeUser.menunggu > 0"
                        @click="approveAllPending(activeUser)"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>Setujui Semua yang Menunggu ({{ activeUser.menunggu }})</span>
                    </button>
                </div>

                <!-- Modal Body: Daftar Aktivitas -->
                <div class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-4">
                    <!-- Empty State -->
                    <div v-if="!activeUser.activities || activeUser.activities.length === 0"
                        class="py-16 text-center text-slate-400">
                        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="text-sm font-bold text-slate-700 dark:text-slate-300">Belum ada aktivitas yang dikirim</div>
                        <p class="text-xs text-slate-400 mt-1">Pegawai ini belum mengirimkan laporan aktivitas pada periode bulan {{ filterMonth }}.</p>
                    </div>

                    <!-- Activity Cards List -->
                    <div v-for="(act, idx) in activeUser.activities" :key="act.id"
                        class="rounded-2xl border p-4 sm:p-5 transition-all shadow-xs"
                        :class="[
                            act.status_validasi === 'Menunggu' ? 'border-amber-300 dark:border-amber-800/80 bg-amber-50/20 dark:bg-amber-950/20' :
                            act.status_validasi === 'Disetujui' ? 'border-emerald-200 dark:border-emerald-800/60 bg-white dark:bg-slate-800/40' :
                            'border-rose-200 dark:border-rose-800/60 bg-rose-50/20 dark:bg-rose-950/20'
                        ]">
                        <!-- Card Header -->
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 pb-3 border-b border-slate-100 dark:border-slate-800">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-lg text-xs font-black bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 font-mono">
                                    #{{ idx + 1 }}
                                </span>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {{ formatDateIndo(act.tanggal) }}
                                </span>
                                <span v-if="act.jam_mulai && act.jam_selesai" class="text-xs text-slate-400 font-mono">
                                    • {{ formatTimeShort(act.jam_mulai) }} - {{ formatTimeShort(act.jam_selesai) }} WIB
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300">
                                    {{ act.kategori }}
                                </span>
                                <span v-if="act.lokasi" class="px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                    📍 {{ act.lokasi }}
                                </span>
                            </div>

                            <!-- Status Badge -->
                            <div>
                                <span v-if="act.status_validasi === 'Menunggu'"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 ring-1 ring-amber-400/40">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                    <span>Menunggu Validasi</span>
                                </span>
                                <span v-else-if="act.status_validasi === 'Disetujui'"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200 ring-1 ring-emerald-400/40">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Disetujui</span>
                                </span>
                                <span v-else-if="act.status_validasi === 'Ditolak'"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 dark:bg-rose-900/60 text-rose-800 dark:text-rose-200 ring-1 ring-rose-400/40">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span>Ditolak</span>
                                </span>
                            </div>
                        </div>

                        <!-- Card Body: Uraian Tugas & Capaian -->
                        <div class="py-3 text-xs sm:text-sm text-slate-800 dark:text-slate-200 whitespace-pre-line leading-relaxed">
                            {{ act.uraian }}
                        </div>

                        <!-- Lampiran Foto & Dokumen (Multi-format smart display) -->
                        <div v-if="getFileList(act.lampiran).length > 0" class="pt-2 pb-3 border-t border-slate-100 dark:border-slate-800">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                                Berkas Lampiran ({{ getFileList(act.lampiran).length }} Berkas):
                            </div>

                            <!-- List File Badges -->
                            <div class="flex flex-wrap gap-2 mb-3">
                                <template v-for="(file, fIdx) in getFileList(act.lampiran)" :key="'badge-'+fIdx">
                                    <!-- Dokumen PDF -->
                                    <a v-if="getFileType(file) === 'pdf'"
                                        :href="'/storage/lampiran_aktivitas/' + file"
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 hover:bg-rose-100 transition text-xs font-bold shadow-xs">
                                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        <span class="truncate max-w-[150px]">{{ file }}</span>
                                        <span class="text-[10px] text-rose-500 font-mono">PDF ↗</span>
                                    </a>

                                    <!-- Foto / Gambar -->
                                    <button v-else-if="getFileType(file) === 'image'"
                                        type="button"
                                        @click="openPhotoViewer('/storage/lampiran_aktivitas/' + file, file, getPhotoUrlsFromLampiran(act.lampiran), getPhotoIndexInLampiran(act.lampiran, file))"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 transition text-xs font-bold shadow-xs cursor-pointer">
                                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <span class="truncate max-w-[150px]">{{ file }}</span>
                                        <span class="text-[10px] text-emerald-500 font-mono">Lihat 🔍</span>
                                    </button>

                                    <!-- Berkas Lainnya -->
                                    <a v-else
                                        :href="'/storage/lampiran_aktivitas/' + file"
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-200 transition text-xs font-semibold shadow-xs">
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        <span class="truncate max-w-[150px]">{{ file }}</span>
                                        <span class="text-[10px] text-slate-400 font-mono">Unduh ↗</span>
                                    </a>
                                </template>
                            </div>

                            <!-- Thumbnail Galeri Foto -->
                            <div v-if="getPhotoUrlsFromLampiran(act.lampiran).length > 0" class="flex flex-wrap gap-2">
                                <template v-for="(file, fIdx) in getFileList(act.lampiran)" :key="'thumb-'+fIdx">
                                    <button v-if="getFileType(file) === 'image'"
                                        type="button"
                                        @click="openPhotoViewer('/storage/lampiran_aktivitas/' + file, file, getPhotoUrlsFromLampiran(act.lampiran), getPhotoIndexInLampiran(act.lampiran, file))"
                                        class="w-16 h-16 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 hover:opacity-85 transition ring-1 ring-transparent hover:ring-emerald-500 shadow-xs relative group cursor-pointer">
                                        <img :src="'/storage/lampiran_aktivitas/' + file" :alt="file" class="w-full h-full object-cover" />
                                        <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-bold transition">
                                            🔍
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Catatan Validasi Sebelumnya (Jika ada) -->
                        <div v-if="act.catatan" class="mt-2 p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-xs">
                            <span class="font-bold text-slate-600 dark:text-slate-400">Catatan Sebelumnya:</span>
                            <span class="text-slate-700 dark:text-slate-300 ml-1.5 italic">"{{ act.catatan }}"</span>
                        </div>

                        <!-- PEMILIHAN VALIDASI (DISETUJUI / DITOLAK) -->
                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <!-- OPSI 1: JIKA MASIH MENUNGGU VALIDASI (BISA PILIH SETUJUI ATAU TOLAK) -->
                            <template v-if="act.status_validasi === 'Menunggu'">
                                <div class="flex-1 max-w-md">
                                    <input type="text"
                                        v-model="activityNotes[act.id]"
                                        placeholder="Catatan/arahan validasi (opsional)..."
                                        class="w-full text-xs rounded-xl border-slate-200 dark:border-slate-700 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-emerald-500 py-1.5 px-3" />
                                </div>

                                <div class="flex items-center gap-2 self-end sm:self-auto">
                                    <!-- Tombol Setujui -->
                                    <button type="button"
                                        @click="validateSingleActivity(act, 'Disetujui')"
                                        :disabled="validatingActivityId === act.id"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-xs cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Setujui</span>
                                    </button>

                                    <!-- Tombol Tolak -->
                                    <button type="button"
                                        @click="validateSingleActivity(act, 'Ditolak')"
                                        :disabled="validatingActivityId === act.id"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white transition shadow-xs cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span>Tolak</span>
                                    </button>
                                </div>
                            </template>

                            <!-- OPSI 2: JIKA SUDAH DISETUJUI (TOMBOL TOLAK HILANG & TERKUNCI) -->
                            <template v-else-if="act.status_validasi === 'Disetujui'">
                                <div class="text-xs text-slate-500 italic">
                                    Aktivitas telah disetujui (Keputusan final, tidak dapat diubah).
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 shadow-xs">
                                    <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                    <span>Telah Disetujui ✓</span>
                                </div>
                            </template>

                            <!-- OPSI 3: JIKA SUDAH DITOLAK (TOMBOL SETUJUI HILANG & TERKUNCI) -->
                            <template v-else-if="act.status_validasi === 'Ditolak'">
                                <div class="text-xs text-slate-500 italic">
                                    Aktivitas telah ditolak (Menunggu perbaikan dari pegawai PJLP).
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-300 dark:border-rose-800 shadow-xs">
                                    <svg class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span>Telah Ditolak ✕</span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/40">
                    <div class="text-xs text-slate-500">
                        Perubahan validasi langsung tersimpan dan status diperbarui secara otomatis.
                    </div>
                    <button @click="closeValidationModal" type="button"
                        class="px-5 py-2 rounded-xl text-xs font-bold bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 transition cursor-pointer">
                        Selesai & Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- LIGHTBOX / PHOTO VIEWER MODAL -->
        <!-- ============================================================ -->
        <div v-if="showPhotoViewer"
            class="fixed inset-0 z-[100] flex flex-col items-center justify-center p-3 sm:p-6 bg-black/90 backdrop-blur-md animate-in fade-in duration-200"
            @click.self="closePhotoViewer">
            <div class="w-full max-w-5xl flex items-center justify-between text-white pb-3 px-2">
                <div class="flex items-center gap-3">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-white/20 text-white">
                        Foto {{ (activePhotoViewer.index || 0) + 1 }} dari {{ activePhotoViewer.list?.length || 1 }}
                    </span>
                    <span class="text-xs text-slate-300 truncate max-w-xs sm:max-w-md">{{ activePhotoViewer.title }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <a :href="activePhotoViewer.url" target="_blank" download
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition">
                        <span>Unduh / Buka ↗</span>
                    </a>
                    <button @click="closePhotoViewer" type="button"
                        class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-sm font-bold transition cursor-pointer">
                        ✕
                    </button>
                </div>
            </div>

            <div class="relative w-full max-w-5xl flex-1 flex items-center justify-center min-h-0">
                <button v-if="activePhotoViewer.list?.length > 1" @click.stop="prevPhotoViewer" type="button"
                    class="absolute left-2 sm:left-4 z-10 w-11 h-11 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center text-xl font-bold shadow-lg transition cursor-pointer"
                    title="Foto Sebelumnya">
                    ‹
                </button>

                <img :src="activePhotoViewer.url" :alt="activePhotoViewer.title"
                    class="max-h-[80vh] max-w-full rounded-2xl object-contain shadow-2xl transition duration-200" />

                <button v-if="activePhotoViewer.list?.length > 1" @click.stop="nextPhotoViewer" type="button"
                    class="absolute right-2 sm:right-4 z-10 w-11 h-11 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center text-xl font-bold shadow-lg transition cursor-pointer"
                    title="Foto Selanjutnya">
                    ›
                </button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- FLOATING TOAST NOTIFICATION -->
        <!-- ============================================================ -->
        <transition enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="toast.show" class="fixed bottom-5 right-5 z-[110] flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl text-white text-xs font-bold"
                :class="toast.type === 'success' ? 'bg-emerald-600' : 'bg-rose-600'">
                <span>{{ toast.type === 'success' ? '✓' : '⚠' }}</span>
                <span>{{ toast.message }}</span>
            </div>
        </transition>
    </AuthenticatedLayout>
</template>
