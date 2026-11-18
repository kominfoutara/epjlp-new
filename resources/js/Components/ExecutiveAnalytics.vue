<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Users, CalendarCheck, Network, FileText, ChartColumn, TrendingUp, Trophy } from 'lucide-vue-next';

const props = defineProps({ data: { type: Object, required: true } });
const number = (value) => Number(value || 0).toLocaleString('id-ID');
const percent = (count, total) => total ? Math.round(count / total * 1000) / 10 : 0;
const stats = computed(() => props.data.activityStats);
const total = computed(() => stats.value.total_month);
const colors = ['#1685f8', '#14b8a6', '#9561ef', '#fb923c', '#f43f86'];
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const statuses = computed(() => [
    { name: 'Disetujui', count: stats.value.disetujui, color: '#10b981' },
    { name: 'Ditolak', count: stats.value.ditolak, color: '#f43f5e' },
    { name: 'Menunggu', count: stats.value.menunggu, color: '#f59e0b' },
    { name: 'Lainnya', count: Math.max(0, total.value - stats.value.disetujui - stats.value.ditolak - stats.value.menunggu), color: '#cbd5e1' },
]);
const donut = computed(() => {
    if (!total.value) return '#e2e8f0';
    let start = 0;
    return `conic-gradient(${statuses.value.map((status) => {
        const end = start + status.count / total.value * 100;
        const segment = `${status.color} ${start}% ${end}%`;
        start = end;
        return segment;
    }).join(',')})`;
});
const maxTrend = computed(() => Math.max(1, ...props.data.trend.map((item) => item.count)));
const points = computed(() => props.data.trend.map((item, index) => ({
    ...item, x: 34 + index * 38, y: 172 - item.count / maxTrend.value * 132,
})));
const line = computed(() => points.value.map((point) => `${point.x},${point.y}`).join(' '));
const maxCategory = computed(() => Math.max(1, ...props.data.categories.map((item) => item.count)));
const cards = computed(() => [
    { title: 'Total Akun Pengguna', icon: Users, color: 'blue', value: number(props.data.userBreakdown.total), unit: 'Akun', badge: `${number(props.data.userBreakdown.active)} aktif`, note: `${props.data.userBreakdown.id} ID · ${props.data.userBreakdown.astik} Astik · ${props.data.userBreakdown.kip} KIP`, link: route('users.index'), action: 'Kelola pengguna' },
    { title: 'Presensi PJLP Hari Ini', icon: CalendarCheck, color: 'green', value: number(props.data.scanCount), unit: `/ ${number(props.data.todayPresence.total_pjlp)} PJLP`, badge: `${percent(props.data.scanCount, props.data.todayPresence.total_pjlp)}%`, note: `${props.data.today} · Berdasarkan scan masuk/keluar`, link: route('attendance.index'), action: 'Monitoring presensi' },
    { title: 'Struktur Unit Kerja', icon: Network, color: 'violet', value: number(props.data.seksiOverview.length), unit: 'Seksi / Satker', badge: `${props.data.seksiOverview.filter((s) => s.is_active).length} aktif`, note: `Total PJLP terdaftar: ${number(props.data.todayPresence.total_pjlp)} orang`, link: route('seksi.index'), action: 'Master satker' },
    { title: 'Aktivitas Bulanan', icon: FileText, color: 'orange', value: number(total.value), unit: 'Aktivitas', badge: `${number(stats.value.menunggu)} menunggu`, note: `Seluruh periode: ${number(stats.value.total_all_time)} aktivitas`, link: route('activity.index', { bulan: props.data.period }), action: 'Audit aktivitas' },
]);
</script>

<template>
    <div class="executive space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in cards" :key="card.title" class="panel p-4" :class="card.color">
                <div class="flex items-start gap-3">
                    <div class="icon-box"><component :is="card.icon" class="h-6 w-6" /></div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-slate-700">{{ card.title }}</p>
                        <div class="mt-2 flex flex-wrap items-baseline gap-1.5"><strong class="text-2xl font-extrabold tracking-tight text-slate-950">{{ card.value }}</strong><span class="text-xs text-slate-600">{{ card.unit }}</span></div>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2"><span class="badge">{{ card.badge }}</span></div>
                <p class="mt-3 text-[11px] text-slate-500">{{ card.note }}</p>
                <Link :href="card.link" class="mt-3 block border-t border-slate-100 pt-3 text-right text-xs font-semibold text-slate-700 hover:text-blue-600">{{ card.action }} →</Link>
            </article>
        </div>

        <div class="grid gap-4 xl:grid-cols-12">
            <section class="panel p-5 xl:col-span-4">
                <h2 class="heading"><ChartColumn class="h-5 w-5 text-blue-500" />Distribusi Aktivitas per Kategori</h2>
                <p class="subtitle">{{ data.periodLabel }} · {{ number(total) }} aktivitas, seluruh role</p>
                <div v-if="data.categories.length" class="mt-5 max-h-64 space-y-4 overflow-y-auto pr-2">
                    <div v-for="(category, index) in data.categories" :key="category.name">
                        <div class="mb-1.5 flex justify-between gap-3 text-xs"><span class="break-words text-slate-700">{{ category.name }}</span><strong class="shrink-0 text-slate-700">{{ number(category.count) }} <span class="font-normal text-slate-500">({{ percent(category.count, total) }}%)</span></strong></div>
                        <div class="h-3 rounded bg-slate-100"><div class="h-3 rounded" :style="{ width: `${category.count / maxCategory * 100}%`, background: colors[index % colors.length] }"></div></div>
                    </div>
                </div>
                <p v-else class="empty">Belum ada aktivitas pada periode ini.</p>
            </section>

            <section class="panel p-5 xl:col-span-5">
                <h2 class="heading"><TrendingUp class="h-5 w-5 text-blue-500" />Tren Aktivitas Tahun {{ data.period.slice(0, 4) }}</h2>
                <p class="subtitle">Jumlah aktivitas per bulan · bukan akumulasi</p>
                <svg viewBox="0 0 480 210" class="mt-4 w-full" role="img" :aria-label="`Tren aktivitas ${data.period.slice(0, 4)}`">
                    <defs><linearGradient id="executive-trend-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#60a5fa" stop-opacity="0.5" /><stop offset="100%" stop-color="#60a5fa" stop-opacity="0.03" /></linearGradient></defs>
                    <g v-for="tick in [0, 1, 2, 3, 4]" :key="tick"><line x1="34" x2="452" :y1="172 - tick * 33" :y2="172 - tick * 33" stroke="#e2e8f0" /><text x="29" :y="176 - tick * 33" text-anchor="end" font-size="9" fill="#64748b">{{ Math.round(maxTrend * tick / 4) }}</text></g>
                    <polygon :points="`34,172 ${line} 452,172`" fill="url(#executive-trend-fill)" />
                    <polyline :points="line" fill="none" stroke="#1685f8" stroke-width="2.5" />
                    <g v-for="point in points" :key="point.month"><circle :cx="point.x" :cy="point.y" r="4" fill="#1685f8" stroke="white" stroke-width="1.5"><title>{{ monthNames[point.month - 1] }}: {{ number(point.count) }} aktivitas</title></circle><text :x="point.x" :y="point.y - 9" text-anchor="middle" font-size="9" fill="#334155">{{ point.count }}</text><text :x="point.x" y="195" text-anchor="middle" font-size="10" fill="#64748b">{{ monthNames[point.month - 1] }}</text></g>
                </svg>
                <p v-if="!data.trend.some((item) => item.count)" class="text-center text-xs text-slate-500">Belum ada aktivitas pada tahun ini.</p>
            </section>

            <section class="panel p-5 xl:col-span-3">
                <h2 class="heading"><FileText class="h-5 w-5 text-violet-500" />Status Validasi</h2>
                <p class="subtitle">{{ data.periodLabel }}</p>
                <div class="mx-auto my-5 flex h-32 w-32 items-center justify-center rounded-full" :style="{ background: donut }" role="img" aria-label="Distribusi status validasi; rincian tersedia di bawah">
                    <div class="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-white"><strong class="text-2xl text-slate-900">{{ number(total) }}</strong><span class="text-xs text-slate-500">Aktivitas</span></div>
                </div>
                <div class="space-y-2"><div v-for="status in statuses" :key="status.name" class="flex items-center justify-between gap-2 text-xs"><span class="flex items-center gap-2 text-slate-600"><i class="h-2 w-2 rounded-full" :style="{ background: status.color }"></i>{{ status.name }}</span><strong>{{ number(status.count) }} ({{ percent(status.count, total) }}%)</strong></div></div>
                <p class="mt-4 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">{{ total ? `${percent(stats.menunggu, total)}% aktivitas periode ini masih menunggu validasi.` : 'Belum ada aktivitas untuk divalidasi.' }}</p>
            </section>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <section v-for="ranking in [{ title: 'Top 5 Kategori Aktivitas', items: data.categories.slice(0, 5), note: 'Berdasarkan jumlah aktivitas per kategori' }, { title: 'Top 5 Seksi', items: data.topSections, note: 'Berdasarkan jumlah aktivitas, bukan penilaian kualitas kerja' }]" :key="ranking.title" class="panel p-5">
                <h2 class="heading"><Trophy class="h-5 w-5 text-amber-500" />{{ ranking.title }}</h2><p class="subtitle">{{ ranking.note }} · {{ data.periodLabel }}</p>
                <ol class="mt-3 divide-y divide-slate-100"><li v-for="(item, index) in ranking.items" :key="item.name" class="flex items-center gap-3 py-2.5 text-xs"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-50 font-bold text-blue-600">{{ index + 1 }}</span><span class="flex-1 text-slate-700">{{ item.name }}</span><strong class="text-slate-900">{{ number(item.count) }}</strong></li></ol>
                <p v-if="!ranking.items.length" class="empty">Belum ada data pada periode ini.</p>
            </section>
        </div>
    </div>
</template>

<style scoped>
.panel { border: 1px solid #dfe8f5; border-radius: 14px; background: linear-gradient(145deg, #fff 65%, #f7faff); box-shadow: 0 2px 5px #1e40af08; }
.heading { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #0f172a; }
.subtitle { margin-top: 5px; font-size: 11px; color: #64748b; }
.icon-box { padding: 9px; border-radius: 10px; background: #eff6ff; color: #1685f8; }
.badge { padding: 3px 9px; border-radius: 20px; background: #eff6ff; color: #1d4ed8; font-size: 10px; font-weight: 600; }
.green .icon-box, .green .badge { background: #dcfce7; color: #15803d; }
.violet .icon-box, .violet .badge { background: #f3e8ff; color: #7e22ce; }
.orange .icon-box, .orange .badge { background: #fff7ed; color: #c2410c; }
.empty { padding: 35px 0; text-align: center; font-size: 12px; color: #64748b; }
</style>
