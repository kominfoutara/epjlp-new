<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    month: String,
    today: String,
    selectedDate: String,
    nodes: Array,
    activities: Object,
});
const page = usePage();
const user = computed(() => page.props.auth.user);
const isPjlp = computed(() => user.value.role === 'PJLP');
const scopeLabel = computed(() => isPjlp.value ? 'Aktivitas saya' : user.value.role === 'Kasi'
    ? `PJLP seksi ${user.value.seksi || 'belum ditentukan'}` : 'PJLP seluruh seksi');
const formatDate = (date, options) => new Date(`${date}T12:00:00`).toLocaleDateString('id-ID', options);
const monthLabel = computed(() => formatDate(`${props.month}-01`, { month: 'long', year: 'numeric' }));
const nodesByDate = computed(() => {
    const result = {};
    for (const node of props.nodes) (result[node.date] ??= []).push(node);
    return result;
});
const days = computed(() => {
    const [year, month] = props.month.split('-').map(Number);
    const offset = (new Date(year, month - 1, 1).getDay() + 6) % 7;
    const count = new Date(year, month, 0).getDate();
    const cells = Array.from({ length: offset }, () => null);
    for (let day = 1; day <= count; day++) {
        const date = `${props.month}-${String(day).padStart(2, '0')}`;
        cells.push({ day, date, nodes: nodesByDate.value[date] || [] });
    }
    while (cells.length % 7) cells.push(null);
    return cells;
});
const total = computed(() => props.nodes.reduce((sum, node) => sum + node.count, 0));
const visitMonth = (month) => {
    if (/^\d{4}-\d{2}$/.test(month)) router.get(route('reminder.index'), { bulan: month });
};
const moveMonth = (direction) => {
    const [year, month] = props.month.split('-').map(Number);
    const next = new Date(year, month - 1 + direction, 1);
    visitMonth(`${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`);
};
const selectDate = (tanggal) => router.get(route('reminder.index'), { bulan: props.month, tanggal }, { preserveScroll: true });
</script>

<template>
    <Head title="Reminder Aktivitas" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-bold text-slate-900">Reminder Aktivitas</h1>
            <p class="mt-1 text-sm text-slate-500">{{ scopeLabel }} · Kalender input aktivitas bulanan</p>
        </template>

        <div class="p-4 sm:p-6 lg:p-8 space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <div class="p-4 sm:p-5 flex flex-wrap items-center justify-between gap-4 border-b border-slate-200">
                    <div>
                        <h2 class="text-lg font-bold capitalize text-slate-900">{{ monthLabel }}</h2>
                        <p class="text-sm text-slate-500">{{ total }} aktivitas tercatat · Pilih tanggal untuk melihat rincian.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" aria-label="Bulan sebelumnya" class="rounded-lg border px-3 py-2 hover:bg-slate-50" @click="moveMonth(-1)">‹</button>
                        <input type="month" aria-label="Pilih bulan" :value="month" class="rounded-lg border-slate-300 text-sm" @change="visitMonth($event.target.value)" />
                        <button type="button" aria-label="Bulan berikutnya" class="rounded-lg border px-3 py-2 hover:bg-slate-50" @click="moveMonth(1)">›</button>
                        <button type="button" class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50" @click="visitMonth(today.slice(0, 7))">Bulan ini</button>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 px-5 py-3 text-xs text-slate-600">
                    <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Sudah ada aktivitas</span>
                    <template v-if="isPjlp">
                        <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Belum ada aktivitas</span>
                        <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-slate-300"></span>Tanggal mendatang (kosong)</span>
                    </template>
                </div>
                <div class="overflow-x-auto">
                    <div class="min-w-[700px]">
                        <div class="grid grid-cols-7 bg-slate-50 border-y border-slate-200">
                            <div v-for="day in ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']" :key="day" class="p-3 text-center text-xs font-semibold text-slate-500">{{ day }}</div>
                        </div>
                        <div class="grid grid-cols-7 gap-px bg-slate-200">
                            <template v-for="(day, index) in days" :key="index">
                                <button v-if="day" type="button" :aria-label="`${day.date}, ${day.nodes.reduce((sum, node) => sum + node.count, 0)} aktivitas`"
                                    :aria-pressed="selectedDate === day.date" :aria-current="day.date === today ? 'date' : undefined"
                                    class="min-h-[140px] p-3 text-left flex flex-col gap-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600 focus-visible:-outline-offset-2"
                                    :class="selectedDate === day.date ? 'bg-blue-50 ring-2 ring-inset ring-blue-500' : 'bg-white hover:bg-slate-50'"
                                    @click="selectDate(day.date)">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold" :class="day.date === today ? 'bg-blue-600 text-white' : 'text-slate-700'">{{ day.day }}</span>
                                    <span v-for="node in day.nodes.slice(0, 3)" :key="node.user_id" class="block w-full rounded-md bg-emerald-50 px-2 py-1 text-xs text-emerald-800 border border-emerald-100" :title="`${node.name} · ${node.seksi || 'Tanpa seksi'} · ${node.count} aktivitas`">
                                        <span class="block truncate font-medium">● {{ isPjlp ? 'Sudah input' : node.name }}</span>
                                        <span>{{ node.count }} aktivitas</span>
                                    </span>
                                    <span v-if="day.nodes.length > 3" class="text-xs font-medium text-blue-600">+{{ day.nodes.length - 3 }} PJLP lainnya</span>
                                    <span v-if="isPjlp && !day.nodes.length" class="rounded-md px-2 py-1 text-xs" :class="day.date <= today ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-500'">
                                        ● {{ day.date <= today ? 'Belum ada aktivitas' : 'Tanggal mendatang' }}
                                    </span>
                                </button>
                                <div v-else class="bg-slate-50 min-h-[140px]"></div>
                            </template>
                        </div>
                    </div>
                </div>
                <p class="px-5 py-3 text-xs text-slate-500">Penanda berdasarkan tanggal aktivitas, termasuk aktivitas menunggu, disetujui, dan ditolak. <span v-if="isPjlp">Tanggal kosong termasuk akhir pekan; penanda ini bukan status ketidakhadiran atau pelanggaran.</span></p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-live="polite">
                <h2 class="text-base font-bold text-slate-900">{{ formatDate(selectedDate, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ activities.total }} aktivitas · Tampilan pemantauan</p>
                <p v-if="!activities.total" class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-500">Belum ada aktivitas pada tanggal ini{{ isPjlp ? '.' : ' dalam cakupan Anda.' }}</p>
                <div v-else class="mt-4 divide-y divide-slate-100">
                    <article v-for="activity in activities.data" :key="activity.id" class="py-4 first:pt-0">
                        <div class="flex flex-wrap justify-between gap-2">
                            <h3 class="text-sm font-semibold text-slate-900">{{ activity.user.nama_lengkap || activity.user.name }} <span class="font-normal text-slate-500">· {{ activity.user.seksi || 'Tanpa seksi' }}</span></h3>
                            <span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ activity.status_validasi }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ activity.jam_mulai }} – {{ activity.jam_selesai }} · {{ activity.kategori }}</p>
                        <p class="mt-2 whitespace-pre-wrap break-words text-sm text-slate-700">{{ activity.uraian }}</p>
                    </article>
                </div>
                <div v-if="activities.last_page > 1" class="mt-4 flex items-center gap-4 text-sm">
                    <Link v-if="activities.prev_page_url" :href="activities.prev_page_url" preserve-scroll class="text-blue-600">Sebelumnya</Link>
                    <span>{{ activities.current_page }} / {{ activities.last_page }}</span>
                    <Link v-if="activities.next_page_url" :href="activities.next_page_url" preserve-scroll class="text-blue-600">Berikutnya</Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
