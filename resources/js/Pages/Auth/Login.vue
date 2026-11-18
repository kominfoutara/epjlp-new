<script setup>
import { ref, onMounted, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import GovShieldLogo from '@/Components/GovShieldLogo.vue';
import {
    Eye,
    EyeOff,
    RotateCw,
    Volume2,
    HelpCircle,
    CheckCircle2,
    AlertCircle,
    ArrowRight,
    Phone,
    Mail,
    X,
    KeyRound,
    User,
} from 'lucide-vue-next';

const props = defineProps({
    canResetPassword: {
        type: Boolean,
        default: true,
    },
    status: {
        type: String,
        default: '',
    },
    initialCaptcha: {
        type: String,
        default: '',
    },
    initialCaptchaKey: {
        type: String,
        default: '',
    },
});

const form = useForm({
    email: '',
    password: '',
    captcha: '',
    captcha_key: props.initialCaptchaKey || '',
    remember: false,
});

const captchaCode = ref(props.initialCaptcha || '');
const isRefreshingCaptcha = ref(false);
const showPassword = ref(false);
const activeModal = ref(null); // 'help' | 'auth' | 'demo' | null
const captchaCanvas = ref(null);
const pasteWarning = ref(false);
let pasteWarningTimeout = null;

const handlePasteAttempt = (e) => {
    if (e) e.preventDefault();
    pasteWarning.value = true;
    if (pasteWarningTimeout) clearTimeout(pasteWarningTimeout);
    pasteWarningTimeout = setTimeout(() => {
        pasteWarning.value = false;
    }, 4000);
};

const drawCaptcha = () => {
    const canvas = captchaCanvas.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const dpr = window.devicePixelRatio || 1;
    const logicalWidth = 190;
    const logicalHeight = 40;

    canvas.width = logicalWidth * dpr;
    canvas.height = logicalHeight * dpr;
    canvas.style.width = logicalWidth + 'px';
    canvas.style.height = logicalHeight + 'px';

    ctx.scale(dpr, dpr);

    // Clear background with soft neutral slate
    ctx.fillStyle = '#f1f5f9';
    ctx.fillRect(0, 0, logicalWidth, logicalHeight);

    // Background noise dots
    ctx.fillStyle = '#cbd5e1';
    for (let i = 0; i < 30; i++) {
        const x = Math.random() * logicalWidth;
        const y = Math.random() * logicalHeight;
        const r = Math.random() * 1.5 + 0.5;
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.fill();
    }

    // Wavy interference lines
    ctx.strokeStyle = '#94a3b8';
    ctx.lineWidth = 1.4;
    ctx.beginPath();
    ctx.moveTo(-5, logicalHeight * 0.45);
    ctx.bezierCurveTo(
        logicalWidth * 0.3,
        6,
        logicalWidth * 0.65,
        logicalHeight - 6,
        logicalWidth + 5,
        logicalHeight * 0.5
    );
    ctx.stroke();

    ctx.strokeStyle = '#64748b';
    ctx.lineWidth = 1.0;
    ctx.beginPath();
    ctx.moveTo(-5, logicalHeight * 0.7);
    ctx.bezierCurveTo(
        logicalWidth * 0.25,
        logicalHeight - 4,
        logicalWidth * 0.75,
        8,
        logicalWidth + 5,
        logicalHeight * 0.35
    );
    ctx.stroke();

    // Draw characters as raster graphics
    const code = (captchaCode.value || '').trim();
    if (!code) return;

    const chars = code.split('');
    const charCount = chars.length;
    const startX = 14;
    const availableWidth = logicalWidth - startX * 2;
    const spacing = availableWidth / charCount;

    chars.forEach((char, index) => {
        ctx.save();
        const x = startX + index * spacing + spacing / 2;
        const y = logicalHeight / 2 + 1;

        // Alternating tilt
        const tiltDeg = (index % 2 === 0 ? 1 : -1) * (8 + (index * 4) % 10);
        ctx.translate(x, y);
        ctx.rotate((tiltDeg * Math.PI) / 180);

        ctx.font = 'italic bold 22px "Times New Roman", Georgia, serif';
        ctx.fillStyle = '#1e293b';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        ctx.shadowColor = 'rgba(30, 41, 59, 0.15)';
        ctx.shadowBlur = 1;
        ctx.shadowOffsetX = 1;
        ctx.shadowOffsetY = 1;

        ctx.fillText(char, 0, 0);
        ctx.restore();
    });
};

watch(
    () => [props.initialCaptcha, props.initialCaptchaKey],
    ([newCaptcha, newKey]) => {
        if (newCaptcha) captchaCode.value = newCaptcha;
        if (newKey) form.captcha_key = newKey;
        setTimeout(drawCaptcha, 20);
    }
);

watch(captchaCode, () => {
    setTimeout(drawCaptcha, 20);
});

onMounted(() => {
    if (!captchaCode.value || !form.captcha_key) {
        refreshCaptcha();
    } else {
        setTimeout(drawCaptcha, 50);
    }
});

const refreshCaptcha = async () => {
    isRefreshingCaptcha.value = true;
    try {
        const response = await window.axios.get(route('captcha.refresh'));
        if (response.data?.captcha && response.data?.captcha_key) {
            captchaCode.value = response.data.captcha;
            form.captcha_key = response.data.captcha_key;
        }
    } catch (error) {
        console.error('Gagal memperbarui captcha:', error);
    } finally {
        isRefreshingCaptcha.value = false;
        setTimeout(drawCaptcha, 20);
    }
};

const playCaptchaAudio = () => {
    if (!('speechSynthesis' in window)) {
        alert('Fitur audio tidak didukung pada peramban Anda.');
        return;
    }
    const code = (captchaCode.value || '').trim();
    if (!code) return;

    window.speechSynthesis.cancel();
    const characters = code.split('').join(', ');
    const utterance = new SpeechSynthesisUtterance(characters);
    utterance.rate = 0.65;
    utterance.pitch = 1.0;
    utterance.lang = 'id-ID';
    window.speechSynthesis.speak(utterance);
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
        onError: () => {
            refreshCaptcha();
            form.reset('captcha');
        },
    });
};

const fillDemoAccount = (username, password = 'password') => {
    form.email = username;
    form.password = password;
    form.captcha = captchaCode.value;
    activeModal.value = null;
};
</script>

<template>
    <Head title="Masuk - e-Kinerja PJLP Jakarta Utara" />

    <div class="min-h-screen relative flex flex-col justify-between font-sans text-slate-800 bg-slate-900">
        <!-- FOTO KANTOR WALIKOTA JAKARTA UTARA BACKGROUND -->
        <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none select-none">
            <img
                src="/images/kantor-walikota-jakut.jpg"
                alt="Kantor Walikota Kota Administrasi Jakarta Utara"
                class="w-full h-full object-cover object-[center_32%] scale-[1.01] transform transition-transform duration-1000 ease-out"
                style="image-rendering: auto; filter: contrast(1.02) saturate(1.04);"
                fetchpriority="high"
                decoding="sync"
            />
            <!-- Gradasi Terkalibrasi: Menjaga warna asli gedung & langit tetap jernih dan cerah, sekaligus memberi kontras tinggi untuk teks & kartu -->
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-900/25 to-slate-950/40"></div>
            <div class="absolute inset-0 bg-radial-[circle_at_center] from-transparent via-transparent to-slate-950/50"></div>
            <!-- Overlay directional halus di sisi kanan pada desktop untuk memaksimalkan kontras kartu login -->
            <div class="hidden lg:block absolute inset-0 bg-gradient-to-r from-transparent via-slate-950/15 to-slate-950/60"></div>
        </div>

        <!-- HEADER PEMERINTAHAN RESMI -->
        <header class="relative z-10 w-full bg-white/95 border-b border-slate-200/80 px-4 sm:px-6 lg:px-8 py-3.5 shadow-2xs backdrop-blur-xs">
            <div class="w-full flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white border border-slate-200 p-1 flex items-center justify-center flex-shrink-0 shadow-2xs">
                        <GovShieldLogo className="w-full h-full object-contain" />
                    </div>
                    <div>
                        <div class="text-[10px] sm:text-[11px] font-bold tracking-wider text-slate-500 uppercase leading-tight">
                            Pemerintah Kota Administrasi Jakarta Utara
                        </div>
                        <div class="text-sm sm:text-base font-bold text-slate-900 leading-tight">
                            e-Kinerja PJLP
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- KONTEN UTAMA / CARD LOGIN & HERO SECTION -->
        <main class="relative z-10 flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
            <div class="w-full max-w-6xl mx-auto flex flex-col lg:flex-row items-center justify-between gap-8 lg:gap-12">
                <!-- Sisi Kiri: Branding & Pengantar Portal (Desktop) -->
                <div class="hidden lg:flex flex-col justify-center flex-1 max-w-lg text-white select-none py-2">
                    <div class="space-y-6">
                        <div class="inline-flex items-center gap-2.5 px-3.5 py-1.5 rounded-full bg-slate-900/65 backdrop-blur-md border border-white/20 text-xs font-semibold text-slate-100 shadow-md">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>Portal Resmi Kepegawaian &amp; Evaluasi Kinerja</span>
                        </div>

                        <div class="space-y-3">
                            <h2 class="text-3xl xl:text-4xl font-extrabold tracking-tight text-white drop-shadow-md leading-tight">
                                Kantor Walikota<br />
                                <span class="text-sky-300">Kota Administrasi Jakarta Utara</span>
                            </h2>
                            <p class="text-sm xl:text-base text-slate-100/90 font-normal leading-relaxed drop-shadow-sm">
                                Selamat datang di sistem terpadu pelaporan tugas harian, presensi kedinasan, dan penilaian kinerja berkala bagi seluruh tenaga Penyedia Jasa Lainnya Perorangan (PJLP).
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Sisi Kanan: Card Login Berstandar SPBE -->
                <div class="w-full max-w-md">
                    <!-- Login Card Solid & Clean -->
                    <div
                        class="bg-white rounded-2xl shadow-2xl shadow-slate-950/40 border border-slate-200 p-6 sm:p-8 relative z-20"
                        style="background-color: #ffffff !important;"
                    >
                    <!-- Heading -->
                    <div class="text-center mb-6">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 mb-3 shadow-xs">
                            <GovShieldLogo className="w-8 h-8 object-contain" />
                        </div>
                        <h1 class="text-xl font-extrabold text-slate-950 tracking-tight">
                            e-Kinerja PJLP
                        </h1>
                        <p class="text-xs text-slate-700 font-medium mt-1">
                            Sistem Pelaporan dan Evaluasi Kinerja Pegawai PJLP
                        </p>
                    </div>

                    <!-- Flash Status Message -->
                    <div
                        v-if="status"
                        class="mb-5 p-3 rounded-lg text-xs font-medium bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-2"
                    >
                        <CheckCircle2 class="w-4 h-4 flex-shrink-0 text-emerald-600" />
                        <span>{{ status }}</span>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Input NIP / ID Pengguna -->
                        <div>
                            <label for="email" class="block text-xs font-bold text-slate-900 mb-1.5">
                                NIP / ID Pengguna
                            </label>
                            <input
                                id="email"
                                type="text"
                                v-model="form.email"
                                placeholder="Masukkan 18 digit NIP atau Username"
                                required
                                autofocus
                                autocomplete="username"
                                class="w-full px-3.5 py-2.5 text-sm rounded-lg border border-slate-300 text-slate-900 placeholder-slate-400 bg-white focus:outline-none focus:ring-2 focus:ring-slate-900 focus:border-slate-900 transition-colors"
                            />
                            <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-600 font-medium">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <!-- Input Kata Sandi -->
                        <div>
                            <label for="password" class="block text-xs font-bold text-slate-900 mb-1.5">
                                Kata Sandi
                            </label>
                            <div class="relative">
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="form.password"
                                    placeholder="Masukkan kata sandi akun Anda"
                                    required
                                    autocomplete="current-password"
                                    class="w-full px-3.5 py-2.5 pr-10 text-sm rounded-lg border border-slate-300 text-slate-900 placeholder-slate-400 bg-white focus:outline-none focus:ring-2 focus:ring-slate-900 focus:border-slate-900 transition-colors"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer"
                                    :title="showPassword ? 'Sembunyikan sandi' : 'Tampilkan sandi'"
                                >
                                    <EyeOff v-if="showPassword" class="w-4 h-4" />
                                    <Eye v-else class="w-4 h-4" />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-600 font-medium">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Input Kode Verifikasi (CAPTCHA) -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="captcha" class="block text-xs font-bold text-slate-900">
                                    Kode Verifikasi (CAPTCHA)
                                </label>
                                <span class="text-[11px] font-semibold text-slate-600">
                                    Ketik manual
                                </span>
                            </div>

                            <!-- Captcha Canvas Display & Action Controls -->
                            <div class="flex items-center gap-2 mb-2">
                                <div
                                    class="flex-1 h-11 bg-slate-100 border border-slate-300 rounded-lg px-2 flex items-center justify-center select-none overflow-hidden"
                                    @copy.prevent
                                    @cut.prevent
                                    @contextmenu.prevent
                                >
                                    <canvas
                                        ref="captchaCanvas"
                                        class="pointer-events-none select-none"
                                        aria-label="Kode Verifikasi CAPTCHA"
                                    ></canvas>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        @click="refreshCaptcha"
                                        :disabled="isRefreshingCaptcha"
                                        title="Muat ulang kode"
                                        class="p-2.5 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-950 transition disabled:opacity-50 cursor-pointer"
                                    >
                                        <RotateCw class="w-4 h-4" :class="{ 'animate-spin': isRefreshingCaptcha }" />
                                    </button>
                                    <button
                                        type="button"
                                        @click="playCaptchaAudio"
                                        title="Dengarkan kode (Audio)"
                                        class="p-2.5 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-950 transition cursor-pointer"
                                    >
                                        <Volume2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Captcha Text Input -->
                            <input
                                id="captcha"
                                type="text"
                                v-model="form.captcha"
                                maxlength="5"
                                placeholder="Ketik 5 karakter kode di atas"
                                required
                                autocomplete="off"
                                autocorrect="off"
                                autocapitalize="characters"
                                spellcheck="false"
                                @paste.prevent="handlePasteAttempt"
                                @drop.prevent="handlePasteAttempt"
                                @copy.prevent
                                @cut.prevent
                                @contextmenu.prevent
                                class="w-full px-3.5 py-2.5 text-sm font-mono tracking-widest rounded-lg border border-slate-300 text-slate-900 placeholder:font-sans placeholder:tracking-normal placeholder-slate-400 bg-white focus:outline-none focus:ring-2 focus:ring-slate-900 focus:border-slate-900 uppercase transition-colors"
                            />

                            <p v-if="pasteWarning" class="mt-1.5 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 flex items-center gap-1.5">
                                <AlertCircle class="w-3.5 h-3.5 flex-shrink-0 text-amber-600" />
                                <span>Demi keamanan, silakan ketik kode verifikasi secara manual.</span>
                            </p>
                            <p v-if="form.errors.captcha" class="mt-1.5 text-xs text-rose-600 font-medium">
                                {{ form.errors.captcha }}
                            </p>
                        </div>

                        <!-- Baris: Ingat Sesi & Lupa Sandi -->
                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    v-model="form.remember"
                                    class="w-4 h-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900 cursor-pointer"
                                />
                                <span class="text-xs text-slate-800 font-semibold">
                                    Ingat sesi saya
                                </span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-xs font-bold text-slate-800 hover:text-slate-950 hover:underline"
                            >
                                Lupa Kata Sandi?
                            </Link>
                        </div>

                        <!-- Tombol Submit -->
                        <div class="pt-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full py-2.5 px-4 rounded-lg bg-slate-900 hover:bg-slate-800 active:bg-slate-950 text-white font-bold text-sm transition-colors duration-150 flex items-center justify-center gap-2 shadow-xs disabled:opacity-60 cursor-pointer"
                            >
                                <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>{{ form.processing ? 'Memverifikasi...' : 'Masuk ke Portal' }}</span>
                                <ArrowRight v-if="!form.processing" class="w-4 h-4" />
                            </button>
                        </div>
                    </form>

                    <!-- Informasi Bantuan Login -->
                    <div class="mt-6 pt-5 border-t border-slate-200 flex items-center justify-between text-xs text-slate-700">
                        <div class="flex items-center gap-1.5 font-medium">
                            <HelpCircle class="w-4 h-4 text-slate-500" />
                            <span>Kendala akses akun?</span>
                        </div>
                        <button
                            type="button"
                            @click="activeModal = 'help'"
                            class="font-bold text-slate-950 hover:text-blue-900 hover:underline cursor-pointer"
                        >
                            Pusat Bantuan TI
                        </button>
                    </div>

                    <!-- Cek Status Pendaftaran Calon Pegawai -->
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-700">
                        <span class="text-slate-500">Calon Pegawai Baru?</span>
                        <Link
                            :href="route('pendaftaran.status')"
                            class="font-bold text-emerald-700 hover:text-emerald-900 hover:underline flex items-center gap-1"
                        >
                            <span>Cek Status Pendaftaran</span>
                            <ArrowRight class="w-3.5 h-3.5" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </main>

        <!-- FOOTER RESMI -->
        <footer class="relative z-10 w-full bg-white/95 border-t border-slate-200/80 px-4 sm:px-6 lg:px-8 py-3.5 backdrop-blur-xs">
            <div class="w-full flex flex-col sm:flex-row items-center justify-between text-xs text-slate-600 gap-2">
                <div>
                    Hak Cipta &copy; 2026 Suku Dinas Kominfotik Kota Administrasi Jakarta Utara
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="activeModal = 'auth'"
                        class="hover:text-slate-900 hover:underline cursor-pointer"
                    >
                        Petunjuk Login
                    </button>
                    <span class="text-slate-300">•</span>
                    <button
                        type="button"
                        @click="activeModal = 'demo'"
                        class="text-slate-500 hover:text-slate-800 hover:underline cursor-pointer"
                    >
                        Bantuan Akun Uji Coba
                    </button>
                </div>
            </div>
        </footer>

        <!-- MODALS -->

        <!-- Modal 1: Bantuan Teknis TI -->
        <div
            v-if="activeModal === 'help'"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60"
            @click.self="activeModal = null"
        >
            <div class="w-full max-w-md bg-white rounded-xl shadow-2xl border border-slate-200 p-6 animate-in fade-in duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2 text-slate-900 font-bold text-sm sm:text-base">
                        <HelpCircle class="w-5 h-5 text-slate-700" />
                        <span>Pusat Bantuan TI &amp; Kepegawaian</span>
                    </div>
                    <button
                        type="button"
                        @click="activeModal = null"
                        class="text-slate-400 hover:text-slate-600 p-1 rounded-lg cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="mt-4 space-y-3 text-xs text-slate-600 leading-relaxed">
                    <p>
                        Jika Anda mengalami kendala saat masuk ke portal e-Kinerja PJLP (lupa kata sandi, akun terkunci, atau mutasi seksi), silakan hubungi tim Helpdesk Kepegawaian &amp; TI:
                    </p>

                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                        <div class="flex items-center gap-2 text-slate-800 font-medium">
                            <Phone class="w-4 h-4 text-emerald-600 flex-shrink-0" />
                            <span>Kontak Helpdesk: Kantor Walikota Jakarta Utara</span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-800 font-medium">
                            <Mail class="w-4 h-4 text-sky-600 flex-shrink-0" />
                            <span>Email Kedinasan: kominfotik.ju@jakarta.go.id</span>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-500">
                        Jam Layanan: Hari Kerja (Senin - Jumat) pukul 07.30 - 16.00 WIB.
                    </p>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        @click="activeModal = null"
                        class="px-4 py-2 bg-slate-900 text-white text-xs font-semibold rounded-lg hover:bg-slate-800 cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal 2: Petunjuk Format Autentikasi -->
        <div
            v-if="activeModal === 'auth'"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60"
            @click.self="activeModal = null"
        >
            <div class="w-full max-w-md bg-white rounded-xl shadow-2xl border border-slate-200 p-6 animate-in fade-in duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2 text-slate-900 font-bold text-sm sm:text-base">
                        <User class="w-5 h-5 text-slate-700" />
                        <span>Petunjuk Identitas Pengguna</span>
                    </div>
                    <button
                        type="button"
                        @click="activeModal = null"
                        class="text-slate-400 hover:text-slate-600 p-1 rounded-lg cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="mt-4 space-y-3 text-xs text-slate-600 leading-relaxed">
                    <p>
                        Masuk ke portal e-Kinerja PJLP menggunakan salah satu identitas resmi berikut:
                    </p>
                    <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                        <div>• <strong>NIP 18 Digit</strong> (Bagi ASN / Pejabat Penilai / Kasie / Kasudin)</div>
                        <div>• <strong>NIK 16 Digit atau Username</strong> (Bagi Tenaga PJLP)</div>
                        <div>• <strong>Username Administrator</strong> (Bagi Admin Pengelola Sistem)</div>
                    </div>
                    <p class="text-[11px] text-slate-500">
                        Pastikan menjaga kerahasiaan kata sandi Anda dan selalu lakukan logout setelah selesai bertugas.
                    </p>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        @click="activeModal = null"
                        class="px-4 py-2 bg-slate-900 text-white text-xs font-semibold rounded-lg hover:bg-slate-800 cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal 3: Akun Uji Coba (Development Helper) -->
        <div
            v-if="activeModal === 'demo'"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60"
            @click.self="activeModal = null"
        >
            <div class="w-full max-w-md bg-white rounded-xl shadow-2xl border border-slate-200 p-6 animate-in fade-in duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2 text-slate-900 font-bold text-sm sm:text-base">
                        <KeyRound class="w-5 h-5 text-slate-700" />
                        <span>Akun Uji Coba (Development)</span>
                    </div>
                    <button
                        type="button"
                        @click="activeModal = null"
                        class="text-slate-400 hover:text-slate-600 p-1 rounded-lg cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="mt-4 space-y-2.5 text-xs">
                    <p class="text-slate-500 mb-2">
                        Pilih peran pengujian untuk mengisi kredensial secara otomatis:
                    </p>

                    <button
                        type="button"
                        @click="fillDemoAccount('superadmin', 'superadmin1234')"
                        class="w-full p-2.5 rounded-lg border border-slate-200 hover:border-slate-400 hover:bg-slate-50 transition flex items-center justify-between text-left cursor-pointer"
                    >
                        <div>
                            <div class="font-semibold text-slate-900">Super Administrator (Baru)</div>
                            <div class="text-[11px] text-slate-500">Username: superadmin</div>
                        </div>
                        <span class="text-xs font-medium px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Pilih
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="fillDemoAccount('admin1', 'password')"
                        class="w-full p-2.5 rounded-lg border border-slate-200 hover:border-slate-400 hover:bg-slate-50 transition flex items-center justify-between text-left cursor-pointer"
                    >
                        <div>
                            <div class="font-semibold text-slate-900">Superadmin (Bawaan)</div>
                            <div class="text-[11px] text-slate-500">Username: admin1</div>
                        </div>
                        <span class="text-xs font-medium px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                            Pilih
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="fillDemoAccount('kasiastik')"
                        class="w-full p-2.5 rounded-lg border border-slate-200 hover:border-slate-400 hover:bg-slate-50 transition flex items-center justify-between text-left cursor-pointer"
                    >
                        <div>
                            <div class="font-semibold text-slate-900">Kasi ASTIK</div>
                            <div class="text-[11px] text-slate-500">Username: kasiastik</div>
                        </div>
                        <span class="text-xs font-medium px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                            Pilih
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="fillDemoAccount('ikhwan')"
                        class="w-full p-2.5 rounded-lg border border-slate-200 hover:border-slate-400 hover:bg-slate-50 transition flex items-center justify-between text-left cursor-pointer"
                    >
                        <div>
                            <div class="font-semibold text-slate-900">PJLP (Ikhwan Prasetya)</div>
                            <div class="text-[11px] text-slate-500">Username: ikhwan (NIK: 3314011306920003)</div>
                        </div>
                        <span class="text-xs font-medium px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                            Pilih
                        </span>
                    </button>
                </div>

                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        @click="activeModal = null"
                        class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-medium rounded-lg hover:bg-slate-200 cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
