<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ activities: Object, filters: Object, summary: Object, users: Array, sections: Array });
const role = computed(() => usePage().props.auth.user.role);
const isKasi = computed(() => role.value === 'Kasi');
const initial = () => ({
    mode: props.filters.mode,
    bulan: props.filters.bulan,
    start_date: props.filters.start_date,
    end_date: props.filters.end_date,
    seksi: props.filters.seksi || '',
    user_id: props.filters.user_id || '',
    search: props.filters.search || '',
    per_page: props.filters.per_page || 10,
});
const form = ref(initial());
watch(() => props.filters, () => { form.value = initial(); });
const filteredUsers = computed(() => !form.value.seksi || isKasi.value ? props.users : props.users.filter((u) => {
    const key = (value) => (value || '').trim().replace(/^(seksi|subbagian)\s+/i, '').toLocaleLowerCase('id-ID');
    return key(u.seksi) === key(form.value.seksi);
}));
watch(() => form.value.seksi, () => { form.value.user_id = ''; });
const params = (source = form.value) => {
    const values = { mode: source.mode, per_page: source.per_page };
    if (source.mode === 'month') values.bulan = source.bulan;
    else { values.start_date = source.start_date; values.end_date = source.end_date; }
    if (!isKasi.value && source.seksi) values.seksi = source.seksi;
    if (source.user_id) values.user_id = source.user_id;
    if (source.search) values.search = source.search;
    return values;
};
const error = ref('');
const apply = () => {
    error.value = '';
    if (form.value.mode === 'range' && (!form.value.start_date || !form.value.end_date || form.value.end_date < form.value.start_date)) {
        error.value = 'Isi rentang tanggal yang valid.';
        return;
    }
    if (form.value.mode === 'month' && !form.value.bulan) {
        error.value = 'Pilih bulan terlebih dahulu.';
        return;
    }
    router.get(route('activity-download.index'), params(), { preserveState: true, replace: true });
};
const reset = () => router.get(route('activity-download.index'));
const download = () => {
    const query = new URLSearchParams(params(props.filters));
    window.location.href = `${route('activity-download.excel')}?${query}`;
};
const formatDate = (value) => value ? new Date(`${value.slice(0, 10)}T12:00:00`).toLocaleDateString('id-ID') : '-';
const period = computed(() => `${formatDate(props.filters.start_date)} – ${formatDate(props.filters.end_date)}`);
</script>

<template>
    <Head title="Tarik Data Aktivitas PJLP" />
    <AuthenticatedLayout>
        <template #header><h1 class="text-xl font-bold text-slate-900">Tarik Data Aktivitas PJLP</h1><p class="mt-1 text-sm text-slate-500">Lihat dan unduh aktivitas PJLP sesuai cakupan akses Anda.</p></template>
        <div class="space-y-4 p-4 sm:p-6 lg:p-8">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-bold text-slate-900">Filter Data</h2><div class="flex gap-2"><button type="button" @click="reset" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold">Reset Filter</button><button type="button" @click="apply" class="rounded-lg bg-blue-700 px-4 py-2 text-xs font-semibold text-white">Tampilkan Data</button></div></div>
                <p v-if="error" role="alert" class="mt-3 text-sm text-rose-700">{{ error }}</p>
                <p v-if="$page.props.errors && Object.keys($page.props.errors).length" role="alert" class="mt-3 text-sm text-rose-700">{{ Object.values($page.props.errors)[0] }}</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <fieldset class="text-xs"><legend class="mb-2 font-semibold text-slate-600">Periode Data</legend><label class="mr-4"><input v-model="form.mode" type="radio" value="range" /> Rentang Tanggal</label><label><input v-model="form.mode" type="radio" value="month" /> Bulan</label></fieldset>
                    <template v-if="form.mode === 'range'"><label class="text-xs font-semibold text-slate-600">Tanggal Mulai<input v-model="form.start_date" type="date" class="mt-2 block w-full rounded-lg border-slate-300 text-sm" /></label><label class="text-xs font-semibold text-slate-600">Tanggal Selesai<input v-model="form.end_date" type="date" :min="form.start_date" class="mt-2 block w-full rounded-lg border-slate-300 text-sm" /></label></template>
                    <label v-else class="text-xs font-semibold text-slate-600">Bulan<input v-model="form.bulan" type="month" class="mt-2 block w-full rounded-lg border-slate-300 text-sm" /></label>
                    <label class="text-xs font-semibold text-slate-600">Seksi / Unit Kerja<select v-model="form.seksi" :disabled="isKasi" class="mt-2 block w-full rounded-lg border-slate-300 text-sm"><option v-if="!isKasi" value="">Semua Seksi</option><option v-for="section in sections" :key="section" :value="section">{{ section }}</option><option v-if="isKasi && !sections.length" :value="form.seksi">{{ form.seksi || 'Seksi belum ditentukan' }}</option></select></label>
                    <label class="text-xs font-semibold text-slate-600">PJLP (Opsional)<select v-model="form.user_id" class="mt-2 block w-full rounded-lg border-slate-300 text-sm"><option value="">Semua PJLP</option><option v-for="user in filteredUsers" :key="user.id" :value="user.id">{{ user.nama_lengkap || user.name }}</option></select></label>
                </div>
            </section>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><div v-for="card in [{ label: 'Total Aktivitas', value: summary.total }, { label: 'Jumlah PJLP', value: summary.pjlp }, { label: 'Kategori Aktivitas', value: summary.categories }, { label: 'Periode', value: period }]" :key="card.label" class="rounded-xl border border-blue-100 bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">{{ card.label }}</p><strong class="mt-2 block text-lg text-slate-900">{{ card.value }}</strong></div></div>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-5"><div><h2 class="font-bold text-slate-900">Rincian Aktivitas PJLP</h2><p class="text-xs text-slate-500">{{ activities.total }} data sesuai filter</p></div><div class="flex gap-2"><label class="text-xs text-slate-600">Tampilkan <select v-model="form.per_page" @change="apply" class="rounded-lg border-slate-300 text-xs"><option v-for="n in [10,25,50,100]" :key="n" :value="n">{{ n }}</option></select></label><input v-model="form.search" @keyup.enter="apply" aria-label="Cari aktivitas" placeholder="Cari nomor, uraian, kategori..." class="rounded-lg border-slate-300 text-xs" /><button type="button" @click="apply" class="rounded-lg bg-slate-900 px-3 text-xs text-white">Cari</button></div></div>
                <div class="overflow-x-auto"><table class="min-w-[1050px] w-full text-left text-xs"><thead class="bg-slate-50 text-slate-600"><tr><th v-for="heading in ['No','Tanggal','No Aktivitas','Nama PJLP','Seksi','Kategori','Uraian Aktivitas','Lokasi','Nama Unit','Jam Mulai','Jam Selesai','Status','Lampiran']" :key="heading" class="px-3 py-3">{{ heading }}</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="(activity, index) in activities.data" :key="activity.id"><td class="px-3 py-3">{{ (activities.from || 1) + index }}</td><td class="px-3 py-3 whitespace-nowrap">{{ formatDate(activity.tanggal) }}</td><td class="px-3 py-3">{{ activity.no || '-' }}</td><td class="px-3 py-3">{{ activity.user?.nama_lengkap || activity.user?.name }}</td><td class="px-3 py-3">{{ activity.user?.seksi || '-' }}</td><td class="px-3 py-3">{{ activity.kategori || '-' }}</td><td class="max-w-xs whitespace-pre-wrap break-words px-3 py-3">{{ activity.uraian }}</td><td class="px-3 py-3">{{ activity.lokasi || '-' }}</td><td class="px-3 py-3">{{ activity.nama_unit || '-' }}</td><td class="px-3 py-3">{{ activity.jam_mulai || '-' }}</td><td class="px-3 py-3">{{ activity.jam_selesai || '-' }}</td><td class="px-3 py-3">{{ activity.status_validasi || '-' }}</td><td class="px-3 py-3"><div v-if="activity.attachment_links?.length" class="flex flex-wrap gap-1"><a v-for="(url, fileIndex) in activity.attachment_links" :key="url" :href="url" target="_blank" rel="noopener noreferrer" class="rounded bg-blue-50 px-2 py-1 font-semibold text-blue-700 hover:underline">Lampiran {{ fileIndex + 1 }}</a></div><span v-else>-</span></td></tr><tr v-if="!activities.data.length"><td colspan="13" class="px-3 py-8 text-center text-slate-500">Tidak ada aktivitas untuk filter ini.</td></tr></tbody></table></div>
                <div class="flex flex-wrap items-center justify-between gap-2 p-4 text-xs text-slate-500"><span>Menampilkan {{ activities.from || 0 }}–{{ activities.to || 0 }} dari {{ activities.total }} data</span><div class="flex items-center gap-3"><button type="button" :disabled="!activities.prev_page_url" @click="router.get(activities.prev_page_url, {}, { preserveScroll: true })" class="disabled:opacity-30">Sebelumnya</button><span>{{ activities.current_page }} / {{ activities.last_page }}</span><button type="button" :disabled="!activities.next_page_url" @click="router.get(activities.next_page_url, {}, { preserveScroll: true })" class="disabled:opacity-30">Berikutnya</button></div></div>
            </section>
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><div><h2 class="font-bold text-slate-900">Unduh ke Excel</h2><p class="mt-1 text-xs text-slate-600">Ekspor {{ summary.total }} aktivitas sesuai filter yang sudah ditampilkan, termasuk semua halaman.</p></div><button type="button" @click="download" class="rounded-lg bg-emerald-700 px-5 py-3 text-sm font-semibold text-white">Download Excel (.xlsx)</button></section>
        </div>
    </AuthenticatedLayout>
</template>
