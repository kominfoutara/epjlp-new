<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Search,
    Clock,
    CheckCircle2,
    XCircle,
    Building2,
    Shield,
    Copy,
    Check,
    ArrowRight,
    LogIn,
    FileText,
    AlertCircle,
    AlertTriangle,
    RotateCcw,
} from 'lucide-vue-next';

const props = defineProps({
    code: String,
    submission: Object,
    searched: Boolean,
});

const searchCode = ref(props.code || '');
const copySuccess = ref(false);

const handleSearch = () => {
    if (!searchCode.value.trim()) return;
    router.get(route('pendaftaran.status'), {
        code: searchCode.value.trim(),
    }, {
        preserveState: true,
        replace: true,
    });
};

const copyToClipboard = async (text) => {
    if (!text) return false;
    let success = false;

    if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        try {
            await navigator.clipboard.writeText(text);
            success = true;
        } catch (e) {}
    }

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
            console.error(err);
        }
    }

    if (success) {
        copySuccess.value = true;
        setTimeout(() => {
            copySuccess.value = false;
        }, 2000);
    }
};

const shareableUrl = computedUrl();

function computedUrl() {
    if (typeof window === 'undefined') return '';
    return window.location.href;
}

const formatDateTime = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
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
    <Head title="Cek Status Pendaftaran PJLP" />

    <div class="min-h-screen bg-slate-100 flex flex-col justify-between py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full mx-auto space-y-6">
            <!-- Header Brand -->
            <div class="text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center mx-auto shadow-md">
                    <Building2 class="w-7 h-7 text-emerald-400" />
                </div>
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">
                    Suku Dinas Kominfotik Jakarta Timur
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900">
                    Pelacakan Status Pendaftaran Calon Pegawai
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 max-w-md mx-auto">
                    Masukkan kode registrasi Anda untuk melihat status terkini berkas pengajuan (Menunggu, Disetujui, atau Ditolak).
                </p>
            </div>

            <!-- Form Pencarian Kode Registrasi -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 sm:p-5">
                <form @submit.prevent="handleSearch" class="flex flex-col sm:flex-row gap-2.5">
                    <div class="relative flex-1">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input
                            v-model="searchCode"
                            type="text"
                            required
                            placeholder="Contoh: REG-20261104-XXXXX"
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-mono focus:ring-2 focus:ring-slate-900 focus:border-slate-900 transition uppercase"
                        />
                    </div>
                    <button
                        type="submit"
                        class="px-6 py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs sm:text-sm hover:bg-slate-800 transition shadow-sm flex items-center justify-center gap-2"
                    >
                        <Search class="w-4 h-4 text-emerald-400" />
                        <span>Cek Status</span>
                    </button>
                </form>
            </div>

            <!-- HASIL PENCARIAN -->
            <div v-if="searched">
                <!-- 1. KODE DITEMUKAN -->
                <div v-if="submission" class="bg-white rounded-3xl border border-slate-200 shadow-lg overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <!-- Status Banner Atas -->
                    <div
                        class="p-6 text-center border-b"
                        :class="submission.status === 'approved'
                            ? 'bg-emerald-50/70 border-emerald-200'
                            : (submission.status === 'rejected' ? 'bg-rose-50/70 border-rose-200' : 'bg-amber-50/70 border-amber-200')"
                    >
                        <!-- Icon Status -->
                        <div
                            class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-xs"
                            :class="submission.status === 'approved'
                                ? 'bg-emerald-100 text-emerald-600'
                                : (submission.status === 'rejected' ? 'bg-rose-100 text-rose-600' : 'bg-amber-100 text-amber-600')"
                        >
                            <CheckCircle2 v-if="submission.status === 'approved'" class="w-8 h-8" />
                            <XCircle v-else-if="submission.status === 'rejected'" class="w-8 h-8" />
                            <Clock v-else class="w-8 h-8 animate-pulse" />
                        </div>

                        <!-- Label Status -->
                        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Status Pengajuan Anda
                        </div>

                        <div
                            class="text-xl sm:text-2xl font-black"
                            :class="submission.status === 'approved'
                                ? 'text-emerald-700'
                                : (submission.status === 'rejected' ? 'text-rose-700' : 'text-amber-700')"
                        >
                            <span v-if="submission.status === 'approved'">PENDAFTARAN DISETUJUI</span>
                            <span v-else-if="submission.status === 'rejected'">PENDAFTARAN DITOLAK</span>
                            <span v-else>MENUNGGU VALIDASI</span>
                        </div>

                        <!-- Pesan Pendukung Status -->
                        <p class="text-xs text-slate-600 mt-2 max-w-md mx-auto leading-relaxed">
                            <span v-if="submission.status === 'approved'">
                                Selamat! Berkas pendaftaran Anda telah disetujui oleh Superadmin. Akun resmi PJLP Anda kini telah aktif.
                            </span>
                            <span v-else-if="submission.status === 'rejected'">
                                Mohon maaf, pengajuan pendaftaran Anda belum dapat disetujui oleh Superadmin.
                            </span>
                            <span v-else>
                                Pengajuan berkas pendaftaran Anda telah diterima sistem dan saat ini sedang menunggu antrean verifikasi oleh Superadmin.
                            </span>
                        </p>
                    </div>

                    <!-- Catatan Admin (Jika Ada) -->
                    <div v-if="submission.catatan_admin" class="p-4 mx-6 mt-6 rounded-2xl border text-xs"
                        :class="submission.status === 'approved' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900'">
                        <div class="font-bold mb-1 flex items-center gap-1.5">
                            <AlertCircle class="w-4 h-4" />
                            <span>Catatan Administrator:</span>
                        </div>
                        <p class="italic leading-relaxed">"{{ submission.catatan_admin }}"</p>
                    </div>

                    <!-- Rincian Data Formulir -->
                    <div class="p-6 sm:p-8 space-y-4">
                        <div class="text-xs font-bold text-slate-800 uppercase tracking-wider pb-2 border-b border-slate-100 flex items-center gap-1.5">
                            <FileText class="w-4 h-4 text-slate-400" />
                            <span>Rincian Pengajuan Berkas</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-slate-50 p-4 rounded-2xl border border-slate-200 font-mono">
                            <div>
                                <span class="text-slate-400 text-[11px] block font-sans">Kode Registrasi:</span>
                                <span class="font-bold text-slate-900 text-sm select-all">{{ submission.registration_code }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[11px] block font-sans">Username Diajukan:</span>
                                <span class="font-bold text-slate-900 text-sm select-all">{{ submission.username }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[11px] block font-sans">Nama Lengkap:</span>
                                <span class="font-semibold text-slate-800 font-sans">{{ submission.nama_lengkap }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[11px] block font-sans">Jabatan:</span>
                                <span class="font-bold text-emerald-700 font-sans">{{ submission.jabatan || 'PJLP' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[11px] block font-sans">Seksi Penempatan:</span>
                                <span class="font-medium text-slate-800 font-sans">{{ submission.seksi }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[11px] block font-sans">Posisi Pekerjaan:</span>
                                <span class="font-medium text-slate-800 font-sans">{{ submission.posisi_pekerjaan }}</span>
                            </div>
                            <div class="sm:col-span-2 pt-2 border-t border-slate-200">
                                <span class="text-slate-400 text-[11px] block font-sans">Waktu Pengiriman Berkas:</span>
                                <span class="text-slate-700 font-sans">{{ formatDateTime(submission.created_at) }}</span>
                            </div>
                        </div>

                        <!-- Kotak Simpan / Salin Tautan Cek Status -->
                        <div class="bg-blue-50/60 rounded-2xl p-4 border border-blue-200 text-xs space-y-2">
                            <div class="font-bold text-blue-950 flex items-center gap-1.5">
                                <Shield class="w-4 h-4 text-blue-600" />
                                <span>Simpan Tautan Ini Untuk Memeriksa Kembali Nanti:</span>
                            </div>
                            <p class="text-blue-800 text-[11px]">
                                Anda dapat menyimpan tautan halaman ini untuk memantau status validasi tanpa perlu mengetik ulang kode.
                            </p>
                            <button
                                type="button"
                                @click="copyToClipboard(shareableUrl)"
                                class="w-full py-2 px-3 rounded-xl bg-white border border-blue-300 text-blue-900 font-semibold flex items-center justify-center gap-2 hover:bg-blue-100 transition shadow-2xs"
                            >
                                <Check v-if="copySuccess" class="w-4 h-4 text-emerald-600" />
                                <Copy v-else class="w-4 h-4 text-blue-600" />
                                <span>{{ copySuccess ? 'Tautan Berhasil Disalin!' : 'Salin Tautan Cek Status Ini' }}</span>
                            </button>
                        </div>

                        <!-- Tombol Masuk Jika Sudah Disetujui -->
                        <div v-if="submission.status === 'approved'" class="pt-2">
                            <Link
                                :href="route('login')"
                                class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm transition shadow-md flex items-center justify-center gap-2"
                            >
                                <LogIn class="w-4 h-4" />
                                <span>Masuk ke Sistem Sekarang (Login)</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- 2. KODE TIDAK DITEMUKAN -->
                <div v-else class="bg-white rounded-3xl border border-slate-200 p-8 text-center space-y-3 shadow-md">
                    <div class="w-14 h-14 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center mx-auto shadow-xs">
                        <AlertTriangle class="w-7 h-7" />
                    </div>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900">
                        Kode Registrasi Tidak Ditemukan
                    </h2>
                    <p class="text-xs text-slate-600 max-w-md mx-auto leading-relaxed">
                        Kode <strong>"{{ code }}"</strong> tidak terdaftar dalam database pendaftaran. Silakan periksa kembali ketikan kode Anda (format: <code>REG-YYYYMMDD-XXXXX</code>).
                    </p>
                </div>
            </div>

            <!-- Tombol Navigasi Bawah -->
            <div class="flex items-center justify-center gap-4 text-xs pt-2">
                <Link
                    :href="route('login')"
                    class="text-slate-600 hover:text-slate-900 font-semibold flex items-center gap-1.5 transition"
                >
                    <ArrowRight class="w-3.5 h-3.5 rotate-180" />
                    <span>Kembali ke Halaman Masuk</span>
                </Link>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-8 text-center text-xs text-slate-400">
            &copy; {{ new Date().getFullYear() }} Suku Dinas Komunikasi, Informatika dan Statistik Kota Administrasi Jakarta Timur
        </footer>
    </div>
</template>

