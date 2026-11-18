<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    activities: Object,
    stats: Object,
    seksiList: Array,
    usersList: Array,
    filters: Object,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});

// State filter
const filterMonth = ref(props.filters?.bulan || new Date().toISOString().slice(0, 7));
const filterSeksi = ref(props.filters?.seksi || '');
const filterUserId = ref(props.filters?.user_id || '');
const filterStatus = ref(props.filters?.status || '');
const search = ref(props.filters?.search || '');

// Modal state
const showDetailModal = ref(false);
const previewActivity = ref(null);

const openDetailModal = (activity) => {
    previewActivity.value = activity;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    previewActivity.value = null;
};

const applyFilter = (statusOverride) => {
    const statusVal = statusOverride !== undefined ? statusOverride : filterStatus.value;
    filterStatus.value = statusVal;

    router.get(route('activity.index'), {
        bulan: filterMonth.value || undefined,
        seksi: filterSeksi.value || undefined,
        user_id: filterUserId.value || undefined,
        status: statusVal || undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
};

const resetFilter = () => {
    filterMonth.value = new Date().toISOString().slice(0, 7);
    filterSeksi.value = '';
    filterUserId.value = '';
    filterStatus.value = '';
    search.value = '';
    applyFilter('');
};

const isFiltered = computed(() => {
    return Boolean(
        (filterMonth.value && filterMonth.value !== new Date().toISOString().slice(0, 7)) ||
        filterSeksi.value ||
        filterUserId.value ||
        filterStatus.value ||
        search.value
    );
});

// Fitur Export Rekap Aktivitas Excel & PDF
const getExportQueryString = () => {
    const params = new URLSearchParams();
    if (filterMonth.value) params.append('bulan', filterMonth.value);
    if (filterSeksi.value && filterSeksi.value !== 'all') params.append('seksi', filterSeksi.value);
    if (filterUserId.value && filterUserId.value !== 'all') params.append('user_id', filterUserId.value);
    if (filterStatus.value && filterStatus.value !== 'all') params.append('status', filterStatus.value);
    if (search.value) params.append('search', search.value);
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

// State Lightbox Photo Viewer
const showPhotoViewer = ref(false);
const activePhotoViewer = ref({
    url: '',
    title: '',
    index: 0,
    list: [],
});

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

// Format Tanggal Indonesia
const formatDateIndo = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('id-ID', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
};

// Deteksi Tipe Lampiran Berkas
const getFileType = (filename) => {
    if (!filename) return 'other';
    const clean = filename.trim().split('?')[0];
    const ext = clean.split('.').pop()?.toLowerCase();
    if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(ext)) return 'image';
    if (ext === 'pdf') return 'pdf';
    if (['xlsx', 'xls', 'csv'].includes(ext)) return 'excel';
    return 'other';
};

const getFileIcon = (filename) => {
    const type = getFileType(filename);
    if (type === 'image') return '🖼️';
    if (type === 'pdf') return '📄';
    if (type === 'excel') return '📊';
    return '📎';
};

const getFileLabel = (filename, index) => {
    const type = getFileType(filename);
    if (type === 'image') return `Foto ${index + 1}`;
    if (type === 'pdf') return `PDF ${index + 1}`;
    if (type === 'excel') return `Excel ${index + 1}`;
    return `Berkas ${index + 1}`;
};

const getFileList = (lampiranStr) => {
    if (!lampiranStr) return [];
    return lampiranStr.split(',').map(f => f.trim()).filter(Boolean);
};
</script>

<template>
    <Head title="Rekap Seluruh Aktivitas Pegawai - Superadmin" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 ring-1 ring-purple-300/40">
                            Monitoring Seluruh Aktivitas
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 ring-1 ring-slate-300/40">
                            Read-Only
                        </span>
                        <span class="text-xs text-slate-400">• Periode {{ filterMonth }}</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                        Rekap Seluruh Aktivitas Pegawai
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Pemantauan langsung seluruh entri aktivitas dan capaian tugas yang dikirimkan oleh seluruh pegawai.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                    <!-- Tombol Export Excel -->
                    <button @click="exportExcel" type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white shadow-xs shadow-emerald-600/20 transition cursor-pointer">
                        <svg class="w-4 h-4 text-emerald-100" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM6 20V4h7v5h5v11H6zm10.7-5.5l-1.8 2.7 1.9 2.8h-1.5l-1.2-1.8-1.2 1.8h-1.5l1.9-2.8-1.8-2.7h1.5l1.1 1.7 1.1-1.7h1.6z"/>
                        </svg>
                        <span>Export Excel</span>
                    </button>

                    <!-- Tombol Export PDF -->
                    <button @click="exportPdf" type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white shadow-xs shadow-rose-600/20 transition cursor-pointer">
                        <svg class="w-4 h-4 text-rose-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-6 4h4"/>
                        </svg>
                        <span>Export PDF</span>
                    </button>

                    <!-- Tombol Segarkan Data -->
                    <button @click="applyFilter()" type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 shadow-xs transition cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Segarkan Data</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- 1. SUMMARY STATS CARDS -->
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3.5">
                <!-- Total Aktivitas -->
                <button type="button" @click="applyFilter('')"
                    class="text-left bg-white dark:bg-slate-900 p-4 rounded-2xl border transition shadow-xs"
                    :class="!filterStatus ? 'border-slate-900 dark:border-slate-100 ring-2 ring-slate-900/10' : 'border-slate-200/80 dark:border-slate-800 hover:border-slate-400'">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Laporan</div>
                    <div class="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1.5">{{ stats?.total || 0 }}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Semua status</div>
                </button>

                <!-- Menunggu Validasi -->
                <button type="button" @click="applyFilter('Menunggu')"
                    class="text-left bg-white dark:bg-slate-900 p-4 rounded-2xl border transition shadow-xs"
                    :class="filterStatus === 'Menunggu' ? 'border-amber-500 ring-2 ring-amber-500/20' : 'border-amber-200/70 dark:border-amber-800/50 hover:border-amber-400'">
                    <div class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider flex items-center justify-between">
                        <span>Menunggu</span>
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    </div>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1.5">{{ stats?.menunggu || 0 }}</div>
                    <div class="text-[11px] text-amber-600/80 mt-0.5 font-medium">Perlu review Kasi</div>
                </button>

                <!-- Aktivitas Disetujui -->
                <button type="button" @click="applyFilter('Disetujui')"
                    class="text-left bg-white dark:bg-slate-900 p-4 rounded-2xl border transition shadow-xs"
                    :class="filterStatus === 'Disetujui' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-emerald-200/70 dark:border-emerald-800/50 hover:border-emerald-400'">
                    <div class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Disetujui</div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1.5">{{ stats?.disetujui || 0 }}</div>
                    <div class="text-[11px] text-emerald-600/80 mt-0.5">Tervalidasi atasan</div>
                </button>

                <!-- Aktivitas Ditolak -->
                <button type="button" @click="applyFilter('Ditolak')"
                    class="text-left bg-white dark:bg-slate-900 p-4 rounded-2xl border transition shadow-xs"
                    :class="filterStatus === 'Ditolak' ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-rose-200/70 dark:border-rose-800/50 hover:border-rose-400'">
                    <div class="text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider">Ditolak</div>
                    <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1.5">{{ stats?.ditolak || 0 }}</div>
                    <div class="text-[11px] text-rose-600/80 mt-0.5">Perlu perbaikan</div>
                </button>

                <!-- Pegawai Melapor -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-blue-200/70 dark:border-blue-800/50 shadow-xs">
                    <div class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Pegawai Melapor</div>
                    <div class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1.5">{{ stats?.total_pegawai || 0 }}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Pengirim aktivitas</div>
                </div>
            </div>

            <!-- 2. FILTER & PENCARIAN TOOLBAR -->
            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                    <!-- Filter Periode Bulan -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Periode Bulan</label>
                        <input type="month" v-model="filterMonth" @change="applyFilter()"
                            class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 focus:ring-purple-500 shadow-sm py-2 px-3 cursor-pointer" />
                    </div>

                    <!-- Filter Seksi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Seksi / Unit</label>
                        <select v-model="filterSeksi" @change="applyFilter()"
                            class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-medium text-slate-800 dark:text-slate-200 focus:ring-purple-500 shadow-sm py-2 px-3 cursor-pointer">
                            <option value="">Semua Seksi</option>
                            <option v-for="s in seksiList" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>

                    <!-- Filter Pegawai -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Pegawai Spesifik</label>
                        <select v-model="filterUserId" @change="applyFilter()"
                            class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-medium text-slate-800 dark:text-slate-200 focus:ring-purple-500 shadow-sm py-2 px-3 cursor-pointer">
                            <option value="">Semua Pegawai</option>
                            <option v-for="u in usersList" :key="u.id" :value="u.id">
                                {{ u.nama_lengkap }} ({{ u.pin || 'No PIN' }}) - {{ u.seksi || 'Pimpinan' }}
                            </option>
                        </select>
                    </div>

                    <!-- Filter Status Validasi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Status Validasi</label>
                        <select v-model="filterStatus" @change="applyFilter()"
                            class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-medium text-slate-800 dark:text-slate-200 focus:ring-purple-500 shadow-sm py-2 px-3 cursor-pointer">
                            <option value="">Semua Status</option>
                            <option value="Menunggu">Menunggu Review</option>
                            <option value="Disetujui">Disetujui</option>
                            <option value="Ditolak">Ditolak</option>
                        </select>
                    </div>
                </div>

                <!-- Baris Pencarian Teks & Aksi Reset -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <div class="relative flex-1 max-w-xl">
                        <input type="text" v-model="search" @keyup.enter="applyFilter()"
                            placeholder="Cari uraian aktivitas, nama pegawai, PIN, kategori, lokasi..."
                            class="w-full pl-9 pr-8 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs text-slate-800 dark:text-slate-200 focus:ring-purple-500 shadow-sm py-2" />
                        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <button v-if="search" @click="search = ''; applyFilter()" type="button"
                            class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600 text-xs">✕</button>
                    </div>

                    <div class="flex items-center gap-2 self-end sm:self-auto">
                        <button @click="applyFilter()" type="button"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-900 hover:bg-slate-800 text-white transition shadow-sm">
                            Cari
                        </button>
                        <button v-if="isFiltered" @click="resetFilter" type="button"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
                            Reset Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Banner Filter Aktif jika ada -->
            <div v-if="isFiltered" class="flex items-center justify-between px-4 py-2.5 rounded-xl bg-purple-50/70 dark:bg-purple-950/40 border border-purple-200/80 dark:border-purple-800/60 text-xs">
                <div class="flex items-center gap-2 text-purple-900 dark:text-purple-200">
                    <span class="font-bold">Filter Aktif:</span>
                    <span v-if="filterMonth" class="px-2 py-0.5 rounded bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-800 text-[11px]">Bulan: {{ filterMonth }}</span>
                    <span v-if="filterSeksi" class="px-2 py-0.5 rounded bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-800 text-[11px]">Seksi: {{ filterSeksi }}</span>
                    <span v-if="filterStatus" class="px-2 py-0.5 rounded bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-800 text-[11px]">Status: {{ filterStatus }}</span>
                    <span v-if="search" class="px-2 py-0.5 rounded bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-800 text-[11px]">Pencarian: "{{ search }}"</span>
                </div>
                <button type="button" @click="resetFilter" class="text-xs font-bold text-purple-700 hover:text-purple-900 dark:text-purple-300 underline cursor-pointer">
                    Hapus Semua Filter
                </button>
            </div>

            <!-- 3. TABEL SELURUH AKTIVITAS -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                            <span>Daftar Entri Aktivitas yang Dikirimkan</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                {{ activities?.total || 0 }} entri
                            </span>
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Menampilkan seluruh rincian kegiatan pegawai secara real-time dalam mode pemantauan (read-only).
                        </p>
                    </div>

                    <div class="text-xs text-slate-500 self-end sm:self-auto font-medium">
                        Hal {{ activities?.current_page || 1 }} dari {{ activities?.last_page || 1 }}
                    </div>
                </div>

                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 text-[11px]">
                            <tr>
                                <th class="py-3.5 px-4 w-12 text-center">No</th>
                                <th class="py-3.5 px-4 min-w-[130px]">Tanggal & Waktu</th>
                                <th class="py-3.5 px-4 min-w-[200px]">Pegawai</th>
                                <th class="py-3.5 px-4 min-w-[160px]">Kategori & Lokasi</th>
                                <th class="py-3.5 px-4 min-w-[240px]">Uraian Aktivitas</th>
                                <th class="py-3.5 px-4 text-center min-w-[100px]">Lampiran</th>
                                <th class="py-3.5 px-4 text-center min-w-[110px]">Status</th>
                                <th class="py-3.5 px-4 text-center w-28">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <!-- Empty State -->
                            <tr v-if="!activities?.data || activities.data.length === 0">
                                <td colspan="8" class="py-14 text-center text-slate-400">
                                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 mb-3">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tidak ada aktivitas ditemukan</div>
                                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                        Belum ada aktivitas yang dikirimkan untuk kriteria filter atau periode bulan terpilih.
                                    </p>
                                    <button v-if="isFiltered" @click="resetFilter" type="button"
                                        class="mt-3 px-3.5 py-1.5 rounded-lg text-xs font-bold bg-slate-900 text-white hover:bg-slate-800 transition">
                                        Reset Filter
                                    </button>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr v-for="(act, idx) in activities?.data" :key="act.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition group">
                                <!-- No -->
                                <td class="py-3.5 px-4 text-center font-mono text-slate-400 text-xs">
                                    {{ ((activities.current_page - 1) * activities.per_page) + idx + 1 }}
                                </td>

                                <!-- Tanggal & Waktu -->
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">
                                        {{ formatDateIndo(act.tanggal) }}
                                    </div>
                                    <div class="text-[11px] font-mono text-slate-500 mt-0.5 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>{{ act.jam_mulai ? act.jam_mulai.slice(0, 5) : '08:00' }} - {{ act.jam_selesai ? act.jam_selesai.slice(0, 5) : '16:00' }} WIB</span>
                                    </div>
                                </td>

                                <!-- Pegawai -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg text-white font-extrabold flex items-center justify-center text-[11px] shadow-xs flex-shrink-0"
                                            :class="act.user?.role === 'Kasi' ? 'bg-gradient-to-br from-indigo-600 to-purple-700' : 'bg-gradient-to-br from-emerald-500 to-teal-700'">
                                            {{ (act.user?.nama_lengkap || 'P').slice(0, 2).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 dark:text-slate-100 truncate group-hover:text-purple-600 transition">
                                                {{ act.user?.nama_lengkap || 'Pegawai' }}
                                            </div>
                                            <div class="flex items-center gap-1.5 text-[10px] text-slate-500 mt-0.5">
                                                <span class="font-mono">{{ act.user?.pin || '-' }}</span>
                                                <span>•</span>
                                                <span class="px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 font-semibold text-slate-700 dark:text-slate-300">
                                                    {{ act.user?.seksi || 'Pimpinan' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Kategori & Lokasi -->
                                <td class="py-3.5 px-4">
                                    <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700">
                                        {{ act.kategori }}
                                    </span>
                                    <div v-if="act.lokasi || act.nama_unit" class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 truncate">
                                        📍 {{ act.lokasi || act.nama_unit }}
                                    </div>
                                </td>

                                <!-- Uraian Aktivitas -->
                                <td class="py-3.5 px-4">
                                    <p class="text-xs text-slate-800 dark:text-slate-200 line-clamp-2 leading-relaxed">
                                        {{ act.uraian }}
                                    </p>
                                </td>

                                <!-- Lampiran -->
                                <td class="py-3.5 px-4 text-center">
                                    <div v-if="getFileList(act.lampiran).length > 0" class="inline-flex flex-wrap gap-1 justify-center">
                                        <button type="button" @click="openDetailModal(act)"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700 transition">
                                            <span>📎</span>
                                            <span>{{ getFileList(act.lampiran).length }} berkas</span>
                                        </button>
                                    </div>
                                    <span v-else class="text-slate-400 text-xs">-</span>
                                </td>

                                <!-- Status Validasi -->
                                <td class="py-3.5 px-4 text-center">
                                    <span v-if="act.status_validasi === 'Disetujui'"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <span>✓</span>
                                        <span>Disetujui</span>
                                    </span>
                                    <span v-else-if="act.status_validasi === 'Ditolak'"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800"
                                        :title="act.catatan ? `Alasan: ${act.catatan}` : ''">
                                        <span>✕</span>
                                        <span>Ditolak</span>
                                    </span>
                                    <span v-else
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span>Menunggu</span>
                                    </span>
                                </td>

                                <!-- Aksi (Lihat Detail Read-only) -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <button @click="openDetailModal(act)" type="button"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition shadow-xs">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <span>Lihat</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Adaptive Cards for Rekap Aktivitas -->
                <div class="block md:hidden p-3.5 space-y-3 bg-slate-50/50 dark:bg-slate-950/20">
                    <!-- Empty State -->
                    <div v-if="!activities?.data || activities.data.length === 0" class="py-10 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 p-6">
                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 mb-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">Tidak ada aktivitas ditemukan</div>
                        <p class="text-xs text-slate-500 mt-1">Belum ada aktivitas untuk periode bulan terpilih.</p>
                    </div>

                    <!-- Cards -->
                    <div v-for="act in activities?.data" :key="'mob-rekap-' + act.id"
                        class="p-4 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xs space-y-3 transition active:bg-slate-50/50">
                        <!-- Top Row: Tanggal, Waktu & Status Badge -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-slate-900 dark:text-slate-100">
                                    {{ formatDateIndo(act.tanggal) }}
                                </div>
                                <div class="text-[11px] font-mono text-slate-500 mt-0.5 flex items-center gap-1">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>{{ act.jam_mulai ? act.jam_mulai.slice(0, 5) : '08:00' }} - {{ act.jam_selesai ? act.jam_selesai.slice(0, 5) : '16:00' }} WIB</span>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <span v-if="act.status_validasi === 'Disetujui'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex-shrink-0">
                                <span>✓ Disetujui</span>
                            </span>
                            <span v-else-if="act.status_validasi === 'Ditolak'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 flex-shrink-0">
                                <span>✕ Ditolak</span>
                            </span>
                            <span v-else
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                <span>Menunggu</span>
                            </span>
                        </div>

                        <!-- Pegawai info (if Kasi/Admin view) -->
                        <div v-if="act.user" class="flex items-center gap-2 pt-0.5">
                            <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white font-extrabold text-[10px] flex items-center justify-center flex-shrink-0">
                                {{ act.user.nama_lengkap.slice(0, 2).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-slate-800 dark:text-slate-200 truncate">{{ act.user.nama_lengkap }}</div>
                                <div class="text-[10px] text-slate-400 truncate">{{ act.user.posisi || 'Tenaga Pendukung PJLP' }}</div>
                            </div>
                        </div>

                        <!-- Kategori & Lokasi -->
                        <div class="flex items-center gap-2 flex-wrap text-[11px]">
                            <span class="px-2.5 py-0.5 rounded-lg font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 ring-1 ring-slate-200/60 dark:ring-slate-700">
                                {{ act.kategori }}
                            </span>
                            <span class="text-slate-500 dark:text-slate-400 flex items-center gap-1 truncate max-w-full">
                                <span>📍</span>
                                <span>{{ act.lokasi || '(Lokasi tidak diisi)' }}</span>
                                <span v-if="act.nama_unit" class="text-slate-400">({{ act.nama_unit }})</span>
                            </span>
                        </div>

                        <!-- Uraian Aktivitas -->
                        <div class="text-xs text-slate-800 dark:text-slate-200 leading-relaxed bg-slate-50/70 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                            {{ act.uraian }}
                        </div>

                        <!-- Lampiran -->
                        <div v-if="act.lampiran" class="pt-1 flex flex-wrap items-center gap-1.5">
                            <span class="text-[10px] text-slate-400 font-semibold mr-1">Lampiran:</span>
                            <template v-for="(file, fIdx) in act.lampiran.split(',')" :key="'mob-rek-f-' + fIdx">
                                <button v-if="getFileType(file.trim()) === 'image'" type="button"
                                    @click="openPhotoViewer('/storage/lampiran_aktivitas/' + file.trim(), file.trim(), getPhotoUrlsFromLampiran(act.lampiran), getPhotoIndexInLampiran(act.lampiran, file.trim()))"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800 transition active:scale-95 cursor-pointer">
                                    <span>🖼️</span>
                                    <span>Foto {{ fIdx + 1 }}</span>
                                    <span class="text-[9px] opacity-75">🔍</span>
                                </button>
                                <a v-else
                                    :href="'/storage/lampiran_aktivitas/' + file.trim()" target="_blank"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800 transition active:scale-95">
                                    <span>📄</span>
                                    <span>PDF</span>
                                    <span class="text-[9px] opacity-75">↗</span>
                                </a>
                            </template>
                        </div>

                        <!-- Detail Action Button (44px target) -->
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                            <button @click="openDetailModal(act)" type="button"
                                class="w-full py-2.5 px-4 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition flex items-center justify-center gap-1.5 active:scale-95 cursor-pointer">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span>Lihat Detail Lengkap</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4. PAGINATION -->
                <div v-if="activities?.links && activities.links.length > 3"
                    class="p-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/50 dark:bg-slate-800/30">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Menampilkan <span class="font-bold text-slate-900 dark:text-slate-100">{{ activities.from || 0 }}</span>
                        sampai <span class="font-bold text-slate-900 dark:text-slate-100">{{ activities.to || 0 }}</span>
                        dari total <span class="font-bold text-slate-900 dark:text-slate-100">{{ activities.total }}</span> entri aktivitas
                    </div>

                    <div class="flex flex-wrap items-center gap-1">
                        <template v-for="(link, i) in activities.links" :key="i">
                            <span v-if="!link.url"
                                v-html="link.label"
                                class="px-3 py-1.5 text-xs rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 bg-white dark:bg-slate-900 opacity-60 cursor-not-allowed">
                            </span>
                            <Link v-else
                                :href="link.url"
                                v-html="link.label"
                                class="px-3 py-1.5 text-xs rounded-xl border font-bold transition"
                                :class="link.active
                                    ? 'bg-slate-900 text-white border-slate-900 dark:bg-white dark:text-slate-900 shadow-xs'
                                    : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800'"
                                preserve-scroll />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- 5. MODAL DETAIL AKTIVITAS (READ-ONLY) -->
        <!-- ============================================================ -->
        <div v-if="showDetailModal && previewActivity"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-2xl w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5 max-h-[90vh] overflow-y-auto">
                <!-- Header Modal -->
                <div class="flex items-start justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl text-white font-black flex items-center justify-center text-sm shadow-sm flex-shrink-0"
                            :class="previewActivity.user?.role === 'Kasi' ? 'bg-gradient-to-br from-indigo-600 to-purple-700' : 'bg-gradient-to-br from-emerald-500 to-teal-700'">
                            {{ (previewActivity.user?.nama_lengkap || 'P').slice(0, 2).toUpperCase() }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="font-bold text-base text-slate-900 dark:text-slate-100">
                                    {{ previewActivity.user?.nama_lengkap || 'Detail Pegawai' }}
                                </h4>
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-purple-50 dark:bg-purple-950 text-purple-700 dark:text-purple-300 ring-1 ring-purple-300/40">
                                    Read-Only
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                PIN: <span class="font-mono font-bold">{{ previewActivity.user?.pin || '-' }}</span> • Seksi: {{ previewActivity.user?.seksi || 'Pimpinan' }} • {{ previewActivity.user?.posisi_pekerjaan || 'Pegawai' }}
                            </div>
                        </div>
                    </div>
                    <button @click="closeDetailModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                        ✕
                    </button>
                </div>

                <!-- Info Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/70 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Waktu Pelaksanaan</span>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-xs mt-1">
                            {{ formatDateIndo(previewActivity.tanggal) }}
                        </div>
                        <div class="text-[11px] font-mono text-slate-500 mt-0.5">
                            {{ previewActivity.jam_mulai ? previewActivity.jam_mulai.slice(0, 5) : '08:00' }} - {{ previewActivity.jam_selesai ? previewActivity.jam_selesai.slice(0, 5) : '16:00' }} WIB
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/70 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Kategori Aktivitas</span>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-xs mt-1">
                            {{ previewActivity.kategori }}
                        </div>
                        <div v-if="previewActivity.lokasi || previewActivity.nama_unit" class="text-[11px] text-slate-500 mt-0.5 truncate">
                            📍 {{ previewActivity.lokasi || previewActivity.nama_unit }}
                        </div>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/70 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Status Validasi</span>
                        <div class="mt-1">
                            <span v-if="previewActivity.status_validasi === 'Disetujui'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                ✓ Disetujui
                            </span>
                            <span v-else-if="previewActivity.status_validasi === 'Ditolak'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300 border border-rose-200 dark:border-rose-800">
                                ✕ Ditolak
                            </span>
                            <span v-else
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                ⏳ Menunggu Review
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Catatan Verifikator jika ada -->
                <div v-if="previewActivity.catatan"
                    class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800/80">
                    <span class="text-[10px] font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider block mb-1">
                        Catatan Evaluasi / Verifikator:
                    </span>
                    <p class="text-xs text-amber-900 dark:text-amber-200 font-medium whitespace-pre-line leading-relaxed">
                        {{ previewActivity.catatan }}
                    </p>
                </div>

                <!-- Uraian Lengkap Tugas -->
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">
                        Uraian Tugas & Capaian Pekerjaan:
                    </span>
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-100 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200 whitespace-pre-line leading-relaxed font-sans">
                        {{ previewActivity.uraian }}
                    </div>
                </div>

                <!-- Lampiran Bukti Dokumen & Foto -->
                <div v-if="getFileList(previewActivity.lampiran).length > 0">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-2">
                        Lampiran Berkas Bukti ({{ getFileList(previewActivity.lampiran).length }} Berkas):
                    </span>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div v-for="(file, fIdx) in getFileList(previewActivity.lampiran)" :key="fIdx"
                            class="p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 flex items-center justify-between gap-3 shadow-xs">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="text-lg flex-shrink-0">{{ getFileIcon(file) }}</span>
                                <div class="truncate">
                                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate text-xs">{{ getFileLabel(file, fIdx) }}</div>
                                    <div class="text-[10px] text-slate-400 truncate">{{ file }}</div>
                                </div>
                            </div>
                            <a :href="'/storage/lampiran_aktivitas/' + file" target="_blank"
                                class="flex-shrink-0 px-3 py-1.5 text-xs font-bold rounded-lg bg-slate-900 hover:bg-slate-800 text-white shadow-xs transition">
                                Buka ↗
                            </a>
                        </div>
                    </div>

                    <!-- Mini Galeri Thumbnail Foto -->
                    <div class="flex flex-wrap gap-2.5 mt-3">
                        <template v-for="(file, fIdx) in getFileList(previewActivity.lampiran)" :key="'thumb-'+fIdx">
                            <div v-if="getFileType(file) === 'image'"
                                @click="openPhotoViewer('/storage/lampiran_aktivitas/' + file, file, getPhotoUrlsFromLampiran(previewActivity.lampiran), getPhotoIndexInLampiran(previewActivity.lampiran, file))"
                                class="relative block w-24 h-24 rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-700 shadow-sm hover:opacity-90 transition group cursor-pointer"
                                title="Klik untuk memperbesar foto">
                                <img :src="'/storage/lampiran_aktivitas/' + file" :alt="file" class="w-full h-full object-cover" />
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-[10px] font-bold transition">
                                    Lihat Foto 🔍
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Footer Modal (Hanya Tombol Tutup & Keterangan Monitoring) -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                    <span class="text-[11px] text-slate-400">
                        Mode pemantauan Superadmin (read-only). Validasi dilakukan oleh atasan langsung / Kasi.
                    </span>
                    <button @click="closeDetailModal" type="button"
                        class="px-5 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- LIGHTBOX / MODAL PHOTO VIEWER -->
        <!-- ============================================================ -->
        <div v-if="showPhotoViewer"
            class="fixed inset-0 z-[100] flex flex-col items-center justify-center p-3 sm:p-6 bg-black/90 backdrop-blur-md animate-in fade-in duration-200"
            @click.self="closePhotoViewer">
            <!-- Header Lightbox -->
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
                        class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-sm font-bold transition">
                        ✕
                    </button>
                </div>
            </div>

            <!-- Container Foto & Navigasi -->
            <div class="relative w-full max-w-5xl flex-1 flex items-center justify-center min-h-0">
                <!-- Tombol Prev -->
                <button v-if="activePhotoViewer.list?.length > 1" @click.stop="prevPhotoViewer" type="button"
                    class="absolute left-2 sm:left-4 z-10 w-11 h-11 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center text-xl font-bold shadow-lg transition"
                    title="Foto Sebelumnya">
                    ‹
                </button>

                <!-- Foto Utama -->
                <img :src="activePhotoViewer.url" :alt="activePhotoViewer.title"
                    class="max-h-[80vh] max-w-full rounded-2xl object-contain shadow-2xl transition duration-200" />

                <!-- Tombol Next -->
                <button v-if="activePhotoViewer.list?.length > 1" @click.stop="nextPhotoViewer" type="button"
                    class="absolute right-2 sm:right-4 z-10 w-11 h-11 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center text-xl font-bold shadow-lg transition"
                    title="Foto Selanjutnya">
                    ›
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

