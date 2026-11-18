<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Copy,
    Check,
    Clock,
    Building2,
    Shield,
    ArrowRight,
    LogIn,
    Search,
    ExternalLink,
} from 'lucide-vue-next';

const props = defineProps({
    submission: Object,
    flashData: Object,
    statusUrl: String,
});

const data = props.submission || props.flashData || {};
const registrationCode = data.registration_code || 'REG-PENDING';
const targetStatusUrl = props.statusUrl || (typeof window !== 'undefined' ? `${window.location.origin}/pendaftaran/status?code=${registrationCode}` : `/pendaftaran/status?code=${registrationCode}`);

const copyCodeSuccess = ref(false);
const copyUrlSuccess = ref(false);

const copyToClipboard = async (text, type = 'code') => {
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
        if (type === 'code') {
            copyCodeSuccess.value = true;
            setTimeout(() => { copyCodeSuccess.value = false; }, 2000);
        } else {
            copyUrlSuccess.value = true;
            setTimeout(() => { copyUrlSuccess.value = false; }, 2000);
        }
    }
};
</script>

<template>
    <Head title="Pendaftaran Berhasil Dikirim" />

    <div class="min-h-screen bg-slate-100 flex flex-col justify-between py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-xl w-full mx-auto space-y-6">
            <!-- Brand -->
            <div class="text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center mx-auto shadow-md">
                    <Building2 class="w-7 h-7 text-emerald-400" />
                </div>
                <div class="text-xs font-bold uppercase tracking-widest text-slate-500">
                    Suku Dinas Kominfotik Jakarta Timur
                </div>
            </div>

            <!-- Card Utama -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-xl overflow-hidden p-6 sm:p-8 space-y-6">
                <!-- Icon Centang Sukses -->
                <div class="text-center space-y-2">
                    <div class="w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto shadow-xs">
                        <CheckCircle2 class="w-9 h-9" />
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900">
                        Formulir Berhasil Dikirim!
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed max-w-md mx-auto">
                        Terima kasih. Berkas pendaftaran Anda telah berhasil diterima oleh sistem e-Kinerja PJLP dan diteruskan ke Superadmin untuk diverifikasi.
                    </p>
                </div>

                <!-- Kartu Kode Registrasi -->
                <div class="bg-slate-900 text-white rounded-2xl p-5 text-center relative overflow-hidden shadow-inner">
                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        Kode Pendaftaran Anda
                    </div>
                    <div class="text-xl sm:text-2xl font-mono font-black text-emerald-400 tracking-wider mt-1 select-all">
                        {{ registrationCode }}
                    </div>
                    <div class="mt-3 flex items-center justify-center">
                        <button
                            type="button"
                            @click="copyToClipboard(registrationCode, 'code')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 text-slate-200 hover:text-white hover:bg-slate-700 text-xs font-medium transition"
                        >
                            <Check v-if="copyCodeSuccess" class="w-3.5 h-3.5 text-emerald-400" />
                            <Copy v-else class="w-3.5 h-3.5" />
                            <span>{{ copyCodeSuccess ? 'Kode Berhasil Disalin!' : 'Salin Kode Pendaftaran' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Kartu Tautan Cek Status Pendaftaran -->
                <div class="bg-emerald-50/70 border border-emerald-200 rounded-2xl p-4 sm:p-5 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <Search class="w-4 h-4" />
                        </div>
                        <div class="flex-1">
                            <div class="text-xs font-bold text-emerald-950">
                                Pantau Status Pendaftaran Anda
                            </div>
                            <p class="text-[11px] text-emerald-800 mt-0.5 leading-relaxed">
                                Anda dapat mengklik tombol di bawah ini kapan saja untuk mengecek apakah berkas Anda sedang <strong>Menunggu Validasi</strong>, <strong>Disetujui</strong>, atau <strong>Ditolak</strong>.
                            </p>
                        </div>
                    </div>

                    <!-- Tombol Aksi Cek & Simpan Tautan -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                        <Link
                            :href="targetStatusUrl"
                            class="w-full py-2.5 px-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs flex items-center justify-center gap-1.5"
                        >
                            <span>Cek Status Sekarang</span>
                            <ArrowRight class="w-3.5 h-3.5" />
                        </Link>
                        <button
                            type="button"
                            @click="copyToClipboard(targetStatusUrl, 'url')"
                            class="w-full py-2.5 px-3.5 rounded-xl bg-white border border-emerald-300 text-emerald-900 font-bold text-xs hover:bg-emerald-50 transition shadow-2xs flex items-center justify-center gap-1.5"
                        >
                            <Check v-if="copyUrlSuccess" class="w-3.5 h-3.5 text-emerald-600" />
                            <Copy v-else class="w-3.5 h-3.5 text-emerald-600" />
                            <span>{{ copyUrlSuccess ? 'Tautan Tersalin!' : 'Simpan / Salin Tautan Cek' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Rincian Ringkas Pengajuan -->
                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200 text-xs space-y-2">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                        <span class="text-slate-500">Nama Pelamar:</span>
                        <span class="font-bold text-slate-900">{{ data.nama_lengkap || '-' }}</span>
                    </div>
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                        <span class="text-slate-500">Username Diajukan:</span>
                        <span class="font-mono font-bold text-slate-900">{{ data.username || '-' }}</span>
                    </div>
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                        <span class="text-slate-500">Jabatan:</span>
                        <span class="font-bold text-emerald-700">{{ data.jabatan || 'PJLP' }}</span>
                    </div>
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                        <span class="text-slate-500">Seksi / Posisi:</span>
                        <span class="font-medium text-slate-800 text-right">{{ data.seksi }} - {{ data.posisi_pekerjaan }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Status Terkini:</span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                            <Clock class="w-3 h-3" />
                            Menunggu Validasi Superadmin
                        </span>
                    </div>
                </div>

                <!-- Informasi Tahap Selanjutnya -->
                <div class="border-t border-slate-100 pt-5 space-y-3">
                    <div class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                        <Shield class="w-4 h-4 text-emerald-600" />
                        <span>Informasi Keamanan & Proses Selanjutnya:</span>
                    </div>
                    <ul class="text-xs text-slate-600 space-y-2 list-disc pl-5 leading-relaxed">
                        <li>
                            <strong>Akun Sementara Telah Dihapus:</strong> Akun sementara yang Anda gunakan sebelumnya telah dihapus demi menjaga kerahasiaan data.
                        </li>
                        <li>
                            <strong>Verifikasi Berkas:</strong> Superadmin akan meninjau kelengkapan formasi dan menyetujui pengajuan Anda.
                        </li>
                        <li>
                            <strong>Aktivasi Akun Resmi:</strong> Setelah disetujui, Anda dapat langsung masuk ke portal e-Kinerja menggunakan username <strong>{{ data.username }}</strong> dan kata sandi yang telah Anda daftarkan.
                        </li>
                    </ul>
                </div>

                <!-- Tombol Kembali ke Login -->
                <div class="pt-2">
                    <Link
                        :href="route('login')"
                        class="w-full py-3 px-4 rounded-xl bg-slate-900 text-white font-bold text-xs sm:text-sm hover:bg-slate-800 transition shadow-md flex items-center justify-center gap-2"
                    >
                        <LogIn class="w-4 h-4 text-emerald-400" />
                        <span>Kembali ke Halaman Masuk (Login)</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-8 text-center text-xs text-slate-400">
            &copy; {{ new Date().getFullYear() }} Suku Dinas Komunikasi, Informatika dan Statistik Kota Administrasi Jakarta Timur
        </footer>
    </div>
</template>
