<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    seksiList: Object,
    filters: Object,
});

const search = ref(props.filters.search || '');
const filterStatus = ref(props.filters.status || '');

const showModal = ref(false);
const editingSeksi = ref(null);

const form = useForm({
    nama: '',
    kode: '',
    deskripsi: '',
    is_active: true,
});

const applyFilter = () => {
    router.get(route('seksi.index'), {
        search: search.value || undefined,
        status: filterStatus.value || undefined,
    }, { preserveState: true, replace: true });
};

const resetFilter = () => {
    search.value = '';
    filterStatus.value = '';
    applyFilter();
};

const openAddModal = () => {
    editingSeksi.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
    showModal.value = true;
};

const openEditModal = (seksi) => {
    editingSeksi.value = seksi;
    form.clearErrors();
    form.nama = seksi.nama;
    form.kode = seksi.kode;
    form.deskripsi = seksi.deskripsi || '';
    form.is_active = Boolean(seksi.is_active);
    showModal.value = true;
};

const submitForm = () => {
    if (editingSeksi.value) {
        form.put(route('seksi.update', editingSeksi.value.id), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    } else {
        form.post(route('seksi.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    }
};

const toggleStatus = (seksi) => {
    const action = seksi.is_active ? 'menonaktifkan' : 'mengaktifkan kembali';
    if (confirm(`Apakah Anda yakin ingin ${action} "${seksi.nama}"?`)) {
        router.patch(route('seksi.toggle', seksi.id), {}, { preserveScroll: true });
    }
};

const deleteSeksi = (seksi) => {
    if (confirm(`Peringatan: Seksi "${seksi.nama}" akan dinonaktifkan/dihapus (soft delete). Riwayat aktivitas terdahulu tetap terjaga. Lanjutkan?`)) {
        router.delete(route('seksi.destroy', seksi.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Manajemen Seksi - EPJLP" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-black tracking-tight text-slate-800 dark:text-slate-100">
                            Master Data Seksi
                        </h2>
                        <span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                            Superadmin
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Kelola data unit kerja/seksi resmi Suku Dinas Kominfotik, kode singkatan, dan status operasional
                    </p>
                </div>
                <div>
                    <button @click="openAddModal"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white shadow-xs text-white font-semibold text-sm shadow-md shadow-emerald-600/20 hover:shadow-lg hover:shadow-emerald-600/30 transition-all transform active:scale-95">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        <span>Tambah Seksi Baru</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Filter & Search Toolbar -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-5 border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex-1 flex flex-col sm:flex-row gap-3">
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input v-model="search"
                            @keyup.enter="applyFilter"
                            type="text"
                            placeholder="Cari nama seksi, kode, atau deskripsi..."
                            class="block w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition" />
                    </div>

                    <!-- Status Filter -->
                    <div class="sm:w-48">
                        <select v-model="filterStatus"
                            @change="applyFilter"
                            class="block w-full py-2 px-3 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
                            <option value="">Semua Status</option>
                            <option value="active">Aktif Saja</option>
                            <option value="inactive">Nonaktif Saja</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="applyFilter"
                        type="button"
                        class="px-4 py-2 text-sm font-semibold rounded-xl bg-slate-900 dark:bg-slate-700 text-white hover:bg-slate-800 transition">
                        Cari
                    </button>
                    <button v-if="search || filterStatus"
                        @click="resetFilter"
                        type="button"
                        class="px-3 py-2 text-sm font-medium rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        Reset
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 font-bold text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                            <tr>
                                <th scope="col" class="py-3.5 px-4 w-12 text-center">#</th>
                                <th scope="col" class="py-3.5 px-4 w-28">Kode</th>
                                <th scope="col" class="py-3.5 px-4">Nama Seksi / Unit Kerja</th>
                                <th scope="col" class="py-3.5 px-4">Deskripsi</th>
                                <th scope="col" class="py-3.5 px-4 text-center w-32">Pegawai</th>
                                <th scope="col" class="py-3.5 px-4 text-center w-28">Status</th>
                                <th scope="col" class="py-3.5 px-4 text-right w-36">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(seksi, index) in seksiList.data"
                                :key="seksi.id"
                                class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3.5 px-4 text-center font-mono text-xs text-slate-400">
                                    {{ (seksiList.current_page - 1) * seksiList.per_page + index + 1 }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700">
                                        {{ seksi.kode }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-900 dark:text-white">
                                        {{ seksi.nama }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md line-clamp-2">
                                        {{ seksi.deskripsi || '-' }}
                                    </p>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        {{ seksi.users_count || 0 }} Orang
                                    </span>
                                    <p class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                                        {{ seksi.pjlp_count || 0 }} PJLP · {{ (seksi.users_count || 0) - (seksi.pjlp_count || 0) }} lainnya
                                    </p>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span v-if="seksi.is_active"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Aktif
                                    </span>
                                    <span v-else
                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                                        Nonaktif
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <!-- Edit -->
                                        <button @click="openEditModal(seksi)"
                                            type="button"
                                            title="Edit Seksi"
                                            class="p-1.5 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-800 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>

                                        <!-- Toggle Status -->
                                        <button @click="toggleStatus(seksi)"
                                            type="button"
                                            :title="seksi.is_active ? 'Nonaktifkan Seksi' : 'Aktifkan Seksi'"
                                            class="p-1.5 text-slate-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-slate-800 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                        </button>

                                        <!-- Delete / Soft Delete -->
                                        <button @click="deleteSeksi(seksi)"
                                            type="button"
                                            title="Hapus Seksi"
                                            class="p-1.5 text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-slate-800 rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!seksiList.data || seksiList.data.length === 0">
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="w-10 h-10 text-slate-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        <span class="font-medium">Tidak ada data seksi ditemukan</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="seksiList.total > seksiList.per_page"
                    class="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                    <div>
                        Menampilkan <span class="font-semibold text-slate-700 dark:text-slate-300">{{ seksiList.from || 0 }}</span> sampai
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ seksiList.to || 0 }}</span> dari
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ seksiList.total }}</span> seksi
                    </div>
                    <div class="flex gap-1">
                        <template v-for="(link, idx) in seksiList.links" :key="idx">
                            <button v-if="link.url"
                                @click="router.get(link.url, {}, { preserveState: true })"
                                v-html="link.label"
                                :class="[
                                    'px-3 py-1.5 rounded-lg border font-medium transition',
                                    link.active
                                        ? 'bg-emerald-600 text-white border-emerald-600'
                                        : 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                ]"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah / Edit Seksi -->
        <div v-if="showModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 border border-slate-200 dark:border-slate-800 shadow-2xl relative">
                <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>{{ editingSeksi ? 'Edit Data Seksi' : 'Tambah Seksi Baru' }}</span>
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
                    <!-- Kode Seksi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                            Kode Singkatan <span class="text-rose-500">*</span>
                        </label>
                        <input v-model="form.kode"
                            type="text"
                            placeholder="Contoh: ASTIK, KIP, ID, TU"
                            class="block w-full py-2.5 px-3.5 text-sm uppercase font-mono rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            required />
                        <p v-if="form.errors.kode" class="mt-1 text-xs text-rose-500">
                            {{ form.errors.kode }}
                        </p>
                    </div>

                    <!-- Nama Seksi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                            Nama Seksi Lengkap <span class="text-rose-500">*</span>
                        </label>
                        <input v-model="form.nama"
                            type="text"
                            placeholder="Contoh: Seksi Aplikasi Siber dan Transformasi Informasi Komunikasi"
                            class="block w-full py-2.5 px-3.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            required />
                        <p v-if="form.errors.nama" class="mt-1 text-xs text-rose-500">
                            {{ form.errors.nama }}
                        </p>
                    </div>

                    <!-- Deskripsi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                            Deskripsi / Keterangan Tugas
                        </label>
                        <textarea v-model="form.deskripsi"
                            rows="3"
                            placeholder="Tuliskan ringkasan tugas dan fungsi seksi ini..."
                            class="block w-full py-2.5 px-3.5 text-sm rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                        <p v-if="form.errors.deskripsi" class="mt-1 text-xs text-rose-500">
                            {{ form.errors.deskripsi }}
                        </p>
                    </div>

                    <!-- Status Aktif Checkbox -->
                    <div class="flex items-center gap-3 pt-1">
                        <input v-model="form.is_active"
                            id="is_active_toggle"
                            type="checkbox"
                            class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500" />
                        <label for="is_active_toggle" class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            Seksi berstatus Aktif (Dapat dipilih dalam sistem)
                        </label>
                    </div>

                    <!-- Form Buttons -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                        <button @click="showModal = false"
                            type="button"
                            class="px-4 py-2 text-sm font-medium rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            Batal
                        </button>
                        <button type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-5 py-2 text-sm font-semibold rounded-xl bg-slate-900 hover:bg-slate-800 text-white shadow-xs text-white shadow-md shadow-emerald-600/20 disabled:opacity-50 transition">
                            <span v-if="form.processing">Menyimpan...</span>
                            <span v-else>{{ editingSeksi ? 'Perbarui Seksi' : 'Simpan Seksi' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
