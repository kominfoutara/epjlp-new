<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import {
    Printer,
    FileSpreadsheet,
    FileText,
    ArrowLeft,
    Calendar,
    Building2,
    CheckCircle2,
    AlertCircle,
    UserCheck,
    Clock,
    Filter,
    Wifi,
    RefreshCw,
    Radio,
    X
} from 'lucide-vue-next';

const props = defineProps({
    recap: Array,
    filters: Object,
    seksiList: Array,
    isPjlp: Boolean,
    defaultFingerprintIp: String,
});

const filterPeriode = ref(props.filters.periode || new Date().toISOString().slice(0, 7));
const filterSeksi = ref(props.filters.seksi || '');

// Sync & Connection Test State
const isSyncing = ref(false);
const isTesting = ref(false);
const showSyncModal = ref(false);
const machineIp = ref(props.defaultFingerprintIp || '');
const connectionStatus = ref(null);

// Inisialisasi bulan dan tahun dari filter periode aktif
const parsePeriodeMonth = () => {
    if (filterPeriode.value && filterPeriode.value.includes('-')) {
        return filterPeriode.value.split('-')[1];
    }
    return String(new Date().getMonth() + 1).padStart(2, '0');
};
const parsePeriodeYear = () => {
    if (filterPeriode.value && filterPeriode.value.includes('-')) {
        return filterPeriode.value.split('-')[0];
    }
    return String(new Date().getFullYear());
};

const syncMonth = ref(parsePeriodeMonth());
const syncYear = ref(parsePeriodeYear());
const availableYears = ref([2024, 2025, 2026, 2027]);

const monthList = [
    { value: 'all', label: 'Semua Bulan' },
    { value: '01', label: 'Januari' },
    { value: '02', label: 'Februari' },
    { value: '03', label: 'Maret' },
    { value: '04', label: 'April' },
    { value: '05', label: 'Mei' },
    { value: '06', label: 'Juni' },
    { value: '07', label: 'Juli' },
    { value: '08', label: 'Agustus' },
    { value: '09', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const getSelectedPeriodLabel = () => {
    if (syncMonth.value === 'all' && syncYear.value === 'all') {
        return 'Semua Periode';
    }
    const m = monthList.find(x => x.value === syncMonth.value);
    const mLabel = m ? m.label : syncMonth.value;
    if (syncMonth.value === 'all') {
        return `Semua Bulan (${syncYear.value})`;
    }
    if (syncYear.value === 'all') {
        return `${mLabel} (Semua Tahun)`;
    }
    return `${mLabel} ${syncYear.value}`;
};

const openSyncModal = () => {
    // Sesuaikan pilihan bulan & tahun dengan filter yang sedang dilihat user
    syncMonth.value = parsePeriodeMonth();
    syncYear.value = parsePeriodeYear();
    connectionStatus.value = null;
    showSyncModal.value = true;
};

const triggerSync = () => {
    isSyncing.value = true;
    router.post(route('attendance.sync'), {
        ip: machineIp.value || undefined,
        bulan: syncMonth.value,
        tahun: syncYear.value,
    }, {
        preserveState: false,
        onSuccess: (page) => {
            if (page.props.filters?.periode) {
                filterPeriode.value = page.props.filters.periode;
            }
        },
        onFinish: () => {
            isSyncing.value = false;
            showSyncModal.value = false;
        }
    });
};

const testConnection = async () => {
    isTesting.value = true;
    connectionStatus.value = null;
    try {
        const response = await fetch(route('attendance.test-connection'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ ip: machineIp.value || undefined }),
        });
        const data = await response.json();
        connectionStatus.value = data;
    } catch (e) {
        connectionStatus.value = {
            success: false,
            message: 'Terjadi kesalahan jaringan saat menguji koneksi.',
        };
    } finally {
        isTesting.value = false;
    }
};

const applyFilter = () => {
    router.get(route('attendance.recap'), {
        periode: filterPeriode.value,
        seksi: filterSeksi.value || undefined,
    }, { preserveState: true, replace: true });
};

const printPage = () => {
    window.print();
};

const exportRecapExcel = () => {
    const params = new URLSearchParams({
        periode: filterPeriode.value,
        seksi: filterSeksi.value || '',
    });
    window.location.href = `${route('attendance.rekap.export.excel')}?${params.toString()}`;
};

const exportRecapPdf = () => {
    const params = new URLSearchParams({
        periode: filterPeriode.value,
        seksi: filterSeksi.value || '',
    });
    window.open(`${route('attendance.rekap.export.pdf')}?${params.toString()}`, '_blank');
};

const currentDateFormatted = computed(() => {
    const d = new Date();
    const months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
});

const periodeFormatted = computed(() => {
    if (!filterPeriode.value) return '-';
    const parts = filterPeriode.value.split('-');
    if (parts.length < 2) return filterPeriode.value;
    const months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    const monthIdx = parseInt(parts[1], 10) - 1;
    return `${months[monthIdx] || parts[1]} ${parts[0]}`;
});

// Summary Stats
const totalEmployees = computed(() => props.recap?.length || 0);
const totalHadirWfo = computed(() => props.recap?.reduce((acc, curr) => acc + (Number(curr.total_hadir) || 0), 0) || 0);
const totalDinasLuar = computed(() => props.recap?.reduce((acc, curr) => acc + (Number(curr.total_dl) || 0), 0) || 0);
const totalIzinCuti = computed(() => props.recap?.reduce((acc, curr) => acc + (Number(curr.total_cuti) || 0) + (Number(curr.total_izin) || 0) + (Number(curr.total_sakit) || 0), 0) || 0);
const totalAlpa = computed(() => props.recap?.reduce((acc, curr) => acc + (Number(curr.total_alpa) || 0), 0) || 0);
</script>

<template>
    <Head title="Rekapitulasi Absensi Bulanan PJLP" />

    <AuthenticatedLayout>
        <!-- Header Layar Normal -->
        <template #header>
            <div class="print:hidden flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                            Dokumen Resmi Presensi
                        </span>
                        <span class="text-xs text-slate-400">• Periode: {{ periodeFormatted }}</span>
                    </div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                        Rekapitulasi Presensi Bulanan
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Akumulasi hari kerja, dinas luar, cuti, sakit, dan izin pegawai PJLP sesuai standar Pergub DKI No. 125/2019
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <button
                        type="button"
                        @click="exportRecapExcel"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl border border-emerald-300 bg-white hover:bg-emerald-50 text-emerald-700 shadow-xs transition"
                        title="Unduh Rekapitulasi Presensi Bulanan dalam format Excel"
                    >
                        <FileSpreadsheet class="w-4 h-4 text-emerald-600" />
                        <span>Unduh Excel</span>
                    </button>

                    <button
                        type="button"
                        @click="exportRecapPdf"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl border border-rose-300 bg-white hover:bg-rose-50 text-rose-700 shadow-xs transition"
                        title="Unduh Rekapitulasi Presensi Bulanan dalam format PDF"
                    >
                        <FileText class="w-4 h-4 text-rose-600" />
                        <span>Unduh PDF</span>
                    </button>

                    <!-- Tombol Tarik Mesin (Khusus Admin/Kasi/Kasudin) -->
                    <button
                        v-if="!isPjlp"
                        type="button"
                        @click="openSyncModal"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl bg-blue-600 hover:bg-blue-700 text-white shadow-xs transition"
                        title="Tarik data presensi dari mesin fingerprint untuk bulan ini"
                    >
                        <Wifi class="w-4 h-4" />
                        <span>Tarik Mesin</span>
                    </button>

                    <button
                        type="button"
                        @click="printPage"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 shadow-xs transition"
                    >
                        <Printer class="w-4 h-4 text-blue-600" />
                        <span>Cetak Naskah Dinas</span>
                    </button>
                    <Link
                        :href="route('attendance.index')"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl bg-blue-600 text-white shadow-xs hover:bg-blue-500 transition"
                    >
                        <span>Presensi Harian</span>
                        <ArrowLeft class="w-4 h-4 rotate-180" />
                    </Link>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6 print:max-w-none print:w-full print:m-0 print:p-0 print:space-y-4">
            <!-- ============================================================ -->
            <!-- KOP SURAT RESMI PEMPROV DKI (HANYA MUNCUL SAAT DICETAK)       -->
            <!-- ============================================================ -->
            <div class="hidden print:block mb-6 text-black print:w-full">
                <div class="flex items-center justify-between pb-3 border-b-4 border-double border-black">
                    <div class="w-20 h-20 flex-shrink-0 flex items-center justify-center">
                        <img
                            src="/images/logo-jakarta-utara.png"
                            alt="Lambang Jakarta Utara"
                            class="w-16 h-16 object-contain"
                        />
                    </div>
                    <div class="text-center flex-1 px-4 leading-tight">
                        <div class="text-sm font-bold tracking-wider uppercase">Pemerintah Provinsi Daerah Khusus Ibukota Jakarta</div>
                        <div class="text-base font-extrabold uppercase">Suku Dinas Komunikasi, Informatika dan Statistik</div>
                        <div class="text-lg font-black tracking-wide uppercase">Kota Administrasi Jakarta Utara</div>
                        <div class="text-[10px] mt-1">
                            Jl. Laksamana Yos Sudarso No. 27-29, Kebon Bawang, Tanjung Priok, Jakarta Utara 14320<br />
                            Telepon: (021) 4301234 • Faksimile: (021) 4301235 • Laman: utara.jakarta.go.id
                        </div>
                    </div>
                    <div class="w-20 h-20 flex-shrink-0"></div>
                </div>

                <div class="text-center mt-5 mb-4">
                    <h2 class="text-sm font-black uppercase underline tracking-wide">
                        DAFTAR REKAPITULASI PRESENSI & KINERJA BULANAN PJLP
                    </h2>
                    <div class="text-xs font-semibold mt-1">
                        PERIODE: {{ periodeFormatted.toUpperCase() }}
                        <span v-if="filterSeksi"> • SEKSI: {{ filterSeksi.toUpperCase() }}</span>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- RINGKASAN METRIK (PRINT HIDDEN)                              -->
            <!-- ============================================================ -->
            <div class="print:hidden grid grid-cols-2 sm:grid-cols-5 gap-3.5">
                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Pegawai</div>
                    <div class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-1">{{ totalEmployees }}</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Pegawai PJLP</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Total Hadir (WFO)</div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ totalHadirWfo }}</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Hari Kehadiran Fisik</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Dinas Luar (DL)</div>
                    <div class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1">{{ totalDinasLuar }}</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Penugasan Lapangan</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Izin / Cuti / Sakit</div>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ totalIzinCuti }}</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Keterangan Resmi</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider">Tanpa Keterangan (Alpa)</div>
                    <div class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">{{ totalAlpa }}</div>
                    <div class="text-[10px] text-slate-400 mt-0.5">Ketidakhadiran</div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- FILTER TOOLBAR (PRINT HIDDEN)                                -->
            <!-- ============================================================ -->
            <div class="print:hidden bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <Calendar class="w-4 h-4 text-slate-400" />
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300">Periode:</label>
                        <input
                            type="month"
                            v-model="filterPeriode"
                            @change="applyFilter"
                            class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3"
                        />
                    </div>

                    <div v-if="!isPjlp" class="flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-slate-400" />
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300">Seksi SKPD:</label>
                        <select
                            v-model="filterSeksi"
                            @change="applyFilter"
                            class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3"
                        >
                            <option value="">Seluruh Seksi Kominfotik</option>
                            <option v-for="s in seksiList" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                </div>

                <div class="text-xs text-slate-500 font-medium">
                    Menampilkan <span class="font-bold text-slate-900 dark:text-slate-100">{{ recap?.length || 0 }}</span> pegawai
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- TABEL REKAPITULASI RESMI                                     -->
            <!-- ============================================================ -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden print:border print:border-black print:shadow-none print:rounded-none print:overflow-visible print:w-full">
                <div class="hidden md:block overflow-x-auto print:block print:overflow-visible">
                    <table class="w-full text-left border-collapse text-xs print:text-[10px] print:w-full">
                        <thead class="print:table-header-group">
                            <tr class="bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold uppercase tracking-wider print:bg-slate-200 print:text-black print:border-b print:border-black">
                                <th class="py-3 px-3 w-10 text-center border-r border-slate-200 dark:border-slate-700 print:border-r print:border-black">No</th>
                                <th class="py-3 px-4 border-r border-slate-200 dark:border-slate-700 print:border-r print:border-black">Nama Lengkap & NIK / Posisi</th>
                                <th class="py-3 px-3 text-center border-r border-slate-200 dark:border-slate-700 print:border-r print:border-black">PIN Mesin</th>
                                <th class="py-3 px-3 border-r border-slate-200 dark:border-slate-700 print:border-r print:border-black">Seksi Penempatan</th>
                                <th class="py-3 px-3 text-center font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50/50 dark:bg-emerald-950/20 border-r border-slate-200 dark:border-slate-700 print:border-r print:border-black print:text-black">
                                    Hadir WFO
                                </th>
                                <th class="py-3 px-3 text-center font-bold text-blue-700 dark:text-blue-400 bg-blue-50/50 dark:bg-blue-950/20 border-r border-slate-200 dark:border-slate-700 print:border-r print:border-black print:text-black">
                                    Dinas Luar
                                </th>
                                <th class="py-3 px-3 text-center font-bold text-sky-700 dark:text-sky-400 border-r border-slate-200 dark:border-slate-700 print:border-r print:border-black print:text-black">
                                    Sakit
                                </th>
                                <th class="py-3 px-3 text-center font-bold text-amber-700 dark:text-amber-400 border-r border-slate-200 dark:border-slate-700 print:border-r print:border-black print:text-black">
                                    Cuti
                                </th>
                                <th class="py-3 px-3 text-center font-bold text-purple-700 dark:text-purple-400 border-r border-slate-200 dark:border-slate-700 print:border-r print:border-black print:text-black">
                                    Izin
                                </th>
                                <th class="py-3 px-3 text-center font-bold text-rose-700 dark:text-rose-400 bg-rose-50/50 dark:bg-rose-950/20 print:text-black">
                                    Alpa
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 print:divide-black">
                            <tr v-if="!recap || recap.length === 0">
                                <td colspan="10" class="py-12 text-center text-slate-400 print:text-black print:border-b print:border-black">
                                    Belum ada catatan rekapitulasi pada periode {{ periodeFormatted }}.
                                </td>
                            </tr>
                            <tr
                                v-for="(item, idx) in recap"
                                :key="item.user_id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 print:hover:bg-transparent print:break-inside-avoid"
                            >
                                <td class="py-2.5 px-3 text-center text-slate-500 font-mono border-r border-slate-100 dark:border-slate-800 print:border-r print:border-b print:border-black print:text-black">
                                    {{ idx + 1 }}
                                </td>
                                <td class="py-2.5 px-4 font-semibold text-slate-900 dark:text-slate-100 border-r border-slate-100 dark:border-slate-800 print:border-r print:border-b print:border-black print:text-black">
                                    {{ item.nama_lengkap }}
                                    <div class="text-[10px] font-normal text-slate-500 dark:text-slate-400 print:text-black">
                                        {{ item.posisi || 'Tenaga Pendukung PJLP' }}
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-center font-mono text-slate-600 dark:text-slate-300 border-r border-slate-100 dark:border-slate-800 print:border-r print:border-b print:border-black print:text-black">
                                    {{ item.pin || '-' }}
                                </td>
                                <td class="py-2.5 px-3 text-slate-700 dark:text-slate-300 border-r border-slate-100 dark:border-slate-800 print:border-r print:border-b print:border-black print:text-black">
                                    {{ item.seksi || 'Sudin Kominfotik' }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50/30 dark:bg-emerald-950/10 border-r border-slate-100 dark:border-slate-800 print:border-r print:border-b print:border-black print:text-black">
                                    {{ item.total_hadir || 0 }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-bold text-blue-700 dark:text-blue-400 bg-blue-50/30 dark:bg-blue-950/10 border-r border-slate-100 dark:border-slate-800 print:border-r print:border-b print:border-black print:text-black">
                                    {{ item.total_dl || 0 }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-semibold text-slate-700 dark:text-slate-300 border-r border-slate-100 dark:border-slate-800 print:border-r print:border-b print:border-black print:text-black">
                                    {{ item.total_sakit || 0 }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-semibold text-slate-700 dark:text-slate-300 border-r border-slate-100 dark:border-slate-800 print:border-r print:border-b print:border-black print:text-black">
                                    {{ item.total_cuti || 0 }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-semibold text-slate-700 dark:text-slate-300 border-r border-slate-100 dark:border-slate-800 print:border-r print:border-b print:border-black print:text-black">
                                    {{ item.total_izin || 0 }}
                                </td>
                                <td class="py-2.5 px-3 text-center font-bold text-rose-700 dark:text-rose-400 bg-rose-50/30 dark:bg-rose-950/10 print:border-b print:border-black print:text-black">
                                    {{ item.total_alpa || 0 }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Adaptive Cards for Attendance Recap -->
                <div class="block md:hidden print:hidden p-3.5 space-y-3 bg-slate-50/50 dark:bg-slate-950/20">
                    <div v-if="!recap || recap.length === 0" class="py-10 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 p-6">
                        <div class="text-xs">Belum ada catatan rekapitulasi pada periode {{ periodeFormatted }}.</div>
                    </div>

                    <div v-for="(item, idx) in recap" :key="'mob-recap-' + item.user_id"
                        class="p-4 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xs space-y-3 transition">
                        <!-- Top Header: Nama & Seksi -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                                    <span class="font-mono text-slate-400 text-[11px]">#{{ idx + 1 }}</span>
                                    <span class="truncate">{{ item.nama_lengkap }}</span>
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                                    {{ item.posisi || 'Tenaga Pendukung PJLP' }}
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-md font-mono text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 flex-shrink-0">
                                PIN: {{ item.pin || '-' }}
                            </span>
                        </div>

                        <!-- Seksi tag -->
                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                            🏢 {{ item.seksi || 'Sudin Kominfotik' }}
                        </div>

                        <!-- 6-Metric Mini Grid -->
                        <div class="grid grid-cols-3 gap-2 pt-1 text-center text-xs">
                            <div class="p-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800">
                                <div class="text-[10px] font-bold text-emerald-700 dark:text-emerald-300">WFO</div>
                                <div class="font-mono font-black text-sm text-emerald-800 dark:text-emerald-200 mt-0.5">{{ item.total_hadir || 0 }}</div>
                            </div>
                            <div class="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200/80 dark:border-blue-800">
                                <div class="text-[10px] font-bold text-blue-700 dark:text-blue-300">DL</div>
                                <div class="font-mono font-black text-sm text-blue-800 dark:text-blue-200 mt-0.5">{{ item.total_dl || 0 }}</div>
                            </div>
                            <div class="p-2 rounded-xl bg-sky-50 dark:bg-sky-950/40 border border-sky-200/80 dark:border-sky-800">
                                <div class="text-[10px] font-bold text-sky-700 dark:text-sky-300">Sakit</div>
                                <div class="font-mono font-black text-sm text-sky-800 dark:text-sky-200 mt-0.5">{{ item.total_sakit || 0 }}</div>
                            </div>
                            <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800">
                                <div class="text-[10px] font-bold text-amber-700 dark:text-amber-300">Cuti</div>
                                <div class="font-mono font-black text-sm text-amber-800 dark:text-amber-200 mt-0.5">{{ item.total_cuti || 0 }}</div>
                            </div>
                            <div class="p-2 rounded-xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200/80 dark:border-purple-800">
                                <div class="text-[10px] font-bold text-purple-700 dark:text-purple-300">Izin</div>
                                <div class="font-mono font-black text-sm text-purple-800 dark:text-purple-200 mt-0.5">{{ item.total_izin || 0 }}</div>
                            </div>
                            <div class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-800">
                                <div class="text-[10px] font-bold text-rose-700 dark:text-rose-300">Alpa</div>
                                <div class="font-mono font-black text-sm text-rose-800 dark:text-rose-200 mt-0.5">{{ item.total_alpa || 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- KOLOM TANDA TANGAN NASKAH DINAS (PRINT ONLY)                 -->
            <!-- ============================================================ -->
            <div class="hidden print:block mt-8 text-black text-xs leading-normal print:break-inside-avoid break-inside-avoid">
                <div class="flex justify-between items-start px-8">
                    <div class="text-center w-64">
                        <p>Mengetahui,</p>
                        <p class="font-bold">Pejabat Pembuat Komitmen (PPK)</p>
                        <p class="text-[10px]">Suku Dinas Kominfotik Kota Adm. Jakarta Utara</p>
                        <div class="h-20"></div>
                        <p class="font-bold underline uppercase">( .................................................... )</p>
                        <p class="text-[10px]">NIP. ....................................................</p>
                    </div>

                    <div class="text-center w-64">
                        <p>Jakarta Utara, {{ currentDateFormatted }}</p>
                        <p class="font-bold">Kepala Seksi / Verifikator Kinerja</p>
                        <p class="text-[10px]">{{ filterSeksi || 'Suku Dinas Kominfotik Jakarta Utara' }}</p>
                        <div class="h-20"></div>
                        <p class="font-bold underline uppercase">( .................................................... )</p>
                        <p class="text-[10px]">NIP. ....................................................</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL TARIK LOG MESIN FINGERPRINT DENGAN FILTER BULAN & TAHUN -->
        <!-- ============================================================ -->
        <div
            v-if="showSyncModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-lg bg-blue-50 text-blue-600">
                            <Radio class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">
                                Sinkronisasi Mesin Absen
                            </h3>
                            <p class="text-[11px] text-slate-500">
                                Protokol SOAP / XML Service Mesin Fingerprint
                            </p>
                        </div>
                    </div>
                    <button @click="showSyncModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Sistem akan mengambil log presensi dari mesin fingerprint kantor Sudin Kominfotik dan menyaringnya sesuai bulan dan tahun yang Anda pilih.
                </p>

                <!-- Filter Bulan & Tahun Penarikan Mesin -->
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                            <Calendar class="w-4 h-4 text-blue-600" />
                            <span>Filter Bulan & Tahun</span>
                        </label>
                        <span class="text-[11px] font-bold text-blue-700 bg-blue-100/70 px-2 py-0.5 rounded-md border border-blue-200">
                            {{ getSelectedPeriodLabel() }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">
                                Pilih Bulan
                            </label>
                            <select
                                v-model="syncMonth"
                                class="w-full rounded-lg border-slate-300 bg-white text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-2 px-2.5 font-medium text-slate-800"
                            >
                                <option v-for="m in monthList" :key="m.value" :value="m.value">
                                    {{ m.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">
                                Pilih Tahun
                            </label>
                            <select
                                v-model="syncYear"
                                class="w-full rounded-lg border-slate-300 bg-white text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-2 px-2.5 font-medium text-slate-800"
                            >
                                <option value="all">Semua Tahun</option>
                                <option v-for="yr in availableYears" :key="yr" :value="String(yr)">
                                    Tahun {{ yr }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-500 leading-snug">
                        Sistem hanya akan menarik dan memproses data kehadiran pada <strong>{{ getSelectedPeriodLabel() }}</strong>.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                        IP Address Mesin Fingerprint
                    </label>
                    <input
                        type="text"
                        v-model="machineIp"
                        :placeholder="defaultFingerprintIp ? `Default server: ${defaultFingerprintIp}` : 'Contoh: 10.130.56.7'"
                        class="w-full rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 p-2.5 font-mono text-slate-900 dark:text-slate-100"
                    />
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                        Konfigurasi aktif: <span class="font-mono font-semibold text-blue-600 dark:text-blue-400">{{ machineIp || defaultFingerprintIp || '10.130.56.7' }}</span> (Port: 80, Comm Key: 101).
                    </p>
                </div>

                <!-- Status Hasil Uji Koneksi -->
                <div
                    v-if="connectionStatus"
                    class="p-3 rounded-xl text-xs flex items-start gap-2"
                    :class="connectionStatus.success ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'"
                >
                    <CheckCircle2 v-if="connectionStatus.success" class="w-4 h-4 text-emerald-600 flex-shrink-0 mt-0.5" />
                    <AlertCircle v-else class="w-4 h-4 text-rose-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <span class="font-bold">{{ connectionStatus.success ? 'Koneksi Berhasil: ' : 'Koneksi Gagal: ' }}</span>
                        <span>{{ connectionStatus.message }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <button
                        type="button"
                        @click="testConnection"
                        :disabled="isTesting"
                        class="text-xs font-bold text-blue-600 hover:text-blue-500 underline disabled:opacity-50 inline-flex items-center gap-1"
                    >
                        <Wifi class="w-3.5 h-3.5" />
                        <span>{{ isTesting ? 'Menguji koneksi...' : 'Uji Koneksi SOAP' }}</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="showSyncModal = false"
                            class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg"
                        >
                            Batal
                        </button>
                        <button
                            type="button"
                            @click="triggerSync"
                            :disabled="isSyncing"
                            class="px-4 py-2 text-xs font-bold bg-blue-600 hover:bg-blue-500 text-white rounded-lg shadow-xs disabled:opacity-50 inline-flex items-center gap-1.5"
                        >
                            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncing }" />
                            <span>{{ isSyncing ? 'Menarik data...' : 'Tarik Sekarang' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
