<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    LifeBuoy,
    Plus,
    Search,
    Filter,
    X,
    Clock,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Paperclip,
    Send,
    MessageSquare,
    Eye,
    Trash2,
    ExternalLink,
    FileText,
    RefreshCw,
    User,
    Building2,
    Calendar,
    ShieldAlert,
    UserCheck
} from 'lucide-vue-next';

const props = defineProps({
    reports: Object,
    stats: Object,
    filters: Object,
    categories: Array,
    priorities: Array,
    isSuperadmin: Boolean,
});

// Filter state
const filterSearch = ref(props.filters?.search || '');
const filterStatus = ref(props.filters?.status || 'all');
const filterKategori = ref(props.filters?.kategori || 'all');
const filterPrioritas = ref(props.filters?.prioritas || 'all');

const applyFilter = () => {
    router.get(route('issue-reports.index'), {
        search: filterSearch.value || undefined,
        status: filterStatus.value !== 'all' ? filterStatus.value : undefined,
        kategori: filterKategori.value !== 'all' ? filterKategori.value : undefined,
        prioritas: filterPrioritas.value !== 'all' ? filterPrioritas.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const resetFilter = () => {
    filterSearch.value = '';
    filterStatus.value = 'all';
    filterKategori.value = 'all';
    filterPrioritas.value = 'all';
    applyFilter();
};

// Modal states
const showCreateModal = ref(false);
const showDetailModal = ref(false);
const selectedReport = ref(null);
const fileInputRef = ref(null);
const filePreview = ref(null);

// Photo Preview Modal
const showPhotoModal = ref(false);
const photoPreviewUrl = ref('');

// Form for creating a new issue report
const createForm = useForm({
    judul: '',
    kategori: 'Aplikasi & Fitur',
    prioritas: 'Sedang',
    deskripsi: '',
    lampiran: null,
});

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        createForm.lampiran = file;
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (event) => {
                filePreview.value = event.target.result;
            };
            reader.readAsDataURL(file);
        } else {
            filePreview.value = null;
        }
    } else {
        createForm.lampiran = null;
        filePreview.value = null;
    }
};

const submitCreateReport = () => {
    createForm.post(route('issue-reports.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
            filePreview.value = null;
        },
    });
};

// Form for Superadmin update / resolution
const updateForm = useForm({
    status: 'Diproses',
    tanggapan_it: '',
});

const openDetailModal = (report) => {
    selectedReport.value = report;
    updateForm.status = report.status;
    updateForm.tanggapan_it = report.tanggapan_it || '';
    showDetailModal.value = true;
};

const submitUpdateReport = () => {
    if (!selectedReport.value) return;
    updateForm.patch(route('issue-reports.update', selectedReport.value.id), {
        onSuccess: () => {
            showDetailModal.value = false;
            selectedReport.value = null;
        },
    });
};

const deleteReport = (report) => {
    if (!confirm(`Konfirmasi: Apakah Anda yakin ingin membatalkan/menghapus tiket ${report.ticket_number}?`)) {
        return;
    }
    router.delete(route('issue-reports.destroy', report.id));
};

const openPhotoPreview = (url) => {
    photoPreviewUrl.value = url;
    showPhotoModal.value = true;
};

// ============================================================
// REALTIME SYNC & LIVE STATUS MONITORING
// ============================================================
let syncInterval = null;
const isSyncing = ref(false);
const lastSyncTime = ref(new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));

const syncDataRealtime = (silent = true) => {
    // Skip sync if tab is in background or active form is being submitted
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return;
    if (createForm.processing || updateForm.processing) return;

    if (!silent) isSyncing.value = true;

    router.reload({
        only: ['reports', 'stats'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isSyncing.value = false;
            lastSyncTime.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    });
};

// Immediate sync upon receiving notification from AuthenticatedLayout
const handleRealtimeNotification = () => {
    syncDataRealtime(true);
};

// Keep detail modal updated reactively if IT updates status / notes while modal is open
watch(() => props.reports, (newReports) => {
    if (selectedReport.value && newReports?.data) {
        const fresh = newReports.data.find(r => r.id === selectedReport.value.id);
        if (fresh) {
            selectedReport.value = { ...fresh };
            // If Superadmin has the modal open and hasn't edited the form yet:
            if (!updateForm.isDirty) {
                updateForm.status = fresh.status;
                updateForm.tanggapan_it = fresh.tanggapan_it || '';
            }
        }
    }
}, { deep: true });

onMounted(() => {
    // Poll every 4 seconds for real-time ticket status updates
    syncInterval = setInterval(() => {
        syncDataRealtime(true);
    }, 4000);

    window.addEventListener('app-notification-received', handleRealtimeNotification);
});

onUnmounted(() => {
    if (syncInterval) clearInterval(syncInterval);
    window.removeEventListener('app-notification-received', handleRealtimeNotification);
});

// Format date helper
const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Status styling helper
const getStatusBadge = (status) => {
    switch (status) {
        case 'Menunggu':
            return {
                bg: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900/50',
                dot: 'bg-amber-500',
                label: 'Menunggu Tim IT',
            };
        case 'Diproses':
            return {
                bg: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/30 dark:text-sky-400 dark:border-sky-900/50',
                dot: 'bg-sky-500 animate-pulse',
                label: 'Sedang Ditangani IT',
            };
        case 'Selesai':
            return {
                bg: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/50',
                dot: 'bg-emerald-500',
                label: 'Selesai',
            };
        case 'Ditolak':
            return {
                bg: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/50',
                dot: 'bg-rose-500',
                label: 'Ditolak',
            };
        default:
            return {
                bg: 'bg-slate-100 text-slate-700 border-slate-200',
                dot: 'bg-slate-400',
                label: status,
            };
    }
};

// Priority badge helper
const getPriorityBadge = (priority) => {
    switch (priority) {
        case 'Darurat':
            return 'bg-rose-100 text-rose-800 border-rose-300 font-bold';
        case 'Tinggi':
            return 'bg-orange-100 text-orange-800 border-orange-200 font-semibold';
        case 'Sedang':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'Rendah':
            return 'bg-slate-100 text-slate-700 border-slate-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};
</script>

<template>
    <Head title="Laporan Kendala & Aduan IT" />

    <AuthenticatedLayout>
        <!-- Page Header -->
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-900 text-white">
                            Helpdesk IT
                        </span>
                        <span class="text-xs text-slate-400">• Sudin Kominfotik Jakarta Utara</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-slate-100 flex items-center gap-2">
                        <LifeBuoy class="w-6 h-6 text-blue-600 flex-shrink-0" />
                        <span>Pusat Laporan Kendala & Aduan Sistem</span>
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ isSuperadmin
                            ? 'Panel pengelolaan & tindak lanjut kendala teknis dari seluruh pegawai PJLP, Kasi, dan Kasudin.'
                            : 'Laporkan kendala teknis aplikasi, absensi, akun, atau kamera secara langsung kepada Tim IT Superadmin.' }}
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <div class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200/80 text-[11px] font-medium shadow-2xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Realtime Aktif</span>
                    </div>

                    <button
                        type="button"
                        @click="syncDataRealtime(false)"
                        :disabled="isSyncing"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 active:scale-95 transition shadow-2xs disabled:opacity-60"
                        title="Segarkan data tiket sekarang"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin text-blue-600': isSyncing }" />
                        <span class="hidden md:inline">Segarkan</span>
                    </button>

                    <button
                        type="button"
                        @click="showCreateModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-xl bg-blue-600 text-white shadow-xs hover:bg-blue-500 active:scale-95 transition"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Laporkan Kendala Baru</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- ============================================================ -->
            <!-- KPI STATS CARDS                                              -->
            <!-- ============================================================ -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Laporan</span>
                        <LifeBuoy class="w-4 h-4 text-slate-400" />
                    </div>
                    <div class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-1">
                        {{ stats?.total || 0 }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Semua Tiket</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Menunggu IT</span>
                        <Clock class="w-4 h-4 text-amber-500" />
                    </div>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">
                        {{ stats?.menunggu || 0 }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Belum Ditangani</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-sky-600 dark:text-sky-400 uppercase tracking-wider">Sedang Diproses</span>
                        <RefreshCw class="w-4 h-4 text-sky-500 animate-spin" style="animation-duration: 8s" />
                    </div>
                    <div class="text-2xl font-black text-sky-600 dark:text-sky-400 mt-1">
                        {{ stats?.diproses || 0 }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Penanganan Aktif</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Telah Selesai</span>
                        <CheckCircle2 class="w-4 h-4 text-emerald-500" />
                    </div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">
                        {{ stats?.selesai || 0 }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Tuntas / Selesai</div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- FILTER TOOLBAR                                               -->
            <!-- ============================================================ -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <!-- Search Input -->
                    <div class="relative sm:col-span-1">
                        <Search class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                        <input
                            type="text"
                            v-model="filterSearch"
                            @keyup.enter="applyFilter"
                            placeholder="Cari no. tiket, judul, pelapor..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-blue-500 focus:border-blue-500 shadow-xs"
                        />
                    </div>

                    <!-- Filter Status -->
                    <div>
                        <select
                            v-model="filterStatus"
                            @change="applyFilter"
                            class="w-full py-1.5 px-3 text-xs rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-blue-500 focus:border-blue-500 shadow-xs"
                        >
                            <option value="all">Semua Status</option>
                            <option value="Menunggu">Menunggu</option>
                            <option value="Diproses">Sedang Diproses</option>
                            <option value="Selesai">Selesai</option>
                            <option value="Ditolak">Ditolak</option>
                        </select>
                    </div>

                    <!-- Filter Kategori -->
                    <div>
                        <select
                            v-model="filterKategori"
                            @change="applyFilter"
                            class="w-full py-1.5 px-3 text-xs rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-blue-500 focus:border-blue-500 shadow-xs"
                        >
                            <option value="all">Semua Kategori</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>

                    <!-- Filter Prioritas & Buttons -->
                    <div class="flex items-center gap-2">
                        <select
                            v-model="filterPrioritas"
                            @change="applyFilter"
                            class="w-full py-1.5 px-3 text-xs rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-blue-500 focus:border-blue-500 shadow-xs"
                        >
                            <option value="all">Semua Prioritas</option>
                            <option v-for="p in priorities" :key="p" :value="p">{{ p }}</option>
                        </select>
                        <button
                            type="button"
                            @click="applyFilter"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 text-white hover:bg-slate-800 transition shadow-xs"
                        >
                            Filter
                        </button>
                        <button
                            type="button"
                            @click="resetFilter"
                            title="Reset Filter"
                            class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition"
                        >
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- DAFTAR LAPORAN KENDALA (TABLE & CARDS)                        -->
            <!-- ============================================================ -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <!-- Desktop Table View -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100/80 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-3 w-10 text-center">No</th>
                                <th class="py-3 px-4">No. Tiket & Tanggal</th>
                                <th v-if="isSuperadmin" class="py-3 px-4">Pelapor</th>
                                <th class="py-3 px-4">Judul & Kategori</th>
                                <th class="py-3 px-3 text-center">Prioritas</th>
                                <th class="py-3 px-3 text-center">Status</th>
                                <th class="py-3 px-3 text-center">Lampiran</th>
                                <th class="py-3 px-4 text-center">Tindak Lanjut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <!-- Empty State -->
                            <tr v-if="!reports.data || reports.data.length === 0">
                                <td :colspan="isSuperadmin ? 8 : 7" class="py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-3">
                                        <LifeBuoy class="w-6 h-6 text-slate-400" />
                                    </div>
                                    <p class="font-semibold text-slate-700 dark:text-slate-300">Belum Ada Laporan Kendala</p>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        {{ isSuperadmin
                                            ? 'Tidak ada tiket aduan yang sesuai dengan kriteria filter saat ini.'
                                            : 'Sistem berjalan normal. Klik tombol "Laporkan Kendala Baru" jika Anda mengalami kendala.' }}
                                    </p>
                                </td>
                            </tr>

                            <!-- Rows -->
                            <tr
                                v-for="(item, idx) in reports.data"
                                :key="item.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition"
                            >
                                <td class="py-3 px-3 text-center text-slate-500 font-mono">
                                    {{ (reports.current_page - 1) * reports.per_page + idx + 1 }}
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded font-mono text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                                        {{ item.ticket_number }}
                                    </span>
                                    <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-1">
                                        <Clock class="w-3 h-3" />
                                        <span>{{ formatDate(item.created_at) }}</span>
                                    </div>
                                </td>
                                <td v-if="isSuperadmin" class="py-3 px-4">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">
                                        {{ item.user?.nama_lengkap || item.user?.name }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                                        <span class="px-1.5 py-0.2 rounded bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300 font-semibold text-[9px]">
                                            {{ item.user?.role }}
                                        </span>
                                        <span>{{ item.user?.seksi || 'Sudin Kominfotik' }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900 dark:text-slate-100 max-w-xs truncate" :title="item.judul">
                                        {{ item.judul }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ item.kategori }}
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] border shadow-2xs"
                                        :class="getPriorityBadge(item.prioritas)"
                                    >
                                        {{ item.prioritas }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold border shadow-2xs"
                                        :class="getStatusBadge(item.status).bg"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="getStatusBadge(item.status).dot"></span>
                                        <span>{{ getStatusBadge(item.status).label }}</span>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <button
                                        v-if="item.lampiran"
                                        type="button"
                                        @click="openPhotoPreview('/storage/' + item.lampiran)"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-200 text-[10px] font-medium text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/30 transition"
                                        title="Lihat lampiran berkas/bukti"
                                    >
                                        <Paperclip class="w-3 h-3" />
                                        <span>Lihat</span>
                                    </button>
                                    <span v-else class="text-[10px] text-slate-400">-</span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button
                                            type="button"
                                            @click="openDetailModal(item)"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition"
                                        >
                                            <Eye class="w-3.5 h-3.5" />
                                            <span>{{ isSuperadmin ? 'Tindak Lanjut' : 'Detail' }}</span>
                                        </button>

                                        <!-- User can cancel/delete if still 'Menunggu' -->
                                        <button
                                            v-if="(item.user_id === $page.props.auth.user.id && item.status === 'Menunggu') || isSuperadmin"
                                            type="button"
                                            @click="deleteReport(item)"
                                            class="p-1 rounded-lg text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition"
                                            title="Batalkan / Hapus Tiket"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile & Tablet Responsive Card View -->
                <div class="block lg:hidden divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-if="!reports.data || reports.data.length === 0" class="py-12 text-center text-slate-400 p-4">
                        <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-3">
                            <LifeBuoy class="w-6 h-6 text-slate-400" />
                        </div>
                        <p class="font-semibold text-slate-700 dark:text-slate-300">Belum Ada Laporan Kendala</p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ isSuperadmin
                                ? 'Tidak ada tiket aduan yang sesuai filter.'
                                : 'Klik "Laporkan Kendala Baru" jika mengalami kendala aplikasi/kamera/fingerprint.' }}
                        </p>
                    </div>

                    <div
                        v-for="(item, idx) in reports.data"
                        :key="item.id"
                        class="p-4 space-y-3 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition"
                    >
                        <!-- Card Header: Ticket No, Status, Priority -->
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded font-mono text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                                    {{ item.ticket_number }}
                                </span>
                                <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-1">
                                    <Clock class="w-3 h-3" />
                                    <span>{{ formatDate(item.created_at) }}</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span
                                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold border shadow-2xs"
                                    :class="getStatusBadge(item.status).bg"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full" :class="getStatusBadge(item.status).dot"></span>
                                    <span>{{ getStatusBadge(item.status).label }}</span>
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[9px] border shadow-2xs"
                                    :class="getPriorityBadge(item.prioritas)"
                                >
                                    {{ item.prioritas }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Body: Pelapor (if superadmin) & Judul & Deskripsi -->
                        <div>
                            <div v-if="isSuperadmin" class="text-xs text-slate-600 dark:text-slate-300 mb-1">
                                <span class="font-bold text-slate-900 dark:text-slate-100">{{ item.user?.nama_lengkap || item.user?.name }}</span>
                                <span class="text-[10px] text-slate-400 ml-1">({{ item.user?.role }} • {{ item.user?.seksi || 'Sudin Kominfotik' }})</span>
                            </div>
                            <h4 class="font-bold text-sm text-slate-900 dark:text-slate-100">
                                {{ item.judul }}
                            </h4>
                            <div class="text-[11px] text-blue-600 dark:text-blue-400 font-medium mt-0.5">
                                Kategori: {{ item.kategori }}
                            </div>
                            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1 line-clamp-2 leading-relaxed">
                                {{ item.deskripsi }}
                            </p>
                        </div>

                        <!-- Card Footer / Actions -->
                        <div class="flex items-center justify-between pt-1">
                            <div>
                                <button
                                    v-if="item.lampiran"
                                    type="button"
                                    @click="openPhotoPreview('/storage/' + item.lampiran)"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 text-xs font-medium text-blue-600 hover:bg-blue-50 transition"
                                >
                                    <Paperclip class="w-3.5 h-3.5" />
                                    <span>Bukti Lampiran</span>
                                </button>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="openDetailModal(item)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition shadow-xs"
                                >
                                    <Eye class="w-3.5 h-3.5" />
                                    <span>{{ isSuperadmin ? 'Tindak Lanjut' : 'Detail' }}</span>
                                </button>
                                <button
                                    v-if="(item.user_id === $page.props.auth.user.id && item.status === 'Menunggu') || isSuperadmin"
                                    type="button"
                                    @click="deleteReport(item)"
                                    class="p-1.5 rounded-xl text-rose-500 hover:bg-rose-50 border border-rose-200 transition"
                                    title="Batalkan Tiket"
                                >
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="reports.links && reports.links.length > 3" class="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                    <div>
                        Menampilkan <span class="font-bold text-slate-800 dark:text-slate-200">{{ reports.from || 0 }}</span> sampai
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ reports.to || 0 }}</span> dari
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ reports.total || 0 }}</span> tiket kendala
                    </div>
                    <div class="flex gap-1">
                        <button
                            v-for="(link, i) in reports.links"
                            :key="i"
                            v-html="link.label"
                            :disabled="!link.url"
                            @click="link.url && router.visit(link.url, { preserveState: true, replace: true })"
                            class="px-2.5 py-1 rounded-lg border text-xs font-medium transition"
                            :class="link.active
                                ? 'bg-blue-600 text-white border-blue-600'
                                : link.url
                                    ? 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-50'
                                    : 'opacity-40 cursor-not-allowed border-transparent'"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL BUAT LAPORAN KENDALA                                    -->
        <!-- ============================================================ -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-800/40">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center shadow-xs">
                            <LifeBuoy class="w-4 h-4" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                                Buat Laporan Kendala Sistem
                            </h3>
                            <p class="text-[11px] text-slate-500">Laporan akan langsung diteruskan ke Tim IT Superadmin</p>
                        </div>
                    </div>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitCreateReport" class="p-5 space-y-4">
                    <!-- Judul Kendala -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Judul Kendala <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            v-model="createForm.judul"
                            placeholder="Contoh: Gagal ambil foto saat dinas luar / fingerprint tidak terbaca"
                            class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 shadow-xs focus:ring-blue-500 focus:border-blue-500"
                            required
                        />
                        <div v-if="createForm.errors.judul" class="text-rose-500 text-[11px] mt-1">
                            {{ createForm.errors.judul }}
                        </div>
                    </div>

                    <!-- Kategori & Prioritas -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Kategori <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="createForm.kategori"
                                class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 shadow-xs focus:ring-blue-500 focus:border-blue-500"
                                required
                            >
                                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Tingkat Urgensi <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="createForm.prioritas"
                                class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 shadow-xs focus:ring-blue-500 focus:border-blue-500"
                                required
                            >
                                <option value="Rendah">Rendah (Pertanyaan / Minor)</option>
                                <option value="Sedang">Sedang (Kendala Biasa)</option>
                                <option value="Tinggi">Tinggi (Mempengaruhi Kerja)</option>
                                <option value="Darurat">Darurat (Sistem Terhenti)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Deskripsi Rinci -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Deskripsi Lengkap Kendala <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="createForm.deskripsi"
                            rows="4"
                            placeholder="Jelaskan secara detail: apa yang terjadi, langkah yang dilakukan sebelum error, pesan kesalahan yang muncul..."
                            class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 shadow-xs focus:ring-blue-500 focus:border-blue-500"
                            required
                        ></textarea>
                        <div v-if="createForm.errors.deskripsi" class="text-rose-500 text-[11px] mt-1">
                            {{ createForm.errors.deskripsi }}
                        </div>
                    </div>

                    <!-- Lampiran Bukti / Screenshot -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Lampiran Bukti / Tangkapan Layar (Opsional)
                        </label>
                        <input
                            type="file"
                            ref="fileInputRef"
                            @change="handleFileChange"
                            accept="image/png,image/jpeg,image/webp,application/pdf"
                            class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-slate-800 dark:file:text-slate-200"
                        />
                        <p class="text-[10px] text-slate-400 mt-1">Format: JPG, PNG, WEBP, atau PDF. Maksimal 5MB.</p>

                        <!-- Image Preview -->
                        <div v-if="filePreview" class="mt-2 relative inline-block">
                            <img :src="filePreview" alt="Pratinjau Bukti" class="h-20 w-auto rounded-lg border object-cover shadow-xs" />
                            <button
                                type="button"
                                @click="filePreview = null; createForm.lampiran = null; if (fileInputRef) fileInputRef.value = '';"
                                class="absolute -top-1.5 -right-1.5 bg-rose-600 text-white rounded-full p-0.5 hover:bg-rose-700"
                            >
                                <X class="w-3 h-3" />
                            </button>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold rounded-xl bg-blue-600 text-white hover:bg-blue-500 shadow-xs transition disabled:opacity-50"
                        >
                            <Send class="w-3.5 h-3.5" />
                            <span>{{ createForm.processing ? 'Mengirim...' : 'Kirim Laporan' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL DETAIL & TINDAK LANJUT IT                               -->
        <!-- ============================================================ -->
        <div v-if="showDetailModal && selectedReport" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-xl w-full border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50 dark:bg-slate-800/40">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200">
                                {{ selectedReport.ticket_number }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border" :class="getStatusBadge(selectedReport.status).bg">
                                {{ selectedReport.status }}
                            </span>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 mt-1">
                            {{ selectedReport.judul }}
                        </h3>
                    </div>
                    <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 p-1">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    <!-- Info Pelapor -->
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">
                                {{ (selectedReport.user?.nama_lengkap || selectedReport.user?.name || 'U').slice(0, 2).toUpperCase() }}
                            </div>
                            <div>
                                <div class="font-bold text-slate-900 dark:text-slate-100">
                                    {{ selectedReport.user?.nama_lengkap || selectedReport.user?.name }}
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    {{ selectedReport.user?.role }} • {{ selectedReport.user?.seksi || 'Sudin Kominfotik' }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right text-[10px] text-slate-400">
                            <div>Waktu Lapor:</div>
                            <div class="font-medium text-slate-600 dark:text-slate-300">{{ formatDate(selectedReport.created_at) }}</div>
                        </div>
                    </div>

                    <!-- Meta: Kategori & Prioritas -->
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="p-3 rounded-xl border border-slate-200/80 dark:border-slate-800">
                            <div class="text-[10px] text-slate-400 uppercase font-semibold">Kategori Kendala</div>
                            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ selectedReport.kategori }}</div>
                        </div>
                        <div class="p-3 rounded-xl border border-slate-200/80 dark:border-slate-800">
                            <div class="text-[10px] text-slate-400 uppercase font-semibold">Tingkat Urgensi</div>
                            <div class="font-bold mt-0.5" :class="selectedReport.prioritas === 'Darurat' ? 'text-rose-600' : 'text-slate-800 dark:text-slate-200'">
                                {{ selectedReport.prioritas }}
                            </div>
                        </div>
                    </div>

                    <!-- Deskripsi Masalah -->
                    <div>
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Penjelasan Kendala:</div>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-200 whitespace-pre-line leading-relaxed">
                            {{ selectedReport.deskripsi }}
                        </div>
                    </div>

                    <!-- Lampiran Bukti -->
                    <div v-if="selectedReport.lampiran">
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Lampiran Berkas / Bukti:</div>
                        <div class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Paperclip class="w-4 h-4 text-blue-600" />
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300 truncate max-w-xs">
                                    {{ selectedReport.lampiran.split('/').pop() }}
                                </span>
                            </div>
                            <button
                                type="button"
                                @click="openPhotoPreview('/storage/' + selectedReport.lampiran)"
                                class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-950 dark:text-blue-300 transition"
                            >
                                <Eye class="w-3.5 h-3.5" />
                                <span>Buka Lampiran</span>
                            </button>
                        </div>
                    </div>

                    <!-- ============================================================ -->
                    <!-- CATATAN & SOLUSI RESMI DARI SUPERADMIN (TIM IT)               -->
                    <!-- ============================================================ -->
                    <div
                        v-if="selectedReport.tanggapan_it"
                        class="p-4 rounded-2xl border space-y-2 shadow-xs transition"
                        :class="{
                            'bg-emerald-50/90 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-800 text-emerald-950 dark:text-emerald-100': selectedReport.status === 'Selesai',
                            'bg-sky-50/90 dark:bg-sky-950/30 border-sky-300 dark:border-sky-800 text-sky-950 dark:text-sky-100': selectedReport.status === 'Diproses',
                            'bg-rose-50/90 dark:bg-rose-950/30 border-rose-300 dark:border-rose-800 text-rose-950 dark:text-rose-100': selectedReport.status === 'Ditolak',
                            'bg-amber-50/90 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800 text-amber-950 dark:text-amber-100': selectedReport.status === 'Menunggu'
                        }"
                    >
                        <div class="flex items-center justify-between flex-wrap gap-2 text-xs font-bold">
                            <span class="flex items-center gap-1.5">
                                <MessageSquare class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                <span>Catatan Resmi dari Tim IT Superadmin:</span>
                            </span>
                            <span v-if="selectedReport.resolved_at || selectedReport.handled_at" class="text-[10px] font-normal opacity-80">
                                Ditindaklanjuti: {{ formatDate(selectedReport.resolved_at || selectedReport.handled_at) }}
                            </span>
                        </div>
                        <div class="p-3 rounded-xl bg-white/90 dark:bg-slate-900/90 border border-slate-200/70 dark:border-slate-800 text-xs text-slate-800 dark:text-slate-100 whitespace-pre-line leading-relaxed font-medium">
                            {{ selectedReport.tanggapan_it }}
                        </div>
                        <div v-if="selectedReport.handler" class="text-[11px] text-slate-600 dark:text-slate-400 flex items-center gap-1.5 pt-1">
                            <UserCheck class="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                            <span>Ditangani oleh: <strong>{{ selectedReport.handler?.nama_lengkap || selectedReport.handler?.name }}</strong> (Tim IT Superadmin Sudin Kominfotik)</span>
                        </div>
                    </div>

                    <!-- Belum ada catatan tertulis dari Superadmin -->
                    <div
                        v-else
                        class="p-3.5 rounded-2xl bg-amber-50/80 dark:bg-amber-950/20 border border-amber-200/90 dark:border-amber-900/40 text-xs text-amber-800 dark:text-amber-300 space-y-1.5"
                    >
                        <div class="flex items-center gap-1.5 font-bold">
                            <Clock class="w-4 h-4 text-amber-600 shrink-0" />
                            <span>Catatan Tim IT Superadmin:</span>
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                            Belum ada catatan atau tanggapan tertulis dari Tim IT Superadmin. Laporan kendala Anda telah tercatat dan sedang dalam antrean penanganan. Catatan atau solusi dari Superadmin akan langsung muncul di sini begitu tiket ditindaklanjuti.
                        </p>
                    </div>

                    <!-- FORM TINDAK LANJUT SUPERADMIN -->
                    <div v-if="isSuperadmin" class="pt-4 border-t-2 border-dashed border-slate-200 dark:border-slate-800 space-y-3">
                        <div class="flex items-center gap-2">
                            <ShieldAlert class="w-4 h-4 text-blue-600" />
                            <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">
                                Form Tindak Lanjut Tim IT Superadmin
                            </h4>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Perbarui Status Tiket:
                            </label>
                            <select
                                v-model="updateForm.status"
                                class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 shadow-xs focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="Menunggu">Menunggu</option>
                                <option value="Diproses">Diproses (Sedang ditangani)</option>
                                <option value="Selesai">Selesai (Solusi telah diberikan)</option>
                                <option value="Ditolak">Ditolak (Bukan kendala teknis / tidak valid)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Solusi / Catatan Tim IT kepada Pelapor (Akan Tampil di Akun Pengguna):
                            </label>
                            <textarea
                                v-model="updateForm.tanggapan_it"
                                rows="3"
                                placeholder="Tuliskan catatan tindak lanjut, petunjuk teknis, atau solusi pemecahan masalah yang dapat langsung dibaca oleh pelapor..."
                                class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 shadow-xs focus:ring-blue-500 focus:border-blue-500"
                            ></textarea>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="button"
                                @click="submitUpdateReport"
                                :disabled="updateForm.processing"
                                class="inline-flex items-center gap-2 px-5 py-2 text-xs font-bold rounded-xl bg-blue-600 text-white hover:bg-blue-500 shadow-xs transition disabled:opacity-50"
                            >
                                <CheckCircle2 class="w-4 h-4" />
                                <span>{{ updateForm.processing ? 'Menyimpan...' : 'Simpan Tindak Lanjut' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!isSuperadmin" class="p-4 border-t border-slate-100 dark:border-slate-800 flex justify-end bg-slate-50 dark:bg-slate-800/40">
                    <button
                        type="button"
                        @click="showDetailModal = false"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-900 text-white hover:bg-slate-800 transition"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL PRATINJAU FOTO / BUKTI LAMPIRAN                         -->
        <!-- ============================================================ -->
        <div v-if="showPhotoModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="max-w-3xl w-full bg-white dark:bg-slate-900 rounded-2xl overflow-hidden shadow-2xl border border-slate-700">
                <div class="p-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Pratinjau Berkas Lampiran</span>
                    <button @click="showPhotoModal = false" class="p-1 text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="p-4 flex items-center justify-center bg-slate-100 dark:bg-slate-950 max-h-[75vh] overflow-auto">
                    <img
                        v-if="!photoPreviewUrl.endsWith('.pdf')"
                        :src="photoPreviewUrl"
                        alt="Bukti Lampiran"
                        class="max-h-[70vh] w-auto object-contain rounded-lg shadow-md"
                    />
                    <div v-else class="text-center py-8">
                        <FileText class="w-12 h-12 text-blue-500 mx-auto mb-2" />
                        <p class="text-xs text-slate-700 dark:text-slate-300 font-semibold mb-3">Dokumen Berkas PDF</p>
                        <a
                            :href="photoPreviewUrl"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-500 transition"
                        >
                            <ExternalLink class="w-4 h-4" />
                            <span>Buka di Tab Baru</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
