<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    UserPlus,
    Clock,
    CheckCircle2,
    XCircle,
    AlertCircle,
    Search,
    Filter,
    Copy,
    Check,
    Trash2,
    Eye,
    Shield,
    Users,
    FileText,
    Calendar,
    Phone,
    Mail,
    MapPin,
    Briefcase,
    ExternalLink,
    X,
    UserCheck,
    AlertTriangle,
} from 'lucide-vue-next';

const props = defineProps({
    activeTemporaryUsers: Object,
    submissions: Object,
    stats: Object,
    seksiList: Array,
    filters: Object,
    createdCredentials: Object,
});

// Tab state: 'active' | 'submissions'
const activeTab = ref(props.filters?.tab === 'submissions' ? 'submissions' : 'active');

// Search & Filter state for submissions
const search = ref(props.filters?.search || '');
const filterStatus = ref(props.filters?.status || 'all');

const applyFilter = (newTab = activeTab.value) => {
    router.get(route('temporary-users.index'), {
        tab: newTab,
        search: search.value || undefined,
        status: filterStatus.value !== 'all' ? filterStatus.value : undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

const switchTab = (tab) => {
    activeTab.value = tab;
    applyFilter(tab);
};

const resetFilter = () => {
    search.value = '';
    filterStatus.value = 'all';
    applyFilter();
};

// Modal Buat User Sementara
const showCreateModal = ref(false);
const createForm = useForm({
    username: '',
    password: '',
    duration_type: '10', // '5' | '10' | '15' | 'custom'
    custom_minutes: 30,
});

const submitCreate = () => {
    createForm.post(route('temporary-users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

// Modal Kredensial Baru Dibuat
const showCredentialsModal = ref(false);
const activeCredentials = ref(null);
const copySuccessState = ref({});

const copyToClipboard = async (text, key = 'all') => {
    if (!text) return false;

    let success = false;

    // 1. Coba navigator.clipboard jika didukung dan secure context
    if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        try {
            await navigator.clipboard.writeText(text);
            success = true;
        } catch (e) {
            // Fallback ke execCommand di bawah
        }
    }

    // 2. Fallback universal (100% bekerja di HTTP Laragon / browser lokal / tanpa HTTPS)
    if (!success && typeof document !== 'undefined') {
        try {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            textArea.style.top = '-9999px';
            textArea.setAttribute('readonly', '');
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            textArea.setSelectionRange(0, 99999);

            success = document.execCommand('copy');
            document.body.removeChild(textArea);
        } catch (err) {
            console.error('Gagal menyalin:', err);
        }
    }

    if (success) {
        copySuccessState.value[key] = true;
        setTimeout(() => {
            copySuccessState.value[key] = false;
        }, 2000);
    }

    return success;
};

watch(() => props.createdCredentials, (newVal) => {
    if (newVal) {
        activeCredentials.value = newVal;
        showCredentialsModal.value = true;
    }
}, { immediate: true });

const copyCredentialsText = () => {
    if (!activeCredentials.value) return;
    const cred = activeCredentials.value;
    const text = `AKUN SEMENTARA PENDAFTARAN PJLP\n` +
        `Suku Dinas Kominfotik Jakarta Timur\n\n` +
        `Username: ${cred.username}\n` +
        `Password: ${cred.password}\n` +
        `Masa Aktif: ${cred.duration_label} (Kedaluwarsa: ${cred.expires_at_formatted})\n` +
        `Tautan Masuk: ${cred.login_url}\n\n` +
        `Catatan: Akun ini akan otomatis terhapus saat formulir pendaftaran berhasil dikirim atau batas waktu berakhir.`;

    copyToClipboard(text, 'all');
};

// Modal Detail & Validasi Pengajuan
const showDetailModal = ref(false);
const selectedSubmission = ref(null);
const validationMode = ref('detail'); // 'detail' | 'approve' | 'reject'

const approveForm = useForm({
    pin: '',
    seksi: '',
    posisi_pekerjaan: '',
    catatan_admin: '',
});

const rejectForm = useForm({
    catatan_admin: '',
});

const openDetailModal = (sub) => {
    selectedSubmission.value = sub;
    validationMode.value = 'detail';
    approveForm.clearErrors();
    rejectForm.clearErrors();

    approveForm.pin = sub.pin || '';
    approveForm.seksi = sub.seksi || (props.seksiList[0]?.nama || '');
    approveForm.posisi_pekerjaan = sub.posisi_pekerjaan || '';
    approveForm.catatan_admin = '';

    rejectForm.catatan_admin = '';

    showDetailModal.value = true;
};

const submitApprove = () => {
    if (!selectedSubmission.value) return;
    approveForm.post(route('temporary-users.approve', selectedSubmission.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDetailModal.value = false;
            selectedSubmission.value = null;
        },
    });
};

const submitReject = () => {
    if (!selectedSubmission.value) return;
    rejectForm.post(route('temporary-users.reject', selectedSubmission.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDetailModal.value = false;
            selectedSubmission.value = null;
        },
    });
};

// Delete / Revoke temporary user
const revokeUser = (user) => {
    if (confirm(`Yakin ingin mencabut dan menghapus akun sementara '${user.username}'? Calon pengguna tidak akan bisa masuk lagi.`)) {
        router.delete(route('temporary-users.destroy', user.id), {
            preserveScroll: true,
        });
    }
};

// Live countdown timer for active temporary users
const nowTime = ref(Date.now());
let timerInterval = null;

onMounted(() => {
    timerInterval = setInterval(() => {
        nowTime.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

const getRemainingTime = (expiresAt) => {
    if (!expiresAt) return 'Kedaluwarsa';
    const diffMs = new Date(expiresAt).getTime() - nowTime.value;
    if (diffMs <= 0) return 'Kedaluwarsa';

    const totalSeconds = Math.floor(diffMs / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    if (minutes > 60) {
        const hours = Math.floor(minutes / 60);
        const remMin = minutes % 60;
        return `${hours}j ${remMin}m`;
    }

    return `${minutes}m ${seconds.toString().padStart(2, '0')}d`;
};

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
};
</script>

<template>
    <Head title="Kelola User Sementara & Pendaftaran" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header Page -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <Users class="w-4 h-4 text-slate-400" />
                        <span>Manajemen Pengguna</span>
                        <span>/</span>
                        <span class="text-slate-800">User Sementara & Pendaftaran</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 mt-1">
                        User Sementara & Validasi Pendaftaran
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Buat akses sementara bagi calon PJLP untuk mengisi formulir pendaftaran dan validasi pengajuan mereka.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="showCreateModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-xs sm:text-sm font-semibold hover:bg-slate-800 transition shadow-sm hover:shadow"
                    >
                        <UserPlus class="w-4 h-4 text-emerald-400" />
                        <span>Buat User Sementara</span>
                    </button>
                </div>
            </div>

            <!-- Statistik KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- User Sementara Aktif -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Akun Aktif</div>
                        <div class="text-2xl font-bold text-slate-900 mt-1">{{ stats.active_temporary_users }}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Masa aktif berjalan</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                        <Clock class="w-5 h-5" />
                    </div>
                </div>

                <!-- Menunggu Validasi -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Menunggu Validasi</div>
                        <div class="text-2xl font-bold text-amber-600 mt-1">{{ stats.pending_submissions }}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Perlu ditinjau</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                        <AlertCircle class="w-5 h-5" />
                    </div>
                </div>

                <!-- Disetujui -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Disetujui</div>
                        <div class="text-2xl font-bold text-emerald-600 mt-1">{{ stats.approved_submissions }}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Akun resmi aktif</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <CheckCircle2 class="w-5 h-5" />
                    </div>
                </div>

                <!-- Total Pengajuan -->
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Pengajuan</div>
                        <div class="text-2xl font-bold text-slate-900 mt-1">{{ stats.total_submissions }}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">Formulir masuk</div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                        <FileText class="w-5 h-5" />
                    </div>
                </div>
            </div>

            <!-- Tab Navigation & Main Content Container -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <!-- Navigation Tabs -->
                <div class="flex items-center border-b border-slate-200 px-5 pt-3 gap-6 bg-slate-50/50">
                    <button
                        @click="switchTab('active')"
                        class="pb-3 text-xs sm:text-sm font-semibold flex items-center gap-2 border-b-2 transition relative"
                        :class="activeTab === 'active'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-800'"
                    >
                        <Clock class="w-4 h-4" />
                        <span>Akun User Sementara Aktif</span>
                        <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
                            :class="activeTab === 'active' ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-700'">
                            {{ stats.active_temporary_users }}
                        </span>
                    </button>

                    <button
                        @click="switchTab('submissions')"
                        class="pb-3 text-xs sm:text-sm font-semibold flex items-center gap-2 border-b-2 transition relative"
                        :class="activeTab === 'submissions'
                            ? 'border-slate-900 text-slate-900'
                            : 'border-transparent text-slate-500 hover:text-slate-800'"
                    >
                        <FileText class="w-4 h-4" />
                        <span>Pengajuan Pendaftaran Masuk</span>
                        <span v-if="stats.pending_submissions > 0" class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-amber-500 text-white">
                            {{ stats.pending_submissions }}
                        </span>
                        <span v-else class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-slate-200 text-slate-700">
                            {{ stats.total_submissions }}
                        </span>
                    </button>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 1: AKUN USER SEMENTARA AKTIF -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'active'" class="p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                        <div class="text-xs text-slate-500">
                            Daftar akun sementara yang saat ini masih memiliki hak akses untuk masuk dan mengisi formulir.
                        </div>
                    </div>

                    <!-- Tabel Akun Sementara -->
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[11px] font-bold text-slate-600 uppercase tracking-wider border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Username Akun</th>
                                    <th class="px-4 py-3">Waktu Pembuatan</th>
                                    <th class="px-4 py-3">Batas Kedaluwarsa</th>
                                    <th class="px-4 py-3">Sisa Waktu</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="user in activeTemporaryUsers.data" :key="user.id" class="hover:bg-slate-50/70 transition">
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center font-mono font-bold text-xs">
                                                {{ user.username.slice(0, 2).toUpperCase() }}
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-mono font-semibold text-slate-900">{{ user.username }}</span>
                                                    <button
                                                        type="button"
                                                        @click="copyToClipboard(user.username, 'table_' + user.id)"
                                                        class="text-slate-400 hover:text-slate-700 p-0.5 rounded transition"
                                                        :title="copySuccessState['table_' + user.id] ? 'Tersalin!' : 'Salin Username'"
                                                    >
                                                        <Check v-if="copySuccessState['table_' + user.id]" class="w-3.5 h-3.5 text-emerald-500" />
                                                        <Copy v-else class="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                                <div class="text-[10px] text-slate-400">ID: #{{ user.id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600">
                                        {{ formatDateTime(user.created_at) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600">
                                        {{ formatDateTime(user.temporary_expires_at) }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-mono font-semibold"
                                            :class="getRemainingTime(user.temporary_expires_at) === 'Kedaluwarsa'
                                                ? 'bg-rose-100 text-rose-700'
                                                : 'bg-emerald-50 text-emerald-700 border border-emerald-200'">
                                            <Clock class="w-3 h-3" />
                                            {{ getRemainingTime(user.temporary_expires_at) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <button
                                            @click="revokeUser(user)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-rose-600 hover:bg-rose-50 border border-rose-200 hover:border-rose-300 font-medium transition"
                                            title="Cabut dan hapus akun sekarang"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" />
                                            <span>Cabut Akses</span>
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="!activeTemporaryUsers.data || activeTemporaryUsers.data.length === 0">
                                    <td colspan="5" class="py-12 text-center text-slate-400">
                                        <Clock class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                        <div class="font-medium text-slate-600">Tidak ada akun sementara yang sedang aktif.</div>
                                        <div class="text-xs text-slate-400 mt-1">Klik tombol "Buat User Sementara" di atas untuk membuat kredensial baru.</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="activeTemporaryUsers.links && activeTemporaryUsers.links.length > 3" class="mt-4 flex items-center justify-between text-xs text-slate-500">
                        <div>Menampilkan {{ activeTemporaryUsers.from || 0 }} - {{ activeTemporaryUsers.to || 0 }} dari {{ activeTemporaryUsers.total }} akun</div>
                        <div class="flex items-center gap-1">
                            <button
                                v-for="(link, i) in activeTemporaryUsers.links"
                                :key="i"
                                @click="link.url && router.visit(link.url, { preserveState: true })"
                                :disabled="!link.url || link.active"
                                v-html="link.label"
                                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                                :class="link.active
                                    ? 'bg-slate-900 text-white border-slate-900 font-bold'
                                    : (link.url ? 'hover:bg-slate-100 border-slate-200 text-slate-700' : 'opacity-40 cursor-not-allowed border-slate-200 text-slate-400')"
                            />
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- TAB 2: PENGAJUAN PENDAFTARAN -->
                <!-- ======================================================== -->
                <div v-if="activeTab === 'submissions'" class="p-5">
                    <!-- Filter & Search Toolbar -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
                        <!-- Status Pills -->
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                            <button
                                @click="filterStatus = 'all'; applyFilter('submissions')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                                :class="filterStatus === 'all'
                                    ? 'bg-slate-900 text-white shadow-xs'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            >
                                Semua ({{ stats.total_submissions }})
                            </button>
                            <button
                                @click="filterStatus = 'pending'; applyFilter('submissions')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
                                :class="filterStatus === 'pending'
                                    ? 'bg-amber-500 text-white shadow-xs'
                                    : 'bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200/60'"
                            >
                                <span>Menunggu</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-white/20">
                                    {{ stats.pending_submissions }}
                                </span>
                            </button>
                            <button
                                @click="filterStatus = 'approved'; applyFilter('submissions')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
                                :class="filterStatus === 'approved'
                                    ? 'bg-emerald-600 text-white shadow-xs'
                                    : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200/60'"
                            >
                                <span>Disetujui</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-white/20">
                                    {{ stats.approved_submissions }}
                                </span>
                            </button>
                            <button
                                @click="filterStatus = 'rejected'; applyFilter('submissions')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
                                :class="filterStatus === 'rejected'
                                    ? 'bg-rose-600 text-white shadow-xs'
                                    : 'bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200/60'"
                            >
                                <span>Ditolak</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-white/20">
                                    {{ stats.rejected_submissions }}
                                </span>
                            </button>
                        </div>

                        <!-- Search Input -->
                        <div class="relative w-full md:w-80">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input
                                v-model="search"
                                @keyup.enter="applyFilter('submissions')"
                                type="text"
                                placeholder="Cari nama, kode, NIK, seksi..."
                                class="w-full pl-9 pr-8 py-1.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-slate-900 focus:border-slate-900 transition"
                            />
                            <button
                                v-if="search"
                                @click="search = ''; applyFilter('submissions')"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Tabel Pengajuan -->
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[11px] font-bold text-slate-600 uppercase tracking-wider border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Kode Registrasi</th>
                                    <th class="px-4 py-3">Pelamar & Posisi</th>
                                    <th class="px-4 py-3">Seksi Penempatan</th>
                                    <th class="px-4 py-3">Kontak</th>
                                    <th class="px-4 py-3">Waktu Masuk</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="sub in submissions.data" :key="sub.id" class="hover:bg-slate-50/70 transition">
                                    <td class="px-4 py-3.5">
                                        <div class="font-mono font-bold text-slate-900">{{ sub.registration_code }}</div>
                                        <div class="text-[10px] text-slate-400">Username: {{ sub.username }}</div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-slate-900">{{ sub.nama_lengkap }}</div>
                                        <div class="text-[11px] text-slate-500">{{ sub.posisi_pekerjaan || '-' }}</div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ sub.seksi || '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600">
                                        <div v-if="sub.no_wa" class="flex items-center gap-1 text-[11px]">
                                            <Phone class="w-3 h-3 text-emerald-500" />
                                            <span>{{ sub.no_wa }}</span>
                                        </div>
                                        <div v-if="sub.email" class="flex items-center gap-1 text-[11px] text-slate-400 mt-0.5">
                                            <Mail class="w-3 h-3" />
                                            <span>{{ sub.email }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 text-slate-600 whitespace-nowrap">
                                        {{ formatDateTime(sub.created_at) }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span v-if="sub.status === 'pending'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                            <Clock class="w-3 h-3" />
                                            Menunggu Validasi
                                        </span>
                                        <span v-else-if="sub.status === 'approved'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                            <CheckCircle2 class="w-3 h-3" />
                                            Disetujui
                                        </span>
                                        <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-800 border border-rose-200">
                                            <XCircle class="w-3 h-3" />
                                            Ditolak
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <button
                                            @click="openDetailModal(sub)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                                            :class="sub.status === 'pending'
                                                ? 'bg-slate-900 text-white hover:bg-slate-800 shadow-xs'
                                                : 'border border-slate-200 text-slate-700 hover:bg-slate-100'"
                                        >
                                            <Eye class="w-3.5 h-3.5" />
                                            <span>{{ sub.status === 'pending' ? 'Validasi' : 'Detail' }}</span>
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="!submissions.data || submissions.data.length === 0">
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        <FileText class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                        <div class="font-medium text-slate-600">Tidak ada pengajuan pendaftaran ditemukan.</div>
                                        <div class="text-xs text-slate-400 mt-1">Formulir yang dikirim calon pengguna akan muncul di sini.</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="submissions.links && submissions.links.length > 3" class="mt-4 flex items-center justify-between text-xs text-slate-500">
                        <div>Menampilkan {{ submissions.from || 0 }} - {{ submissions.to || 0 }} dari {{ submissions.total }} pengajuan</div>
                        <div class="flex items-center gap-1">
                            <button
                                v-for="(link, i) in submissions.links"
                                :key="i"
                                @click="link.url && router.visit(link.url, { preserveState: true })"
                                :disabled="!link.url || link.active"
                                v-html="link.label"
                                class="px-3 py-1.5 rounded-lg border text-xs font-medium transition"
                                :class="link.active
                                    ? 'bg-slate-900 text-white border-slate-900 font-bold'
                                    : (link.url ? 'hover:bg-slate-100 border-slate-200 text-slate-700' : 'opacity-40 cursor-not-allowed border-slate-200 text-slate-400')"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL: BUAT USER SEMENTARA -->
        <!-- ============================================================ -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center">
                            <UserPlus class="w-4 h-4 text-emerald-400" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Buat Akun User Sementara</h3>
                            <p class="text-[11px] text-slate-500">Beri akses terbatas untuk mengisi formulir pendaftaran</p>
                        </div>
                    </div>
                    <button @click="showCreateModal = false" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                    <!-- Durasi Masa Aktif -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">
                            Pilih Durasi Masa Aktif Akun <span class="text-rose-500">*</span>
                        </label>
                        <div class="grid grid-cols-4 gap-2">
                            <button
                                type="button"
                                @click="createForm.duration_type = '5'"
                                class="py-2.5 px-3 rounded-xl text-xs font-semibold border transition text-center"
                                :class="createForm.duration_type === '5'
                                    ? 'bg-slate-900 text-white border-slate-900 shadow-xs'
                                    : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                            >
                                5 Menit
                            </button>
                            <button
                                type="button"
                                @click="createForm.duration_type = '10'"
                                class="py-2.5 px-3 rounded-xl text-xs font-semibold border transition text-center"
                                :class="createForm.duration_type === '10'
                                    ? 'bg-slate-900 text-white border-slate-900 shadow-xs'
                                    : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                            >
                                10 Menit
                            </button>
                            <button
                                type="button"
                                @click="createForm.duration_type = '15'"
                                class="py-2.5 px-3 rounded-xl text-xs font-semibold border transition text-center"
                                :class="createForm.duration_type === '15'
                                    ? 'bg-slate-900 text-white border-slate-900 shadow-xs'
                                    : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                            >
                                15 Menit
                            </button>
                            <button
                                type="button"
                                @click="createForm.duration_type = 'custom'"
                                class="py-2.5 px-3 rounded-xl text-xs font-semibold border transition text-center"
                                :class="createForm.duration_type === 'custom'
                                    ? 'bg-slate-900 text-white border-slate-900 shadow-xs'
                                    : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                            >
                                Manual
                            </button>
                        </div>
                    </div>

                    <!-- Input Kustom Menit (Jika memilih manual) -->
                    <div v-if="createForm.duration_type === 'custom'" class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1">
                        <label class="block text-xs font-medium text-slate-700">
                            Masukkan Durasi Waktu (Menit):
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                v-model.number="createForm.custom_minutes"
                                type="number"
                                min="1"
                                max="1440"
                                class="w-32 px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-mono font-bold focus:ring-2 focus:ring-slate-900"
                            />
                            <span class="text-xs text-slate-500">menit (maksimal 1440 menit / 24 jam)</span>
                        </div>
                        <p v-if="createForm.errors.custom_minutes" class="text-[11px] text-rose-500">
                            {{ createForm.errors.custom_minutes }}
                        </p>
                    </div>

                    <!-- Kredensial Kustom (Opsional) -->
                    <div class="pt-2 border-t border-slate-100 space-y-3">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                            Kredensial Akun (Opsional)
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Username (Kosongkan untuk otomatis sistem e.g. <span class="font-mono text-slate-500">tamu_xxxxx</span>)
                            </label>
                            <input
                                v-model="createForm.username"
                                type="text"
                                placeholder="Otomatis digenerate jika kosong"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-slate-900"
                            />
                            <p v-if="createForm.errors.username" class="text-[11px] text-rose-500 mt-1">
                                {{ createForm.errors.username }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Password (Kosongkan untuk otomatis sistem e.g. <span class="font-mono text-slate-500">TamuXXXX</span>)
                            </label>
                            <input
                                v-model="createForm.password"
                                type="text"
                                placeholder="Otomatis digenerate jika kosong"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-slate-900"
                            />
                            <p v-if="createForm.errors.password" class="text-[11px] text-rose-500 mt-1">
                                {{ createForm.errors.password }}
                            </p>
                        </div>
                    </div>

                    <!-- Penjelasan Sistem -->
                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-800 text-[11px] leading-relaxed flex items-start gap-2">
                        <AlertTriangle class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                        <div>
                            <strong>Penting:</strong> Akun ini dirancang khusus untuk pendaftaran. Begitu calon pegawai mengirimkan formulir, akun sementara ini akan <strong>langsung terhapus</strong> secara otomatis dari database sistem.
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-medium hover:bg-slate-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="px-5 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 disabled:opacity-50 transition shadow-sm"
                        >
                            {{ createForm.processing ? 'Membuat Akun...' : 'Terbitkan Akun Sementara' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL: KREDENSIAL BARU DIBUAT (HASIL GENERATE) -->
        <!-- ============================================================ -->
        <div v-if="showCredentialsModal && activeCredentials" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-6 text-center border-b border-slate-100">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto mb-3 shadow-xs">
                        <CheckCircle2 class="w-6 h-6" />
                    </div>
                    <h3 class="text-base font-bold text-slate-900">Akun Sementara Berhasil Diterbitkan!</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Salin dan berikan kredensial login di bawah ini kepada calon pegawai.
                    </p>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Kartu Kredensial -->
                    <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 space-y-3 font-mono text-xs">
                        <!-- Username Row -->
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-200">
                            <div>
                                <span class="text-slate-500 text-[11px] block font-sans">Username:</span>
                                <span class="font-bold text-slate-900 text-sm select-all">{{ activeCredentials.username }}</span>
                            </div>
                            <button
                                type="button"
                                @click="copyToClipboard(activeCredentials.username, 'username')"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border text-[11px] font-sans font-medium transition"
                                :class="copySuccessState.username
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                                <Check v-if="copySuccessState.username" class="w-3.5 h-3.5 text-emerald-600" />
                                <Copy v-else class="w-3.5 h-3.5" />
                                <span>{{ copySuccessState.username ? 'Tersalin' : 'Salin' }}</span>
                            </button>
                        </div>

                        <!-- Password Row -->
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-200">
                            <div>
                                <span class="text-slate-500 text-[11px] block font-sans">Password:</span>
                                <span class="font-bold text-emerald-700 text-sm select-all">{{ activeCredentials.password }}</span>
                            </div>
                            <button
                                type="button"
                                @click="copyToClipboard(activeCredentials.password, 'password')"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border text-[11px] font-sans font-medium transition"
                                :class="copySuccessState.password
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                                <Check v-if="copySuccessState.password" class="w-3.5 h-3.5 text-emerald-600" />
                                <Copy v-else class="w-3.5 h-3.5" />
                                <span>{{ copySuccessState.password ? 'Tersalin' : 'Salin' }}</span>
                            </button>
                        </div>

                        <!-- Masa Aktif Row -->
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-200 font-sans">
                            <span class="text-slate-500">Masa Aktif:</span>
                            <span class="font-semibold text-slate-800">{{ activeCredentials.duration_label }}</span>
                        </div>

                        <!-- Kedaluwarsa Row -->
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-200 font-sans">
                            <span class="text-slate-500">Kedaluwarsa:</span>
                            <span class="text-rose-600 font-semibold">{{ activeCredentials.expires_at_formatted }}</span>
                        </div>

                        <!-- Tautan Masuk Row -->
                        <div class="flex items-center justify-between pt-0.5">
                            <div class="truncate mr-2">
                                <span class="text-slate-500 text-[11px] block font-sans">Tautan Masuk:</span>
                                <span class="text-slate-700 text-[11px] select-all truncate block">{{ activeCredentials.login_url }}</span>
                            </div>
                            <button
                                type="button"
                                @click="copyToClipboard(activeCredentials.login_url, 'url')"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border text-[11px] font-sans font-medium transition flex-shrink-0"
                                :class="copySuccessState.url
                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                            >
                                <Check v-if="copySuccessState.url" class="w-3.5 h-3.5 text-emerald-600" />
                                <Copy v-else class="w-3.5 h-3.5" />
                                <span>{{ copySuccessState.url ? 'Tersalin' : 'Salin' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Tombol Salin Kredensial Lengkap -->
                    <button
                        type="button"
                        @click="copyCredentialsText"
                        class="w-full py-2.5 px-4 rounded-xl bg-slate-900 text-white text-xs font-semibold flex items-center justify-center gap-2 hover:bg-slate-800 transition shadow-sm"
                    >
                        <Check v-if="copySuccessState.all" class="w-4 h-4 text-emerald-400" />
                        <Copy v-else class="w-4 h-4" />
                        <span>{{ copySuccessState.all ? 'Semua Kredensial Berhasil Disalin!' : 'Salin Kredensial Lengkap' }}</span>
                    </button>

                    <button
                        type="button"
                        @click="showCredentialsModal = false"
                        class="w-full py-2 text-center text-xs font-medium text-slate-500 hover:text-slate-800"
                    >
                        Tutup Jendela Ini
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL: DETAIL & VALIDASI PENGAJUAN -->
        <!-- ============================================================ -->
        <div v-if="showDetailModal && selectedSubmission" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden max-h-[90vh] flex flex-col animate-in fade-in zoom-in-95 duration-150">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/50 flex-shrink-0">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-slate-900 text-sm">{{ selectedSubmission.registration_code }}</span>
                            <span v-if="selectedSubmission.status === 'pending'" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                Menunggu Validasi
                            </span>
                            <span v-else-if="selectedSubmission.status === 'approved'" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                Disetujui
                            </span>
                            <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-800 border border-rose-200">
                                Ditolak
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Pengajuan Formulir Pendaftaran Calon Pegawai PJLP</p>
                    </div>
                    <button @click="showDetailModal = false" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <!-- Modal Body (Scrollable) -->
                <div class="p-6 overflow-y-auto space-y-5 text-xs flex-1 custom-scrollbar">
                    <!-- Bagian 1: Akun Resmi yang Diajukan -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <div class="text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <Shield class="w-3.5 h-3.5 text-slate-500" />
                            <span>Kredensial Akun yang Diminta</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <span class="text-slate-400">Username Diajukan:</span>
                                <div class="font-mono font-bold text-slate-900 text-sm mt-0.5">{{ selectedSubmission.username }}</div>
                            </div>
                            <div>
                                <span class="text-slate-400">Kata Sandi:</span>
                                <div class="font-semibold text-emerald-700 mt-0.5">Telah diamankan / terenkripsi</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian 2: Biodata Calon Pegawai -->
                    <div>
                        <div class="text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                            <Briefcase class="w-3.5 h-3.5 text-slate-500" />
                            <span>Identitas Pelamar</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-white p-3.5 rounded-xl border border-slate-100">
                            <div>
                                <span class="text-slate-400">Nama Lengkap:</span>
                                <div class="font-semibold text-slate-900 mt-0.5">{{ selectedSubmission.nama_lengkap }}</div>
                            </div>
                            <div>
                                <span class="text-slate-400">NIK:</span>
                                <div class="font-mono font-medium text-slate-800 mt-0.5">{{ selectedSubmission.nik || '-' }}</div>
                            </div>
                            <div>
                                <span class="text-slate-400">Jenis Kelamin:</span>
                                <div class="font-medium text-slate-800 mt-0.5">{{ selectedSubmission.jenis_kelamin || '-' }}</div>
                            </div>
                            <div>
                                <span class="text-slate-400">Tempat, Tgl Lahir:</span>
                                <div class="font-medium text-slate-800 mt-0.5">
                                    {{ selectedSubmission.tempat_lahir || '-' }}, {{ selectedSubmission.tanggal_lahir ? formatDateTime(selectedSubmission.tanggal_lahir).split(',')[0] : '-' }}
                                </div>
                            </div>
                            <div>
                                <span class="text-slate-400">Status Pernikahan:</span>
                                <div class="font-medium text-slate-800 mt-0.5">{{ selectedSubmission.status_pernikahan || '-' }}</div>
                            </div>
                            <div>
                                <span class="text-slate-400">Pendidikan Terakhir:</span>
                                <div class="font-medium text-slate-800 mt-0.5">{{ selectedSubmission.pendidikan_terakhir || '-' }}</div>
                            </div>
                            <div class="col-span-2 sm:col-span-3 pt-2 border-t border-slate-100">
                                <span class="text-slate-400">Alamat Domisili:</span>
                                <div class="text-slate-800 mt-0.5">{{ selectedSubmission.alamat_domisili || '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian 3: Penempatan & Kontak -->
                    <div>
                        <div class="text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                            <MapPin class="w-3.5 h-3.5 text-slate-500" />
                            <span>Penempatan & Kontak</span>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-white p-3.5 rounded-xl border border-slate-100">
                            <div>
                                <span class="text-slate-400">Seksi Pilihan:</span>
                                <div class="font-semibold text-slate-900 mt-0.5">{{ selectedSubmission.seksi || '-' }}</div>
                            </div>
                            <div>
                                <span class="text-slate-400">Posisi / Jabatan:</span>
                                <div class="font-semibold text-slate-900 mt-0.5">{{ selectedSubmission.posisi_pekerjaan || '-' }}</div>
                            </div>
                            <div>
                                <span class="text-slate-400">No. WhatsApp / HP:</span>
                                <div class="font-mono text-emerald-700 font-semibold mt-0.5">{{ selectedSubmission.no_wa || '-' }}</div>
                            </div>
                            <div class="col-span-2 sm:col-span-3">
                                <span class="text-slate-400">Email:</span>
                                <div class="text-slate-800 mt-0.5">{{ selectedSubmission.email || '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bagian 4: Riwayat Status & Catatan Jika Sudah Diproses -->
                    <div v-if="selectedSubmission.status !== 'pending'" class="p-4 rounded-xl border"
                        :class="selectedSubmission.status === 'approved' ? 'bg-emerald-50/50 border-emerald-200' : 'bg-rose-50/50 border-rose-200'">
                        <div class="font-bold text-xs mb-1" :class="selectedSubmission.status === 'approved' ? 'text-emerald-900' : 'text-rose-900'">
                            {{ selectedSubmission.status === 'approved' ? 'Pengajuan Ini Telah Disetujui' : 'Pengajuan Ini Telah Ditolak' }}
                        </div>
                        <div class="text-[11px] text-slate-600 space-y-1">
                            <div>Divalidasi oleh: <strong>{{ selectedSubmission.approver?.nama_lengkap || 'Superadmin' }}</strong> pada {{ formatDateTime(selectedSubmission.approved_at) }}</div>
                            <div v-if="selectedSubmission.catatan_admin">
                                Catatan Admin: <em>"{{ selectedSubmission.catatan_admin }}"</em>
                            </div>
                            <div v-if="selectedSubmission.registered_user">
                                Akun PJLP resmi aktif: <strong>{{ selectedSubmission.registered_user.username }}</strong> (PIN: {{ selectedSubmission.registered_user.pin || '-' }})
                            </div>
                        </div>
                    </div>

                    <!-- Bagian 5: FORM VALIDASI (Hanya jika Status === 'pending') -->
                    <div v-if="selectedSubmission.status === 'pending'" class="border-t border-slate-200 pt-4">
                        <!-- Mode Tabs: Approve vs Reject -->
                        <div class="flex items-center gap-2 mb-4">
                            <button
                                type="button"
                                @click="validationMode = 'approve'"
                                class="flex-1 py-2 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 transition"
                                :class="validationMode === 'approve'
                                    ? 'bg-emerald-600 text-white shadow-xs'
                                    : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100'"
                            >
                                <CheckCircle2 class="w-4 h-4" />
                                <span>Setujui & Daftarkan PJLP</span>
                            </button>
                            <button
                                type="button"
                                @click="validationMode = 'reject'"
                                class="flex-1 py-2 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 transition"
                                :class="validationMode === 'reject'
                                    ? 'bg-rose-600 text-white shadow-xs'
                                    : 'bg-rose-50 text-rose-800 border border-rose-200 hover:bg-rose-100'"
                            >
                                <XCircle class="w-4 h-4" />
                                <span>Tolak Pengajuan</span>
                            </button>
                        </div>

                        <!-- Form Validasi: Setujui -->
                        <form v-if="validationMode === 'approve'" @submit.prevent="submitApprove" class="space-y-3 bg-emerald-50/40 p-4 rounded-xl border border-emerald-200">
                            <div class="font-bold text-xs text-emerald-900 mb-1">Konfirmasi Persetujuan & Penempatan Resmi</div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                        Seksi Penempatan <span class="text-rose-500">*</span>
                                    </label>
                                    <select
                                        v-model="approveForm.seksi"
                                        class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                    >
                                        <option v-for="s in seksiList" :key="s.id" :value="s.nama">
                                            {{ s.nama }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                        Posisi / Jabatan Pekerjaan <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        v-model="approveForm.posisi_pekerjaan"
                                        type="text"
                                        class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                    PIN Fingerprint Mesin Absensi (Opsional)
                                </label>
                                <input
                                    v-model="approveForm.pin"
                                    type="text"
                                    placeholder="Contoh: 80790658 (dapat diisi nanti)"
                                    class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-mono focus:ring-2 focus:ring-slate-900"
                                />
                                <span class="text-[10px] text-slate-500">PIN mesin ini akan digunakan untuk sinkronisasi otomatis absensi fingerprint.</span>
                            </div>

                            <div>
                                <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                    Catatan Tambahan (Opsional)
                                </label>
                                <textarea
                                    v-model="approveForm.catatan_admin"
                                    rows="2"
                                    placeholder="Catatan verifikasi berkas..."
                                    class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                ></textarea>
                            </div>

                            <button
                                type="submit"
                                :disabled="approveForm.processing"
                                class="w-full py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition shadow-sm"
                            >
                                {{ approveForm.processing ? 'Memproses Penerbitan Akun...' : 'Konfirmasi: Setujui & Terbitkan Akun PJLP Resmi' }}
                            </button>
                        </form>

                        <!-- Form Validasi: Tolak -->
                        <form v-if="validationMode === 'reject'" @submit.prevent="submitReject" class="space-y-3 bg-rose-50/40 p-4 rounded-xl border border-rose-200">
                            <div class="font-bold text-xs text-rose-900 mb-1">Konfirmasi Penolakan Pengajuan</div>

                            <div>
                                <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                    Alasan Penolakan <span class="text-rose-500">*</span>
                                </label>
                                <textarea
                                    v-model="rejectForm.catatan_admin"
                                    rows="3"
                                    required
                                    placeholder="Contoh: Berkas NIK tidak valid, kuota formasi seksi penuh, dsb..."
                                    class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:ring-2 focus:ring-rose-500"
                                ></textarea>
                                <p v-if="rejectForm.errors.catatan_admin" class="text-[11px] text-rose-500 mt-1">
                                    {{ rejectForm.errors.catatan_admin }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="rejectForm.processing"
                                class="w-full py-2.5 rounded-xl bg-rose-600 text-white font-bold text-xs hover:bg-rose-700 transition shadow-sm"
                            >
                                {{ rejectForm.processing ? 'Menolak Pengajuan...' : 'Konfirmasi: Tolak Formulir Pengajuan Ini' }}
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-3 border-t border-slate-200 bg-slate-50/50 flex items-center justify-end flex-shrink-0">
                    <button
                        type="button"
                        @click="showDetailModal = false"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-100 transition"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
