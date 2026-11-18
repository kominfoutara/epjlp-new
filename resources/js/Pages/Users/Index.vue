<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    users: Object,
    seksiList: Array,
    filters: Object,
    roles: Array,
});

// Filters
const search = ref(props.filters.search || '');
const filterRole = ref(props.filters.role || '');
const filterSeksi = ref(props.filters.seksi || '');
const filterStatus = ref(props.filters.status || '');

// Modals & Drawer
const showModal = ref(false);
const editingUser = ref(null);
const showPasswordModal = ref(false);
const targetUserForPassword = ref(null);

// Offcanvas Drawer (Sidebar Detail & Edit)
const showUserDrawer = ref(false);
const drawerTab = ref('detail'); // 'detail' | 'edit'
const selectedUser = ref(null);
const openDropdownUserId = ref(null);

const toggleActionDropdown = (userId, event) => {
    if (event) event.stopPropagation();
    openDropdownUserId.value = openDropdownUserId.value === userId ? null : userId;
};

const closeActionDropdown = () => {
    openDropdownUserId.value = null;
};

// Form Tambah / Edit User
const form = useForm({
    nama_lengkap: '',
    username: '',
    email: '',
    password: '',
    role: 'PJLP',
    seksi: props.seksiList[0]?.nama || 'Astik',
    nik: '',
    no_wa: '',
    jabatan: '',
    nrk: '',
    pin: '',
    posisi_pekerjaan: '',
    jam_masuk: '07:30',
    jam_pulang: '16:00',
    status_user: 'Aktif',
    jenis_kelamin: '',
    status_pernikahan: '',
    pendidikan_terakhir: '',
    tempat_lahir: '',
    tanggal_lahir: '',
    mulai_bekerja: '',
    nama_instansi: 'Sudin Kominfotik Jakarta Barat',
});

// Form Reset Password
const passwordForm = useForm({
    password: '',
});

const applyFilter = () => {
    router.get(route('users.index'), {
        search: search.value || undefined,
        role: filterRole.value || undefined,
        seksi: filterSeksi.value || undefined,
        status: filterStatus.value || undefined,
    }, { preserveState: true, replace: true });
};

const resetFilter = () => {
    search.value = '';
    filterRole.value = '';
    filterSeksi.value = '';
    filterStatus.value = '';
    applyFilter();
};

const openUserDrawer = (u, tab = 'detail') => {
    selectedUser.value = u;
    drawerTab.value = tab;
    openDropdownUserId.value = null;

    editingUser.value = u;
    form.clearErrors();
    form.nama_lengkap = u.nama_lengkap || u.name || '';
    form.username = u.username || '';
    form.email = u.email || '';
    form.password = '';
    form.role = u.role || 'PJLP';
    form.seksi = u.seksi ? formatSeksi(u.seksi) : (props.seksiList[0]?.nama || 'Astik');
    form.nik = u.nik || '';
    form.no_wa = u.no_wa || '';
    form.jabatan = u.jabatan || '';
    form.nrk = u.nrk || '';
    form.pin = u.pin || '';
    form.posisi_pekerjaan = u.posisi_pekerjaan || '';
    form.jam_masuk = u.jam_masuk ? u.jam_masuk.slice(0, 5) : '07:30';
    form.jam_pulang = u.jam_pulang ? u.jam_pulang.slice(0, 5) : '16:00';
    form.status_user = u.status_user || 'Aktif';
    form.jenis_kelamin = u.jenis_kelamin || '';
    form.status_pernikahan = u.status_pernikahan || '';
    form.pendidikan_terakhir = u.pendidikan_terakhir || '';
    form.tempat_lahir = u.tempat_lahir || '';
    form.tanggal_lahir = u.tanggal_lahir ? u.tanggal_lahir.slice(0, 10) : '';
    form.mulai_bekerja = u.mulai_bekerja ? u.mulai_bekerja.slice(0, 10) : '';
    form.nama_instansi = u.nama_instansi || 'Sudin Kominfotik Jakarta Barat';

    showUserDrawer.value = true;
};

const closeUserDrawer = () => {
    showUserDrawer.value = false;
    selectedUser.value = null;
};

const switchDrawerTab = (tab) => {
    drawerTab.value = tab;
};

const openAddModal = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'PJLP';
    form.seksi = props.seksiList[0]?.nama || 'Astik';
    form.jam_masuk = '07:30';
    form.jam_pulang = '16:00';
    form.status_user = 'Aktif';
    form.jenis_kelamin = '';
    form.status_pernikahan = '';
    form.pendidikan_terakhir = '';
    form.tempat_lahir = '';
    form.tanggal_lahir = '';
    form.mulai_bekerja = '';
    form.nama_instansi = 'Sudin Kominfotik Jakarta Barat';
    showModal.value = true;
};

const openEditModal = (u) => {
    openUserDrawer(u, 'edit');
};

const submitForm = () => {
    if (editingUser.value) {
        form.put(route('users.update', editingUser.value.id), {
            onSuccess: () => {
                showModal.value = false;
                if (selectedUser.value && selectedUser.value.id === editingUser.value.id) {
                    Object.assign(selectedUser.value, {
                        nama_lengkap: form.nama_lengkap,
                        name: form.nama_lengkap,
                        username: form.username,
                        email: form.email,
                        role: form.role,
                        seksi: form.seksi,
                        nik: form.nik,
                        no_wa: form.no_wa,
                        jabatan: form.jabatan,
                        nrk: form.nrk,
                        pin: form.pin,
                        posisi_pekerjaan: form.posisi_pekerjaan,
                        jam_masuk: form.jam_masuk,
                        jam_pulang: form.jam_pulang,
                        status_user: form.status_user,
                        jenis_kelamin: form.jenis_kelamin,
                        status_pernikahan: form.status_pernikahan,
                        pendidikan_terakhir: form.pendidikan_terakhir,
                        tempat_lahir: form.tempat_lahir,
                        tanggal_lahir: form.tanggal_lahir,
                        mulai_bekerja: form.mulai_bekerja,
                        nama_instansi: form.nama_instansi,
                    });
                    drawerTab.value = 'detail';
                }
            }
        });
    } else {
        form.post(route('users.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    try {
        const d = new Date(dateString);
        if (isNaN(d.getTime())) return dateString;
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    } catch {
        return dateString;
    }
};

const openResetPasswordModal = (u) => {
    targetUserForPassword.value = u;
    passwordForm.reset();
    passwordForm.clearErrors();
    showPasswordModal.value = true;
};

const submitResetPassword = () => {
    if (!targetUserForPassword.value) return;

    passwordForm.post(route('users.reset-password', targetUserForPassword.value.id), {
        onSuccess: () => {
            showPasswordModal.value = false;
            passwordForm.reset();
        }
    });
};

const toggleUserStatus = (u) => {
    const action = (u.status_user === 'Aktif') ? 'menonaktifkan (suspend)' : 'mengaktifkan kembali';
    if (confirm(`Apakah Anda yakin ingin ${action} akun "${u.nama_lengkap}"?`)) {
        router.patch(route('users.toggle-status', u.id), {}, { preserveScroll: true });
    }
};

const deleteUser = (u) => {
    if (confirm(`Peringatan: Akun "${u.nama_lengkap}" (${u.role}) akan dihapus. Lanjutkan?`)) {
        router.delete(route('users.destroy', u.id), { preserveScroll: true });
    }
};

// Role Badge Styling Helper
const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'Superadmin':
            return 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 border-slate-900 dark:border-slate-100 font-bold';
        case 'Kasudin':
            return 'bg-slate-200 text-slate-800 dark:bg-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-700 font-semibold';
        case 'Kasi':
            return 'bg-slate-100 text-slate-800 dark:bg-slate-800/80 dark:text-slate-200 border-slate-300 dark:border-slate-700 font-medium';
        case 'PJLP':
        default:
            return 'bg-slate-50 text-slate-700 dark:bg-slate-800/50 dark:text-slate-300 border-slate-200 dark:border-slate-700 font-medium';
    }
};

// Seksi Formatter Helper (Astik, KIP, ID)
const formatSeksi = (val) => {
    if (!val) return 'Pimpinan';
    const s = String(val).trim();
    if (/^seksi\s+astik$/i.test(s) || /^astik$/i.test(s)) return 'Astik';
    if (/^seksi\s+kip$/i.test(s) || /^kip$/i.test(s)) return 'KIP';
    if (/^seksi\s+id$/i.test(s) || /^id$/i.test(s)) return 'ID';
    if (/^subbagian\s+tata\s+usaha$/i.test(s) || /^tata\s+usaha$/i.test(s) || /^tu$/i.test(s)) return 'Tata Usaha';
    if (/^pimpinan$/i.test(s)) return 'Pimpinan';
    return s.replace(/^Seksi\s+/i, '');
};
</script>

<template>
    <Head title="Kelola Pengguna - EPJLP" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            Manajemen Pengguna
                        </h2>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                            Master Directory
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Kelola direktori seluruh akun aparatur dan tenaga kerja PJLP Suku Dinas Kominfotik
                    </p>
                </div>
                <div>
                    <button @click="openAddModal"
                        type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 font-semibold text-xs shadow-xs transition active:scale-95">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        <span>Tambah Pengguna</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-4">
            <!-- Filter Toolbar -->
            <div class="bg-white dark:bg-slate-900 rounded-xl p-4 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                    <!-- Search Input -->
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input v-model="search"
                            @keyup.enter="applyFilter"
                            type="text"
                            placeholder="Cari nama, NIK, PIN, username..."
                            class="block w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500 transition" />
                    </div>

                    <!-- Role Filter -->
                    <div>
                        <select v-model="filterRole"
                            @change="applyFilter"
                            class="block w-full py-1.5 px-2.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500 transition">
                            <option value="">Semua Role</option>
                            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>

                    <!-- Seksi Filter -->
                    <div>
                        <select v-model="filterSeksi"
                            @change="applyFilter"
                            class="block w-full py-1.5 px-2.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500 transition">
                            <option value="">Semua Seksi / Unit</option>
                            <option v-for="s in seksiList" :key="s.id" :value="s.nama">{{ formatSeksi(s.nama) }}</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <select v-model="filterStatus"
                            @change="applyFilter"
                            class="block w-full py-1.5 px-2.5 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500 transition">
                            <option value="">Semua Status</option>
                            <option value="Aktif">Aktif</option>
                            <option value="Suspend">Suspend / Nonaktif</option>
                            <option value="Menunggu">Menunggu</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800/60">
                    <button @click="applyFilter"
                        type="button"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 transition">
                        Terapkan Filter
                    </button>
                    <button v-if="search || filterRole || filterSeksi || filterStatus"
                        @click="resetFilter"
                        type="button"
                        class="px-2.5 py-1.5 text-xs font-medium rounded-lg text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        Reset
                    </button>
                </div>
            </div>

            <!-- Users Data Table Card -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 font-bold text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th scope="col" class="py-3.5 px-4 w-12 text-center">#</th>
                                <th scope="col" class="py-3.5 px-4">Pegawai</th>
                                <th scope="col" class="py-3.5 px-4">Role & Jabatan</th>
                                <th scope="col" class="py-3.5 px-4">Seksi / Penugasan</th>
                                <th scope="col" class="py-3.5 px-4">Identitas (PIN / NIK)</th>
                                <th scope="col" class="py-3.5 px-4 text-center w-28">Status</th>
                                <th scope="col" class="py-3.5 px-4 text-right w-36">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(u, index) in users.data"
                                :key="u.id"
                                class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3.5 px-4 text-center font-mono text-xs text-slate-400">
                                    {{ (users.current_page - 1) * users.per_page + index + 1 }}
                                </td>
                                <!-- Pegawai Info -->
                                <td class="py-3.5 px-4">
                                    <div @click="openUserDrawer(u, 'detail')"
                                        class="flex items-center gap-3 cursor-pointer group">
                                        <div class="w-9 h-9 rounded-xl bg-slate-800 text-white font-bold text-xs flex items-center justify-center flex-shrink-0 shadow-sm group-hover:scale-105 transition">
                                            {{ (u.nama_lengkap || u.name || 'U').slice(0, 2).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 dark:text-white group-hover:text-slate-600 dark:group-hover:text-slate-300 transition truncate flex items-center gap-1.5">
                                                <span>{{ u.nama_lengkap || u.name }}</span>
                                                <svg class="w-3.5 h-3.5 text-slate-400 opacity-0 group-hover:opacity-100 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </div>
                                            <div class="text-xs text-slate-400 flex items-center gap-2">
                                                <span>@{{ u.username }}</span>
                                                <span>•</span>
                                                <span class="truncate max-w-[160px]">{{ u.email }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Role & Jabatan -->
                                <td class="py-3.5 px-4">
                                    <div class="space-y-1">
                                        <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold border', getRoleBadgeClass(u.role)]">
                                            {{ u.role }}
                                        </span>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-xs">
                                            {{ u.posisi_pekerjaan || u.jabatan || '-' }}
                                        </div>
                                    </div>
                                </td>

                                <!-- Seksi -->
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center whitespace-nowrap text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-md border border-slate-200 dark:border-slate-700">
                                        {{ formatSeksi(u.seksi) }}
                                    </span>
                                </td>

                                <!-- Identitas PIN / NIK / NRK -->
                                <td class="py-3.5 px-4 font-mono text-xs text-slate-600 dark:text-slate-300">
                                    <div v-if="u.pin">PIN: <span class="font-semibold text-slate-800 dark:text-slate-200">{{ u.pin }}</span></div>
                                    <div v-if="u.nik">NIK: {{ u.nik }}</div>
                                    <div v-if="u.nrk">NRK: {{ u.nrk }}</div>
                                    <div v-if="!u.pin && !u.nik && !u.nrk" class="text-slate-400">-</div>
                                </td>

                                <!-- Status -->
                                <td class="py-3.5 px-4 text-center">
                                    <span v-if="u.status_user === 'Aktif'"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                    <span v-else-if="u.status_user === 'Suspend'"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Suspend
                                    </span>
                                    <span v-else
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        {{ u.status_user }}
                                    </span>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <!-- Detail Button -->
                                        <button @click="openUserDrawer(u, 'detail')"
                                            type="button"
                                            title="Lihat Detail Akun Pegawai"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200/80 dark:border-slate-700 transition">
                                            <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>Detail</span>
                                        </button>

                                        <!-- Edit Button -->
                                        <button @click="openUserDrawer(u, 'edit')"
                                            type="button"
                                            title="Edit Data Pengguna"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 transition shadow-xs">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            <span>Edit</span>
                                        </button>

                                        <!-- Dropdown Menu Trigger (v) -->
                                        <div class="relative inline-block text-left">
                                            <button @click="toggleActionDropdown(u.id, $event)"
                                                type="button"
                                                title="Menu Aksi Lainnya"
                                                class="p-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>

                                            <!-- Dropdown Menu -->
                                            <div v-if="openDropdownUserId === u.id"
                                                class="absolute right-0 mt-1 w-48 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl py-1 z-40 text-xs">
                                                <button @click="openUserDrawer(u, 'detail')"
                                                    type="button"
                                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center gap-2 text-slate-700 dark:text-slate-200">
                                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    <span>Lihat Detail</span>
                                                </button>
                                                <button @click="openUserDrawer(u, 'edit')"
                                                    type="button"
                                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center gap-2 text-slate-700 dark:text-slate-200">
                                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    <span>Edit Akun</span>
                                                </button>
                                                <button @click="openResetPasswordModal(u); openDropdownUserId = null"
                                                    type="button"
                                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center gap-2 text-slate-700 dark:text-slate-200">
                                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                                    </svg>
                                                    <span>Reset Password</span>
                                                </button>
                                                <button @click="toggleUserStatus(u); openDropdownUserId = null"
                                                    type="button"
                                                    class="w-full text-left px-3.5 py-2 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center gap-2 text-slate-700 dark:text-slate-200">
                                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                    </svg>
                                                    <span>{{ u.status_user === 'Aktif' ? 'Suspend Akun' : 'Aktifkan Akun' }}</span>
                                                </button>
                                                <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                                                <button @click="deleteUser(u); openDropdownUserId = null"
                                                    type="button"
                                                    class="w-full text-left px-3.5 py-2 hover:bg-rose-50 dark:hover:bg-rose-950/30 flex items-center gap-2 text-rose-600 dark:text-rose-400">
                                                    <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    <span>Hapus Pengguna</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!users.data || users.data.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="w-10 h-10 text-slate-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <span class="font-medium">Tidak ada data pengguna ditemukan</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Adaptive Cards Feed for Users Management -->
                <div class="block md:hidden p-3.5 space-y-3 bg-slate-50/50 dark:bg-slate-950/20">
                    <div v-if="!users.data || users.data.length === 0" class="py-10 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 p-6">
                        <div class="text-sm font-semibold">Tidak ada data pengguna ditemukan</div>
                        <p class="text-xs text-slate-500 mt-1">Coba sesuaikan filter atau kata kunci pencarian.</p>
                    </div>

                    <div v-for="(u, index) in users.data" :key="'mob-u-' + u.id"
                        class="p-4 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xs space-y-3 transition">
                        <!-- Top Row: Avatar, Name, Role Badge, Status -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-3 min-w-0" @click="openUserDrawer(u, 'detail')">
                                <div class="w-10 h-10 rounded-xl bg-slate-800 text-white font-bold text-xs flex items-center justify-center flex-shrink-0 shadow-sm">
                                    {{ (u.nama_lengkap || u.name || 'U').slice(0, 2).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-xs text-slate-900 dark:text-white truncate flex items-center gap-1.5">
                                        <span>{{ u.nama_lengkap || u.name }}</span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 flex items-center gap-1.5 truncate mt-0.5">
                                        <span>@{{ u.username }}</span>
                                        <span>•</span>
                                        <span class="truncate max-w-[140px]">{{ u.email }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <span v-if="u.status_user === 'Aktif'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Aktif
                            </span>
                            <span v-else-if="u.status_user === 'Suspend'"
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 flex-shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                Suspend
                            </span>
                            <span v-else
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 flex-shrink-0">
                                {{ u.status_user }}
                            </span>
                        </div>

                        <!-- Mid info: Role, Seksi, PIN/NIK -->
                        <div class="flex items-center gap-2 flex-wrap text-[11px] pt-1">
                            <span :class="['px-2.5 py-0.5 rounded-md text-[10px] font-bold border', getRoleBadgeClass(u.role)]">
                                {{ u.role }}
                            </span>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                🏢 {{ formatSeksi(u.seksi) }}
                            </span>
                            <span v-if="u.pin" class="font-mono text-slate-500 text-[10px]">
                                PIN: <strong>{{ u.pin }}</strong>
                            </span>
                        </div>

                        <!-- Action Buttons Bar for Mobile (Easy 40px Tap Targets) -->
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2">
                            <button @click="openUserDrawer(u, 'detail')" type="button"
                                class="flex-1 py-2 px-3 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition flex items-center justify-center gap-1 active:scale-95 cursor-pointer">
                                <span>Detail</span>
                            </button>
                            <button @click="openUserDrawer(u, 'edit')" type="button"
                                class="flex-1 py-2 px-3 rounded-xl text-xs font-bold text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition flex items-center justify-center gap-1 active:scale-95 cursor-pointer">
                                <span>Edit</span>
                            </button>
                            <button @click="openResetPasswordModal(u)" type="button"
                                class="py-2 px-3 rounded-xl text-xs font-bold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 transition flex items-center justify-center gap-1 active:scale-95 cursor-pointer">
                                <span>Reset</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="users.total > users.per_page"
                    class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                    <div>
                        Menampilkan <span class="font-semibold text-slate-700 dark:text-slate-300">{{ users.from || 0 }}</span> sampai
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ users.to || 0 }}</span> dari
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ users.total }}</span> pengguna
                    </div>
                    <div class="flex gap-1">
                        <template v-for="(link, idx) in users.links" :key="idx">
                            <button v-if="link.url"
                                @click="router.get(link.url, {}, { preserveState: true })"
                                v-html="link.label"
                                :class="[
                                    'px-2.5 py-1 rounded-md border font-medium text-xs transition',
                                    link.active
                                        ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 border-slate-900 dark:border-slate-100 font-semibold'
                                        : 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                ]"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backdrop Overlay untuk Action Dropdown -->
        <div v-if="openDropdownUserId" @click="closeActionDropdown" class="fixed inset-0 z-30"></div>

        <!-- Slide-Over Right Sidebar (Drawer) Detail & Edit Akun Pengguna -->
        <div v-if="showUserDrawer" class="relative z-50">
            <!-- Backdrop -->
            <div @click="closeUserDrawer"
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"></div>

            <!-- Drawer Container -->
            <div class="fixed inset-y-0 right-0 max-w-full flex pl-6 sm:pl-10">
                <div class="w-screen max-w-2xl bg-white dark:bg-slate-900 shadow-2xl border-l border-slate-200 dark:border-slate-800 flex flex-col h-full transform transition ease-in-out duration-300">
                    
                    <!-- Drawer Header -->
                    <div class="p-6 pb-4 border-b border-slate-200 dark:border-slate-800 flex-shrink-0 bg-slate-50/70 dark:bg-slate-800/40">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 font-bold text-base flex items-center justify-center flex-shrink-0 shadow-md">
                                    {{ (selectedUser?.nama_lengkap || selectedUser?.name || 'U').slice(0, 2).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-bold text-slate-900 dark:text-white truncate">
                                            {{ selectedUser?.nama_lengkap || selectedUser?.name }}
                                        </h3>
                                        <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border', getRoleBadgeClass(selectedUser?.role)]">
                                            {{ selectedUser?.role }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                                        @{{ selectedUser?.username }} • {{ formatSeksi(selectedUser?.seksi) || 'Unit Kerja' }}
                                    </p>
                                </div>
                            </div>
                            <button @click="closeUserDrawer"
                                type="button"
                                title="Tutup Panel"
                                class="p-1.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Tab Switcher: Detail Akun vs Edit Data -->
                        <div class="flex items-center gap-2 mt-5 p-1 bg-slate-100 dark:bg-slate-800/80 rounded-xl border border-slate-200 dark:border-slate-700">
                            <button @click="switchDrawerTab('detail')"
                                type="button"
                                :class="[
                                    'flex-1 py-1.5 text-xs font-semibold rounded-lg transition flex items-center justify-center gap-1.5',
                                    drawerTab === 'detail'
                                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                                ]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>Detail Akun Pegawai</span>
                            </button>
                            <button @click="switchDrawerTab('edit')"
                                type="button"
                                :class="[
                                    'flex-1 py-1.5 text-xs font-semibold rounded-lg transition flex items-center justify-center gap-1.5',
                                    drawerTab === 'edit'
                                        ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-xs'
                                        : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                                ]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span>Edit Data Akun</span>
                            </button>
                        </div>
                    </div>

                    <!-- Drawer Body -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- TAB 1: DETAIL AKUN -->
                        <div v-if="drawerTab === 'detail'" class="space-y-5">
                            <!-- Card 1: Data Pribadi & Identitas Pegawai (Requested Fields) -->
                            <div class="bg-slate-50/70 dark:bg-slate-800/40 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 space-y-4">
                                <div class="flex items-center justify-between pb-2.5 border-b border-slate-200/80 dark:border-slate-700/60">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                        </svg>
                                        <span>Data Pribadi & Profil Pegawai</span>
                                    </h4>
                                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-md bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                        Dossier Kepegawaian
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                    <!-- Jenis Kelamin (Gender) -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Jenis Kelamin (Gender)</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-white flex items-center gap-1.5">
                                            <span v-if="selectedUser?.jenis_kelamin === 'Laki-laki'" class="inline-flex items-center gap-1 text-slate-800 dark:text-slate-200">
                                                <svg class="w-4 h-4 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 3h5m0 0v5m0-5l-6 6M10 14a5 5 0 110-10 5 5 0 010 10z" />
                                                </svg>
                                                Laki-laki
                                            </span>
                                            <span v-else-if="selectedUser?.jenis_kelamin === 'Perempuan'" class="inline-flex items-center gap-1 text-slate-800 dark:text-slate-200">
                                                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4a5 5 0 100 10 5 5 0 000-10zm0 10v7m-3-3h6" />
                                                </svg>
                                                Perempuan
                                            </span>
                                            <span v-else class="text-slate-400 italic">Belum diisi</span>
                                        </div>
                                    </div>

                                    <!-- Status Pernikahan -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Status Pernikahan</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-white">
                                            {{ selectedUser?.status_pernikahan || '-' }}
                                        </div>
                                    </div>

                                    <!-- Pendidikan Terakhir -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800 sm:col-span-2">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Pendidikan Terakhir</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5" />
                                            </svg>
                                            <span>{{ selectedUser?.pendidikan_terakhir || '-' }}</span>
                                        </div>
                                    </div>

                                    <!-- Tempat & Tanggal Lahir -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Tempat, Tanggal Lahir</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-white">
                                            <span v-if="selectedUser?.tempat_lahir || selectedUser?.tanggal_lahir">
                                                {{ selectedUser?.tempat_lahir ? selectedUser.tempat_lahir + ', ' : '' }}{{ formatDate(selectedUser?.tanggal_lahir) }}
                                            </span>
                                            <span v-else class="text-slate-400 italic">-</span>
                                        </div>
                                    </div>

                                    <!-- NIK (KTP) -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Nomor Induk Kependudukan (NIK)</div>
                                        <div class="mt-1 font-mono font-bold text-slate-900 dark:text-white">
                                            {{ selectedUser?.nik || '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2: Kepegawaian & Penugasan (SPBE) -->
                            <div class="bg-slate-50/70 dark:bg-slate-800/40 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 space-y-4">
                                <div class="flex items-center justify-between pb-2.5 border-b border-slate-200/80 dark:border-slate-700/60">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        <span>Informasi Kepegawaian & Penugasan</span>
                                    </h4>
                                    <span class="text-[10px] font-medium px-2 py-0.5 rounded-md bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                        Unit Kerja
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                    <!-- Seksi / Unit -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Seksi / Unit Kerja</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-white">
                                            {{ formatSeksi(selectedUser?.seksi) || 'Pimpinan' }}
                                        </div>
                                    </div>

                                    <!-- Jabatan / Posisi -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Jabatan / Posisi Formasi KAK</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-white">
                                            {{ selectedUser?.posisi_pekerjaan || selectedUser?.jabatan || '-' }}
                                        </div>
                                    </div>

                                    <!-- PIN Absensi -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">PIN Mesin Absensi (Fingerprint)</div>
                                        <div class="mt-1 font-mono font-bold text-slate-900 dark:text-white">
                                            {{ selectedUser?.pin || '-' }}
                                        </div>
                                    </div>

                                    <!-- NRK / NIP -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">NRK / NIP Pegawai</div>
                                        <div class="mt-1 font-mono font-bold text-slate-900 dark:text-white">
                                            {{ selectedUser?.nrk || '-' }}
                                        </div>
                                    </div>

                                    <!-- Jam Kerja Operasional -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Jam Kerja Standar</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-white">
                                            {{ selectedUser?.jam_masuk ? selectedUser.jam_masuk.slice(0, 5) : '07:30' }} - {{ selectedUser?.jam_pulang ? selectedUser.jam_pulang.slice(0, 5) : '16:00' }} WIB
                                        </div>
                                    </div>

                                    <!-- Tanggal Mulai Bekerja -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Mulai Bertugas / Bekerja</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-white">
                                            {{ formatDate(selectedUser?.mulai_bekerja) }}
                                        </div>
                                    </div>

                                    <!-- Nama Instansi -->
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800 sm:col-span-2">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Nama Instansi / Perangkat Daerah</div>
                                        <div class="mt-1 font-semibold text-slate-900 dark:text-white">
                                            {{ selectedUser?.nama_instansi || 'Suku Dinas Komunikasi, Informatika dan Statistik Kota Administrasi Jakarta Barat' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 3: Akun Sistem & Kontak -->
                            <div class="bg-slate-50/70 dark:bg-slate-800/40 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 space-y-4">
                                <div class="flex items-center justify-between pb-2.5 border-b border-slate-200/80 dark:border-slate-700/60">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        <span>Kredensial Akun & Kontak</span>
                                    </h4>
                                    <span v-if="selectedUser?.status_user === 'Aktif'"
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Akun Aktif
                                    </span>
                                    <span v-else
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        {{ selectedUser?.status_user }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Username Masuk</div>
                                        <div class="mt-1 font-mono font-bold text-slate-900 dark:text-white">
                                            @{{ selectedUser?.username }}
                                        </div>
                                    </div>

                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Email Resmi</div>
                                        <div class="mt-1 font-medium text-slate-900 dark:text-white truncate">
                                            {{ selectedUser?.email }}
                                        </div>
                                    </div>

                                    <div class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/70 dark:border-slate-800 sm:col-span-2">
                                        <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Nomor WhatsApp</div>
                                        <div class="mt-1 font-medium text-slate-900 dark:text-white flex items-center gap-2">
                                            <span>{{ selectedUser?.no_wa || '-' }}</span>
                                            <a v-if="selectedUser?.no_wa"
                                                :href="`https://wa.me/${selectedUser.no_wa.replace(/^0/, '62')}`"
                                                target="_blank"
                                                rel="noopener"
                                                class="text-xs text-emerald-600 hover:underline inline-flex items-center gap-1 font-semibold">
                                                <span>Hubungi WA</span>
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: EDIT DATA AKUN -->
                        <div v-else-if="drawerTab === 'edit'">
                            <form @submit.prevent="submitForm" class="space-y-4">
                                <div class="bg-slate-50/70 dark:bg-slate-800/40 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 space-y-4">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 pb-2 border-b border-slate-200/80 dark:border-slate-700/60">
                                        Data Pribadi Pegawai
                                    </h4>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                        <!-- Jenis Kelamin -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Jenis Kelamin (Gender)
                                            </label>
                                            <select v-model="form.jenis_kelamin"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500">
                                                <option value="">Pilih Jenis Kelamin</option>
                                                <option value="Laki-laki">Laki-laki</option>
                                                <option value="Perempuan">Perempuan</option>
                                            </select>
                                            <p v-if="form.errors.jenis_kelamin" class="mt-1 text-xs text-rose-500">{{ form.errors.jenis_kelamin }}</p>
                                        </div>

                                        <!-- Status Pernikahan -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Status Pernikahan
                                            </label>
                                            <select v-model="form.status_pernikahan"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500">
                                                <option value="">Pilih Status Pernikahan</option>
                                                <option value="Belum Menikah">Belum Menikah</option>
                                                <option value="Menikah">Menikah</option>
                                                <option value="Cerai Hidup">Cerai Hidup</option>
                                                <option value="Cerai Mati">Cerai Mati</option>
                                            </select>
                                            <p v-if="form.errors.status_pernikahan" class="mt-1 text-xs text-rose-500">{{ form.errors.status_pernikahan }}</p>
                                        </div>

                                        <!-- Pendidikan Terakhir -->
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Pendidikan Terakhir
                                            </label>
                                            <input v-model="form.pendidikan_terakhir"
                                                type="text"
                                                list="drawer-pendidikan-options"
                                                placeholder="Contoh: S1 Teknik Informatika / SMA / SMK"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                            <datalist id="drawer-pendidikan-options">
                                                <option value="SMA / SMK Sederajat" />
                                                <option value="Diploma (D3)" />
                                                <option value="Sarjana Terapan / Sarjana (D4 / S1)" />
                                                <option value="Magister (S2)" />
                                                <option value="Doktor (S3)" />
                                            </datalist>
                                            <p v-if="form.errors.pendidikan_terakhir" class="mt-1 text-xs text-rose-500">{{ form.errors.pendidikan_terakhir }}</p>
                                        </div>

                                        <!-- Tempat Lahir -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Tempat Lahir
                                            </label>
                                            <input v-model="form.tempat_lahir"
                                                type="text"
                                                placeholder="Kota Kelahiran"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                        </div>

                                        <!-- Tanggal Lahir -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Tanggal Lahir
                                            </label>
                                            <input v-model="form.tanggal_lahir"
                                                type="date"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-slate-50/70 dark:bg-slate-800/40 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 space-y-4">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 pb-2 border-b border-slate-200/80 dark:border-slate-700/60">
                                        Informasi Akun & Akses
                                    </h4>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                        <!-- Nama Lengkap -->
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Nama Lengkap <span class="text-rose-500">*</span>
                                            </label>
                                            <input v-model="form.nama_lengkap"
                                                type="text"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500"
                                                required />
                                            <p v-if="form.errors.nama_lengkap" class="mt-1 text-xs text-rose-500">{{ form.errors.nama_lengkap }}</p>
                                        </div>

                                        <!-- Username -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Username <span class="text-rose-500">*</span>
                                            </label>
                                            <input v-model="form.username"
                                                type="text"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500"
                                                required />
                                            <p v-if="form.errors.username" class="mt-1 text-xs text-rose-500">{{ form.errors.username }}</p>
                                        </div>

                                        <!-- Email -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Email Resmi <span class="text-rose-500">*</span>
                                            </label>
                                            <input v-model="form.email"
                                                type="email"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500"
                                                required />
                                            <p v-if="form.errors.email" class="mt-1 text-xs text-rose-500">{{ form.errors.email }}</p>
                                        </div>

                                        <!-- Password Baru (Opsional) -->
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Password Baru <span class="text-[10px] text-slate-400 font-normal lowercase">(kosongkan jika tidak diubah)</span>
                                            </label>
                                            <input v-model="form.password"
                                                type="password"
                                                placeholder="••••••••"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                            <p v-if="form.errors.password" class="mt-1 text-xs text-rose-500">{{ form.errors.password }}</p>
                                        </div>

                                        <!-- Role Akses -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Role Akses <span class="text-rose-500">*</span>
                                            </label>
                                            <select v-model="form.role"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500">
                                                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                                            </select>
                                        </div>

                                        <!-- Seksi / Unit -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Seksi / Unit Kerja <span class="text-rose-500">*</span>
                                            </label>
                                            <select v-model="form.seksi"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500">
                                                <option v-for="s in seksiList" :key="s.id" :value="s.nama">{{ formatSeksi(s.nama) }}</option>
                                            </select>
                                        </div>

                                        <!-- Status User -->
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Status Akun
                                            </label>
                                            <select v-model="form.status_user"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500">
                                                <option value="Aktif">Aktif</option>
                                                <option value="Menunggu">Menunggu</option>
                                                <option value="Suspend">Suspend (Nonaktif)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-slate-50/70 dark:bg-slate-800/40 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800 space-y-4">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 pb-2 border-b border-slate-200/80 dark:border-slate-700/60">
                                        Data Kepegawaian & SPBE
                                    </h4>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                        <!-- NIK -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                NIK (16 Digit)
                                            </label>
                                            <input v-model="form.nik"
                                                type="text"
                                                placeholder="16 digit NIK"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                        </div>

                                        <!-- NRK -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                NRK / NIP Pegawai
                                            </label>
                                            <input v-model="form.nrk"
                                                type="text"
                                                placeholder="NRK / NIP"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                        </div>

                                        <!-- PIN Absensi -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                PIN Mesin Absensi
                                            </label>
                                            <input v-model="form.pin"
                                                type="text"
                                                placeholder="Contoh: 80790520"
                                                class="block w-full py-2 px-3 text-xs font-mono rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                        </div>

                                        <!-- WhatsApp -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Nomor WhatsApp
                                            </label>
                                            <input v-model="form.no_wa"
                                                type="text"
                                                placeholder="08xxxxxxxxxx"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                        </div>

                                        <!-- Jabatan / Posisi -->
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Jabatan / Formasi Posisi KAK
                                            </label>
                                            <input v-model="form.posisi_pekerjaan"
                                                type="text"
                                                placeholder="Tenaga Ahli Programmer / PJLP Reporter / dsb"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                        </div>

                                        <!-- Jam Masuk & Jam Pulang -->
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Jam Masuk
                                            </label>
                                            <input v-model="form.jam_masuk"
                                                type="time"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Jam Pulang
                                            </label>
                                            <input v-model="form.jam_pulang"
                                                type="time"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                        </div>

                                        <!-- Tanggal Mulai Bekerja -->
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                                Tanggal Mulai Bekerja
                                            </label>
                                            <input v-model="form.mulai_bekerja"
                                                type="date"
                                                class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons in Edit Tab -->
                                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                                    <button @click="switchDrawerTab('detail')"
                                        type="button"
                                        class="px-4 py-2 text-xs font-medium rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                        Kembali ke Detail
                                    </button>
                                    <button type="submit"
                                        :disabled="form.processing"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-semibold rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 shadow-sm disabled:opacity-50 transition active:scale-98">
                                        <span v-if="form.processing">Menyimpan Perubahan...</span>
                                        <span v-else>Simpan Perubahan Akun</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Drawer Footer (for Detail Tab) -->
                    <div v-if="drawerTab === 'detail'" class="p-4 px-6 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/70 dark:bg-slate-800/40">
                        <button @click="openResetPasswordModal(selectedUser)"
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-slate-700 rounded-lg transition">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            <span>Reset Kata Sandi</span>
                        </button>

                        <div class="flex items-center gap-2">
                            <button @click="closeUserDrawer"
                                type="button"
                                class="px-3.5 py-1.5 text-xs font-medium rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-slate-700 transition">
                                Tutup
                            </button>
                            <button @click="switchDrawerTab('edit')"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 shadow-xs transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span>Edit Akun Ini</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Pengguna Baru -->
        <div v-if="showModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-2xl w-full p-6 border border-slate-200 dark:border-slate-800 shadow-2xl relative my-8">
                <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>Tambah Pengguna Baru</span>
                    </h3>
                    <button @click="showModal = false"
                        type="button"
                        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                        <!-- Nama Lengkap -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Nama Lengkap <span class="text-rose-500">*</span>
                            </label>
                            <input v-model="form.nama_lengkap"
                                type="text"
                                placeholder="Nama lengkap pegawai beserta gelar"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500"
                                required />
                            <p v-if="form.errors.nama_lengkap" class="mt-1 text-xs text-rose-500">{{ form.errors.nama_lengkap }}</p>
                        </div>

                        <!-- Username -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Username <span class="text-rose-500">*</span>
                            </label>
                            <input v-model="form.username"
                                type="text"
                                placeholder="Username untuk masuk sistem"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500"
                                required />
                            <p v-if="form.errors.username" class="mt-1 text-xs text-rose-500">{{ form.errors.username }}</p>
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Email Resmi <span class="text-rose-500">*</span>
                            </label>
                            <input v-model="form.email"
                                type="email"
                                placeholder="contoh@jakarta.go.id"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500"
                                required />
                            <p v-if="form.errors.email" class="mt-1 text-xs text-rose-500">{{ form.errors.email }}</p>
                        </div>

                        <!-- Password -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Password <span class="text-rose-500">*</span>
                            </label>
                            <input v-model="form.password"
                                type="password"
                                placeholder="Minimal 6 karakter"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500"
                                required />
                            <p v-if="form.errors.password" class="mt-1 text-xs text-rose-500">{{ form.errors.password }}</p>
                        </div>

                        <!-- Role -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Role Akses <span class="text-rose-500">*</span>
                            </label>
                            <select v-model="form.role"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500">
                                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                            </select>
                            <p v-if="form.errors.role" class="mt-1 text-xs text-rose-500">{{ form.errors.role }}</p>
                        </div>

                        <!-- Seksi / Penugasan -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Seksi / Unit Kerja <span class="text-rose-500">*</span>
                            </label>
                            <select v-model="form.seksi"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500">
                                <option v-for="s in seksiList" :key="s.id" :value="s.nama">{{ formatSeksi(s.nama) }}</option>
                            </select>
                            <p v-if="form.errors.seksi" class="mt-1 text-xs text-rose-500">{{ form.errors.seksi }}</p>
                        </div>

                        <!-- Jenis Kelamin -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Jenis Kelamin (Gender)
                            </label>
                            <select v-model="form.jenis_kelamin"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500">
                                <option value="">Pilih Jenis Kelamin</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>

                        <!-- Status Pernikahan -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Status Pernikahan
                            </label>
                            <select v-model="form.status_pernikahan"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500">
                                <option value="">Pilih Status Pernikahan</option>
                                <option value="Belum Menikah">Belum Menikah</option>
                                <option value="Menikah">Menikah</option>
                                <option value="Cerai Hidup">Cerai Hidup</option>
                                <option value="Cerai Mati">Cerai Mati</option>
                            </select>
                        </div>

                        <!-- Pendidikan Terakhir -->
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Pendidikan Terakhir
                            </label>
                            <input v-model="form.pendidikan_terakhir"
                                type="text"
                                list="modal-pendidikan-options"
                                placeholder="Contoh: Sarjana (S1) / SMA / SMK"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500" />
                            <datalist id="modal-pendidikan-options">
                                <option value="SMA / SMK Sederajat" />
                                <option value="Diploma (D3)" />
                                <option value="Sarjana Terapan / Sarjana (D4 / S1)" />
                                <option value="Magister (S2)" />
                                <option value="Doktor (S3)" />
                            </datalist>
                        </div>

                        <!-- NIK -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                NIK (KTP)
                            </label>
                            <input v-model="form.nik"
                                type="text"
                                placeholder="16 digit NIK"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500" />
                        </div>

                        <!-- NRK / NIP -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                NRK / NIP Pegawai
                            </label>
                            <input v-model="form.nrk"
                                type="text"
                                placeholder="Nomor Registrasi Kepegawaian"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500" />
                        </div>

                        <!-- PIN Fingerprint -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                PIN Mesin Absensi
                            </label>
                            <input v-model="form.pin"
                                type="text"
                                placeholder="Contoh: 80790520"
                                class="block w-full py-1.5 px-3 text-xs font-mono rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500" />
                        </div>

                        <!-- Nomor WhatsApp -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Nomor WhatsApp
                            </label>
                            <input v-model="form.no_wa"
                                type="text"
                                placeholder="08xxxxxxxxxx"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500" />
                        </div>

                        <!-- Jabatan / Posisi Pekerjaan -->
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Jabatan / Posisi Pekerjaan
                            </label>
                            <input v-model="form.posisi_pekerjaan"
                                type="text"
                                placeholder="Contoh: Tenaga Ahli Programmer / PJLP Reporter / Kepala Seksi"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500" />
                        </div>

                        <!-- Status User -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                Status Akun
                            </label>
                            <select v-model="form.status_user"
                                class="block w-full py-1.5 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500">
                                <option value="Aktif">Aktif</option>
                                <option value="Menunggu">Menunggu</option>
                                <option value="Suspend">Suspend (Nonaktif)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-200 dark:border-slate-800">
                        <button @click="showModal = false"
                            type="button"
                            class="px-3.5 py-1.5 text-xs font-medium rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Batal
                        </button>
                        <button type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 shadow-xs disabled:opacity-50 transition">
                            <span v-if="form.processing">Menyimpan...</span>
                            <span v-else>Simpan Pengguna</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Reset Password -->
        <div v-if="showPasswordModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-800 shadow-xl relative">
                <div class="flex items-center justify-between pb-3.5 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                        Reset Kata Sandi Pengguna
                    </h3>
                    <button @click="showPasswordModal = false"
                        type="button"
                        class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="targetUserForPassword" class="mt-3.5 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 text-xs space-y-1">
                    <div class="text-slate-500">Mereset kata sandi untuk akun:</div>
                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ targetUserForPassword.nama_lengkap }} (@{{ targetUserForPassword.username }})</div>
                    <div class="text-[11px] text-slate-400">Role: {{ targetUserForPassword.role }} • {{ formatSeksi(targetUserForPassword.seksi) }}</div>
                </div>

                <form @submit.prevent="submitResetPassword" class="mt-4 space-y-3.5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                            Kata Sandi Baru <span class="text-rose-500">*</span>
                        </label>
                        <input v-model="passwordForm.password"
                            type="password"
                            placeholder="Minimal 6 karakter"
                            class="block w-full py-2 px-3 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-slate-400 dark:focus:ring-slate-500 focus:border-slate-400 dark:focus:border-slate-500"
                            required />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-rose-500">
                            {{ passwordForm.errors.password }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3.5 border-t border-slate-200 dark:border-slate-800">
                        <button @click="showPasswordModal = false"
                            type="button"
                            class="px-3.5 py-1.5 text-xs font-medium rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Batal
                        </button>
                        <button type="submit"
                            :disabled="passwordForm.processing"
                            class="px-4 py-2 text-xs font-semibold rounded-lg bg-slate-900 hover:bg-slate-800 text-white dark:bg-slate-100 dark:hover:bg-white dark:text-slate-900 shadow-xs disabled:opacity-50 transition">
                            <span v-if="passwordForm.processing">Mereset...</span>
                            <span v-else>Reset Password</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
