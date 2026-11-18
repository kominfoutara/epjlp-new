<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import {
    Users,
    Search,
    Building2,
    ShieldCheck,
    Clock,
    X,
    Filter
} from 'lucide-vue-next';

const props = defineProps({
    list: Object,
    filters: Object,
    seksiList: Array,
});

const search = ref(props.filters.search || '');
const filterSeksi = ref(props.filters.seksi || '');
const filterRole = ref(props.filters.role || '');
const filterStatus = ref(props.filters.status || '');

const applyFilter = () => {
    router.get(route('pjlp.index'), {
        search: search.value || undefined,
        seksi: filterSeksi.value || undefined,
        role: filterRole.value || undefined,
        status: filterStatus.value || undefined,
    }, { preserveState: true, replace: true });
};

const resetFilter = () => {
    search.value = '';
    filterSeksi.value = '';
    filterRole.value = '';
    filterStatus.value = '';
    router.get(route('pjlp.index'), {}, { preserveState: true, replace: true });
};

const hasActiveFilter = () => {
    return !!(search.value || filterSeksi.value || filterRole.value || filterStatus.value);
};

const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'Kasudin':
            return 'bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 ring-1 ring-amber-300/50';
        case 'Kasi':
            return 'bg-indigo-50 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 ring-1 ring-indigo-300/50';
        case 'PJLP':
        default:
            return 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 ring-1 ring-emerald-300/50';
    }
};

const getRoleAvatarClass = (role) => {
    switch (role) {
        case 'Kasudin':
            return 'bg-gradient-to-br from-amber-500 to-orange-600 text-white';
        case 'Kasi':
            return 'bg-gradient-to-br from-indigo-600 to-purple-600 text-white';
        case 'PJLP':
        default:
            return 'bg-gradient-to-br from-emerald-500 to-teal-700 text-white';
    }
};
</script>

<template>
    <Head title="Manajemen Pengguna" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 ring-1 ring-blue-300/40">
                            Direktori Pengguna
                        </span>
                        <span class="text-xs text-slate-400">• Database Pengguna Sudin Kominfotik</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                        Manajemen Pengguna
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">
                        Direktori seluruh pengguna sistem (PJLP, Kepala Seksi, dan Kasudin) beserta seksi penempatan, nomor PIN mesin absensi, dan jam kerja terjadwal.
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Filter Bar -->
            <div class="bg-white dark:bg-slate-900 p-4 sm:p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <!-- Seksi Filter -->
                    <div class="flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-slate-400 flex-shrink-0" />
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Seksi:</label>
                        <select
                            v-model="filterSeksi"
                            @change="applyFilter"
                            class="rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3 font-medium cursor-pointer"
                        >
                            <option value="">Semua Seksi</option>
                            <option v-for="s in seksiList" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>

                    <!-- Role Filter -->
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="w-4 h-4 text-slate-400 flex-shrink-0" />
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Peran:</label>
                        <select
                            v-model="filterRole"
                            @change="applyFilter"
                            class="rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3 font-medium cursor-pointer"
                        >
                            <option value="">Semua Peran</option>
                            <option value="PJLP">PJLP</option>
                            <option value="Kasi">Kepala Seksi (Kasi)</option>
                            <option value="Kasudin">Kepala Suku Dinas</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Status:</label>
                        <select
                            v-model="filterStatus"
                            @change="applyFilter"
                            class="rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3 font-medium cursor-pointer"
                        >
                            <option value="">Semua Status</option>
                            <option value="Aktif">Aktif</option>
                            <option value="Menunggu">Menunggu</option>
                            <option value="Suspend">Suspend / Nonaktif</option>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div class="flex items-center gap-2 flex-1 min-w-[220px] max-w-sm">
                        <div class="relative w-full">
                            <input
                                type="text"
                                v-model="search"
                                @keyup.enter="applyFilter"
                                placeholder="Cari nama, NIK, PIN, username, posisi..."
                                class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 pl-8 pr-7"
                            />
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none" />
                            <button
                                v-if="search"
                                type="button"
                                @click="search = ''; applyFilter()"
                                class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <X class="w-3.5 h-3.5" />
                            </button>
                        </div>
                        <button
                            type="button"
                            @click="applyFilter"
                            class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 transition"
                        >
                            Cari
                        </button>
                    </div>

                    <!-- Reset Filter Button -->
                    <button
                        v-if="hasActiveFilter()"
                        type="button"
                        @click="resetFilter"
                        class="text-xs font-bold text-rose-600 hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300 inline-flex items-center gap-1 transition"
                    >
                        <X class="w-3 h-3" />
                        <span>Reset Filter</span>
                    </button>
                </div>

                <div class="text-xs text-slate-500 self-end lg:self-center font-medium">
                    Total: <span class="font-bold text-slate-900 dark:text-slate-100">{{ list.total || 0 }}</span> Pengguna
                </div>
            </div>

            <!-- Tabel Direktori Pengguna -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200/80 dark:border-slate-700/80 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3.5 px-4">Nama Pengguna & NIK</th>
                                <th class="py-3.5 px-4">Peran / Role</th>
                                <th class="py-3.5 px-4">Username & Email</th>
                                <th class="py-3.5 px-4 text-center">PIN Mesin</th>
                                <th class="py-3.5 px-4">Seksi & Posisi Kerja</th>
                                <th class="py-3.5 px-4">Jam Kerja Terjadwal</th>
                                <th class="py-3.5 px-4 text-center">Status Akun</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                            <tr v-if="!list.data || list.data.length === 0">
                                <td colspan="7" class="py-16 text-center text-slate-400">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-3 text-slate-400">
                                        <Users class="w-6 h-6" />
                                    </div>
                                    <div class="text-sm font-bold text-slate-700 dark:text-slate-300">Tidak ada data pengguna ditemukan</div>
                                    <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau bersihkan filter yang aktif.</p>
                                </td>
                            </tr>
                            <tr
                                v-for="user in list.data"
                                :key="user.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition group"
                            >
                                <!-- Nama & NIK -->
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-9 h-9 rounded-xl font-extrabold flex items-center justify-center text-xs shadow-sm flex-shrink-0"
                                            :class="getRoleAvatarClass(user.role)"
                                        >
                                            {{ (user.nama_lengkap || user.name || 'U').slice(0, 2).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-slate-100 group-hover:text-blue-600 transition">
                                                {{ user.nama_lengkap || user.name }}
                                            </div>
                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                NIK: {{ user.nik || '-' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Peran / Role -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span
                                        class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                        :class="getRoleBadgeClass(user.role)"
                                    >
                                        {{ user.role === 'Kasi' ? 'Kepala Seksi' : (user.role === 'Kasudin' ? 'Kasudin' : user.role) }}
                                    </span>
                                </td>

                                <!-- Username & Email -->
                                <td class="py-3.5 px-4">
                                    <div class="font-mono font-medium text-slate-700 dark:text-slate-300">
                                        @{{ user.username }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate max-w-xs">
                                        {{ user.email }}
                                    </div>
                                </td>

                                <!-- PIN Mesin -->
                                <td class="py-3.5 px-4 text-center font-mono font-bold text-blue-600 dark:text-blue-400">
                                    <span v-if="user.pin" class="px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900">
                                        {{ user.pin }}
                                    </span>
                                    <span v-else class="text-slate-400 text-[11px] font-normal italic">
                                        -
                                    </span>
                                </td>

                                <!-- Seksi & Posisi -->
                                <td class="py-3.5 px-4">
                                    <div class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 mb-0.5">
                                        {{ user.seksi || 'Pimpinan' }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ user.posisi_pekerjaan || (user.role === 'Kasi' ? 'Kepala Seksi' : (user.role === 'Kasudin' ? 'Kepala Suku Dinas' : 'PJLP Kominfotik')) }}
                                    </div>
                                </td>

                                <!-- Jam Kerja -->
                                <td class="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <Clock class="w-3.5 h-3.5 text-slate-400" />
                                        <span>{{ user.jam_masuk ? user.jam_masuk.slice(0, 5) : '07:30' }} – {{ user.jam_pulang ? user.jam_pulang.slice(0, 5) : '16:00' }} WIB</span>
                                    </div>
                                </td>

                                <!-- Status Akun -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span
                                        class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 ring-1 ring-emerald-300/40': user.status_user === 'Aktif',
                                            'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 ring-1 ring-amber-300/40': user.status_user === 'Menunggu',
                                            'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 ring-1 ring-rose-300/40': user.status_user === 'Suspend' || user.status_user === 'Nonaktif',
                                        }"
                                    >
                                        {{ user.status_user || 'Aktif' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="list.links && list.links.length > 3" class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="text-xs text-slate-500">
                        Menampilkan <strong class="text-slate-800 dark:text-slate-200">{{ list.from || 0 }}</strong> – <strong class="text-slate-800 dark:text-slate-200">{{ list.to || 0 }}</strong> dari <strong class="text-slate-800 dark:text-slate-200">{{ list.total || 0 }}</strong> pengguna
                    </div>
                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in list.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                                :class="link.active
                                    ? 'bg-blue-600 text-white shadow-xs'
                                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-300 dark:text-slate-600 cursor-not-allowed border border-slate-100 dark:border-slate-800"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
