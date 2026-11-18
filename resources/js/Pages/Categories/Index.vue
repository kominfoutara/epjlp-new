<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Tags,
    Plus,
    Building2,
    Edit3,
    Trash2,
    X,
    CheckCircle2
} from 'lucide-vue-next';

const props = defineProps({
    categories: Object,
    seksiList: Array,
    filters: Object,
});

const filterSeksi = ref(props.filters.seksi || '');

// Form Tambah / Edit Kategori
const showModal = ref(false);
const editingCategory = ref(null);

const form = useForm({
    nama: '',
    seksi: props.seksiList[0] || 'Seksi ASTIK',
});

const applyFilter = () => {
    router.get(route('categories.index'), {
        seksi: filterSeksi.value || undefined,
    }, { preserveState: true, replace: true });
};

const openAddModal = () => {
    editingCategory.value = null;
    form.reset();
    form.seksi = filterSeksi.value || props.seksiList[0] || 'Seksi ASTIK';
    showModal.value = true;
};

const openEditModal = (cat) => {
    editingCategory.value = cat;
    form.nama = cat.nama;
    form.seksi = cat.seksi;
    showModal.value = true;
};

const submitForm = () => {
    if (editingCategory.value) {
        form.put(route('categories.update', editingCategory.value.id), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    } else {
        form.post(route('categories.store'), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            }
        });
    }
};

const deleteCategory = (cat) => {
    if (confirm(`Apakah Anda yakin ingin menghapus kategori tugas "${cat.nama}"?`)) {
        router.delete(route('categories.destroy', cat.id));
    }
};
</script>

<template>
    <Head title="Master Kategori Tugas PJLP" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                            Master Data SPBE
                        </span>
                        <span class="text-xs text-slate-400">• Standarisasi Uraian Tugas</span>
                    </div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                        Master Kategori Tugas PJLP
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Daftar klasifikasi rincian tugas dan pekerjaan per seksi untuk logbook harian tenaga PJLP Sudin Kominfotik
                    </p>
                </div>

                <div>
                    <button
                        type="button"
                        @click="openAddModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-xl bg-blue-600 text-white shadow-xs hover:bg-blue-500 transition active:scale-95"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Tambah Kategori Baru</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Filter Bar -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Building2 class="w-4 h-4 text-slate-400" />
                    <label class="text-xs font-bold text-slate-700 dark:text-slate-300">Filter Seksi:</label>
                    <select
                        v-model="filterSeksi"
                        @change="applyFilter"
                        class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3"
                    >
                        <option value="">Semua Seksi</option>
                        <option v-for="s in seksiList" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>

                <div class="text-xs text-slate-500 font-medium">
                    Total: <span class="font-bold text-slate-900 dark:text-slate-100">{{ categories.total || 0 }}</span> Kategori
                </div>
            </div>

            <!-- Tabel Kategori -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold uppercase tracking-wider">
                                <th class="py-3.5 px-4 w-12 text-center">No</th>
                                <th class="py-3.5 px-4">Nama Klasifikasi Kategori Tugas</th>
                                <th class="py-3.5 px-4">Seksi Terkait</th>
                                <th class="py-3.5 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!categories.data || categories.data.length === 0">
                                <td colspan="4" class="py-12 text-center text-slate-400">
                                    <Tags class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                    <span>Belum ada kategori aktivitas untuk seksi ini.</span>
                                </td>
                            </tr>
                            <tr
                                v-for="(cat, idx) in categories.data"
                                :key="cat.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition"
                            >
                                <td class="py-3.5 px-4 text-slate-500 font-mono text-center">
                                    {{ idx + 1 }}
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-slate-100">
                                    {{ cat.nama }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 border border-blue-200">
                                        {{ cat.seksi }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openEditModal(cat)"
                                            class="p-1.5 rounded-lg text-slate-600 hover:text-blue-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800 transition"
                                            title="Ubah Kategori"
                                        >
                                            <Edit3 class="w-4 h-4" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteCategory(cat)"
                                            class="p-1.5 rounded-lg text-slate-600 hover:text-rose-600 hover:bg-rose-50 dark:text-slate-300 dark:hover:bg-rose-950/40 transition"
                                            title="Hapus Kategori"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL TAMBAH / EDIT KATEGORI                                 -->
        <!-- ============================================================ -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                            <Tags class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                                {{ editingCategory ? 'Ubah Kategori Tugas' : 'Tambah Kategori Tugas Baru' }}
                            </h3>
                            <p class="text-[11px] text-slate-500">
                                Standarisasi Modul Kinerja PJLP
                            </p>
                        </div>
                    </div>
                    <button @click="showModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Nama Kategori Tugas <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            v-model="form.nama"
                            placeholder="Contoh: Pemeliharaan Jaringan & Router / Peliputan Berita"
                            required
                            class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Seksi Terkait <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.seksi"
                            required
                            class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-2 px-3"
                        >
                            <option v-for="s in seksiList" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="showModal = false"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 rounded-lg"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 text-xs font-bold bg-blue-600 hover:bg-blue-500 text-white rounded-lg shadow-xs disabled:opacity-50"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Kategori' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
