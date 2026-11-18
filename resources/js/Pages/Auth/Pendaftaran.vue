<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import {
    Clock,
    UserCheck,
    Send,
    LogOut,
    Shield,
    FileText,
    Building2,
    Briefcase,
    Phone,
    Mail,
    MapPin,
    Calendar,
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Info,
} from 'lucide-vue-next';

const props = defineProps({
    tempUser: Object,
    remainingSeconds: Number,
    seksiList: Array,
});

// Live Countdown Timer
const secondsLeft = ref(props.remainingSeconds || 600);
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        if (secondsLeft.value > 0) {
            secondsLeft.value -= 1;
        } else {
            clearInterval(timer);
            // Sesi habis, arahkan ke login
            alert('Akun tidak di temukan');
            router.visit(route('login'));
        }
    }, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const formattedTime = computed(() => {
    const mins = Math.floor(secondsLeft.value / 60);
    const secs = secondsLeft.value % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
});

const isUrgent = computed(() => secondsLeft.value <= 180); // <= 3 menit
const isCritical = computed(() => secondsLeft.value <= 60); // <= 1 menit

// Form state
const form = useForm({
    nama_lengkap: '',
    username: '',
    password: '',
    password_confirmation: '',
    email: '',
    nik: '',
    no_wa: '',
    seksi: props.seksiList[0]?.nama || '',
    posisi_pekerjaan: '',
    jabatan: 'PJLP',
    jenis_kelamin: 'Laki-laki',
    tempat_lahir: '',
    tanggal_lahir: '',
    status_pernikahan: 'Belum Menikah',
    pendidikan_terakhir: 'S1',
    alamat_domisili: '',
});

const showConfirmModal = ref(false);

const handlePreSubmit = () => {
    showConfirmModal.value = true;
};

const submitForm = () => {
    showConfirmModal.value = false;
    form.post(route('pendaftaran.store'), {
        preserveScroll: true,
    });
};

const handleLogout = () => {
    if (confirm('Yakin ingin keluar? Pengisian formulir akan dibatalkan.')) {
        router.post(route('logout'));
    }
};
</script>

<template>
    <Head title="Formulir Pendaftaran PJLP" />

    <div class="min-h-screen bg-slate-100 flex flex-col justify-between py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl w-full mx-auto space-y-6">
            <!-- Header Brand & Sesi Timer -->
            <div class="bg-white rounded-2xl p-5 sm:p-6 border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-lg shadow-xs flex-shrink-0">
                        <Building2 class="w-6 h-6 text-emerald-400" />
                    </div>
                    <div>
                        <div class="text-[11px] font-bold tracking-wider uppercase text-slate-500">
                            Suku Dinas Kominfotik Jakarta Timur
                        </div>
                        <h1 class="text-lg sm:text-xl font-bold text-slate-900">
                            Portal Pendaftaran Calon Pegawai PJLP
                        </h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Masuk sebagai: <strong class="font-mono text-slate-800">{{ tempUser.username }}</strong>
                        </p>
                    </div>
                </div>

                <!-- Timer & Logout -->
                <div class="flex items-center gap-3 self-end md:self-auto">
                    <!-- Countdown Badge -->
                    <div
                        class="flex items-center gap-2 px-3.5 py-2 rounded-xl border text-xs font-mono font-bold transition shadow-xs"
                        :class="isCritical
                            ? 'bg-rose-50 text-rose-700 border-rose-300 animate-pulse'
                            : (isUrgent ? 'bg-amber-50 text-amber-800 border-amber-300' : 'bg-slate-50 text-slate-800 border-slate-200')"
                    >
                        <Clock class="w-4 h-4" :class="isCritical ? 'text-rose-600' : (isUrgent ? 'text-amber-600' : 'text-slate-500')" />
                        <div>
                            <span class="text-[10px] uppercase font-sans font-semibold text-slate-500 block leading-none">Sisa Waktu Sesi</span>
                            <span class="text-sm tracking-widest font-black">{{ formattedTime }}</span>
                        </div>
                    </div>

                    <!-- Tombol Keluar -->
                    <button
                        type="button"
                        @click="handleLogout"
                        class="p-2.5 rounded-xl border border-slate-200 text-slate-500 hover:text-rose-600 hover:bg-rose-50 hover:border-rose-200 transition"
                        title="Keluar"
                    >
                        <LogOut class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Petunjuk & Peringatan Penting -->
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 sm:p-5 text-blue-900 text-xs flex items-start gap-3">
                <Info class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <div class="font-bold text-sm">Petunjuk Pengisian & Ketentuan Akun Sementara:</div>
                    <p class="text-blue-800 leading-relaxed">
                        1. Lengkapi seluruh kolom formulir berikut dengan data yang valid dan sesuai dokumen kependudukan (KTP).<br />
                        2. <strong>Akun Sementara Akan Dihapus:</strong> Demi keamanan, segera setelah Anda menekan tombol <em>"Kirim Formulir Pendaftaran"</em>, akun sementara ini akan langsung terhapus dari sistem.<br />
                        3. Formulir Anda akan diverifikasi oleh Superadmin. Simpan kode registrasi yang didapatkan untuk konfirmasi.
                    </p>
                </div>
            </div>

            <!-- Form Utama -->
            <form @submit.prevent="handlePreSubmit" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 sm:p-8 space-y-8">
                    <!-- SEKSI 1: AKUN RESMI YANG DIINGINKAN -->
                    <div>
                        <div class="flex items-center gap-2 pb-3 border-b border-slate-200">
                            <Shield class="w-4 h-4 text-emerald-600" />
                            <h2 class="text-sm sm:text-base font-bold text-slate-900">
                                1. Akun Resmi yang Diinginkan
                            </h2>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 mb-4">
                            Tentukan username dan kata sandi tetap yang akan Anda gunakan untuk masuk setelah pendaftaran disetujui oleh Superadmin.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Username Pilihan <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.username"
                                    type="text"
                                    required
                                    placeholder="Contoh: budi.santoso"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900 font-mono"
                                />
                                <span class="text-[10px] text-slate-400 mt-0.5 block">Hanya huruf, angka, titik, atau strip</span>
                                <p v-if="form.errors.username" class="text-[11px] text-rose-500 mt-1">
                                    {{ form.errors.username }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Kata Sandi Baru <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.password"
                                    type="password"
                                    required
                                    placeholder="Minimal 6 karakter"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                />
                                <p v-if="form.errors.password" class="text-[11px] text-rose-500 mt-1">
                                    {{ form.errors.password }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Konfirmasi Kata Sandi <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.password_confirmation"
                                    type="password"
                                    required
                                    placeholder="Ulangi kata sandi"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- SEKSI 2: IDENTITAS DIRI PELAMAR -->
                    <div>
                        <div class="flex items-center gap-2 pb-3 border-b border-slate-200">
                            <UserCheck class="w-4 h-4 text-blue-600" />
                            <h2 class="text-sm sm:text-base font-bold text-slate-900">
                                2. Data Identitas Pribadi
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Nama Lengkap (Sesuai KTP) <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.nama_lengkap"
                                    type="text"
                                    required
                                    placeholder="Contoh: Budi Santoso, S.Kom"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                />
                                <p v-if="form.errors.nama_lengkap" class="text-[11px] text-rose-500 mt-1">
                                    {{ form.errors.nama_lengkap }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Nomor Induk Kependudukan (NIK)
                                </label>
                                <input
                                    v-model="form.nik"
                                    type="text"
                                    maxlength="16"
                                    placeholder="16 Digit NIK KTP"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-mono focus:ring-2 focus:ring-slate-900"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Jenis Kelamin
                                </label>
                                <select
                                    v-model="form.jenis_kelamin"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                >
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Tempat Lahir
                                </label>
                                <input
                                    v-model="form.tempat_lahir"
                                    type="text"
                                    placeholder="Kota Kelahiran"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Tanggal Lahir
                                </label>
                                <input
                                    v-model="form.tanggal_lahir"
                                    type="date"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Status Pernikahan
                                </label>
                                <select
                                    v-model="form.status_pernikahan"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                >
                                    <option value="Belum Menikah">Belum Menikah</option>
                                    <option value="Menikah">Menikah</option>
                                    <option value="Cerai Hidup">Cerai Hidup</option>
                                    <option value="Cerai Mati">Cerai Mati</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Pendidikan Terakhir
                                </label>
                                <select
                                    v-model="form.pendidikan_terakhir"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                >
                                    <option value="SMA/SMK">SMA / SMK Sederajat</option>
                                    <option value="D3">Diploma 3 (D3)</option>
                                    <option value="D4">Diploma 4 (D4)</option>
                                    <option value="S1">Sarjana (S1)</option>
                                    <option value="S2">Magister (S2)</option>
                                </select>
                            </div>

                            <div class="sm:col-span-2 md:col-span-3">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Alamat Domisili Lengkap
                                </label>
                                <textarea
                                    v-model="form.alamat_domisili"
                                    rows="2"
                                    placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kota..."
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SEKSI 3: KONTAK & PENEMPATAN -->
                    <div>
                        <div class="flex items-center gap-2 pb-3 border-b border-slate-200">
                            <Briefcase class="w-4 h-4 text-purple-600" />
                            <h2 class="text-sm sm:text-base font-bold text-slate-900">
                                3. Kontak & Rencana Penempatan
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Nomor WhatsApp / HP Aktif
                                </label>
                                <input
                                    v-model="form.no_wa"
                                    type="tel"
                                    placeholder="Contoh: 08123456789"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-mono focus:ring-2 focus:ring-slate-900"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Alamat Email
                                </label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    placeholder="Contoh: budi@gmail.com"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Jabatan <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="form.jabatan"
                                        type="text"
                                        readonly
                                        class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs bg-slate-100 text-slate-800 font-bold focus:outline-none cursor-not-allowed select-none"
                                    />
                                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                        Otomatis
                                    </span>
                                </div>
                                <span class="text-[10px] text-slate-400 mt-0.5 block">Kategori kepegawaian otomatis terisi PJLP</span>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Seksi Penempatan <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    v-model="form.seksi"
                                    required
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                >
                                    <option v-for="s in seksiList" :key="s.id" :value="s.nama">
                                        {{ s.nama }}
                                    </option>
                                </select>
                                <p v-if="form.errors.seksi" class="text-[11px] text-rose-500 mt-1">
                                    {{ form.errors.seksi }}
                                </p>
                            </div>

                            <div class="sm:col-span-2 lg:col-span-4">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Posisi Pekerjaan <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    v-model="form.posisi_pekerjaan"
                                    type="text"
                                    required
                                    placeholder="Contoh: Tenaga Ahli IT / Layanan Jaringan / Administrasi Teknis"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:ring-2 focus:ring-slate-900"
                                />
                                <p v-if="form.errors.posisi_pekerjaan" class="text-[11px] text-rose-500 mt-1">
                                    {{ form.errors.posisi_pekerjaan }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Form & Tombol Kirim -->
                <div class="px-6 sm:px-8 py-5 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-xs text-slate-500 text-center sm:text-left">
                        Pastikan seluruh data sudah terisi benar sebelum mengirimkan berkas.
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full sm:w-auto px-8 py-3 rounded-xl bg-slate-900 text-white font-bold text-xs sm:text-sm hover:bg-slate-800 disabled:opacity-50 transition shadow-md flex items-center justify-center gap-2"
                    >
                        <Send class="w-4 h-4 text-emerald-400" />
                        <span>{{ form.processing ? 'Mengirim Formulir...' : 'Kirim Formulir Pendaftaran' }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Modal Konfirmasi Kirim -->
        <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-6 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-3 shadow-xs">
                        <AlertTriangle class="w-6 h-6" />
                    </div>
                    <h3 class="text-base font-bold text-slate-900">Konfirmasi Pengiriman Berkas</h3>
                    <p class="text-xs text-slate-600 mt-2 leading-relaxed">
                        Apakah data formulir yang Anda masukkan sudah benar?<br />
                        <strong>Perhatian:</strong> Begitu Anda mengirim formulir ini, akun sementara Anda akan <strong>langsung dihapus</strong> dan sesi ini akan berakhir.
                    </p>
                </div>

                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="showConfirmModal = false"
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-100"
                    >
                        Periksa Kembali
                    </button>
                    <button
                        type="button"
                        @click="submitForm"
                        :disabled="form.processing"
                        class="px-5 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 disabled:opacity-50 flex items-center gap-1.5"
                    >
                        <CheckCircle2 class="w-4 h-4 text-emerald-400" />
                        <span>Ya, Kirim Sekarang</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Simple Footer -->
        <footer class="mt-8 text-center text-xs text-slate-400">
            &copy; {{ new Date().getFullYear() }} Suku Dinas Komunikasi, Informatika dan Statistik Kota Administrasi Jakarta Timur
        </footer>
    </div>
</template>
