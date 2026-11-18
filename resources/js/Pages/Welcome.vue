<script setup>
import { Head, Link } from '@inertiajs/vue3';
import {
    ShieldCheck,
    FileText,
    Clock,
    MapPin,
    CheckCircle2,
    Building2,
    Users,
    ArrowRight,
    HelpCircle,
    FileSpreadsheet,
    Lock,
    ExternalLink,
    Scale
} from 'lucide-vue-next';

defineProps({
    canLogin: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <Head title="Portal e-Kinerja PJLP - Sudin Kominfotik Jakarta Utara" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 font-sans selection:bg-blue-700 selection:text-white flex flex-col">
        <!-- ============================================================ -->
        <!-- PITA ATAS RESMI PEMERINTAH (SPBE STANDAR NASIONAL)           -->
        <!-- ============================================================ -->
        <div class="bg-[#0b172a] text-slate-300 text-[11px] border-b border-slate-800 px-4 sm:px-8 py-2 flex flex-wrap items-center justify-between gap-2 z-30">
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center gap-1.5 font-bold text-slate-200">
                    <span class="inline-block w-3.5 h-2.5 bg-gradient-to-b from-[#e11d48] via-[#e11d48] to-white rounded-[2px] shadow-xs"></span>
                    SITUS RESMI PEMERINTAH PROVINSI DKI JAKARTA
                </span>
                <span class="hidden md:inline text-slate-600">•</span>
                <span class="hidden md:inline text-slate-400">Pemerintah Kota Administrasi Jakarta Utara</span>
                <span class="hidden lg:inline text-slate-600">•</span>
                <span class="hidden lg:inline text-slate-400">Suku Dinas Komunikasi, Informatika dan Statistik</span>
            </div>

            <div class="flex items-center gap-3 text-[11px]">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950/80 text-emerald-400 border border-emerald-800/80">
                    <ShieldCheck class="w-3 h-3 text-emerald-400" />
                    Kanal Resmi SPBE Terverifikasi
                </span>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- NAVBAR PORTAL                                                -->
        <!-- ============================================================ -->
        <header class="bg-[#0b1b36] border-b border-[#162f58] text-white sticky top-0 z-20 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                <!-- Brand Title & Crest -->
                <div class="flex items-center gap-3.5">
                    <div class="w-12 h-12 rounded-xl bg-white/10 p-1.5 border border-white/20 shadow-lg flex items-center justify-center flex-shrink-0">
                        <img
                            src="/images/logo-jakarta-utara.png"
                            alt="Lambang Kota Administrasi Jakarta Utara"
                            class="w-full h-full object-contain"
                            draggable="false"
                        />
                    </div>
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-amber-400 leading-none mb-1">
                            PEMERINTAH KOTA ADMINISTRASI JAKARTA UTARA
                        </div>
                        <div class="text-base sm:text-lg font-black tracking-tight text-white leading-tight">
                            e-Kinerja & Presensi PJLP
                        </div>
                        <div class="text-[11px] text-slate-300 font-medium leading-none mt-0.5">
                            Suku Dinas Komunikasi, Informatika dan Statistik
                        </div>
                    </div>
                </div>

                <!-- Navigation Action -->
                <div class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('dashboard')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs sm:text-sm shadow-md transition transform active:scale-95"
                    >
                        <span>Buka Dashboard</span>
                        <ArrowRight class="w-4 h-4" />
                    </Link>

                    <Link
                        v-else
                        :href="route('login')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs sm:text-sm shadow-md transition transform active:scale-95"
                    >
                        <span>Masuk ke Sistem</span>
                        <ArrowRight class="w-4 h-4" />
                    </Link>
                </div>
            </div>
        </header>

        <!-- ============================================================ -->
        <!-- HERO SECTION                                                 -->
        <!-- ============================================================ -->
        <section class="relative bg-gradient-to-b from-[#0b1b36] via-[#0d2244] to-slate-900 text-white py-16 sm:py-24 px-4 sm:px-6 lg:px-8 overflow-hidden">
            <!-- Background Texture -->
            <div class="absolute inset-0 bg-cover bg-center opacity-20 pointer-events-none mix-blend-overlay" style="background-image: url('/images/login-bg.jpg');"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(37,99,235,0.25),transparent_50%)] pointer-events-none"></div>

            <div class="relative max-w-7xl mx-auto">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-200 text-xs font-semibold mb-6 backdrop-blur-md">
                        <Building2 class="w-3.5 h-3.5 text-blue-400" />
                        <span>Sistem Pemerintahan Berbasis Elektronik (SPBE)</span>
                    </div>

                    <h1 class="text-3xl sm:text-5xl font-black tracking-tight text-white leading-tight mb-6">
                        Sistem Manajemen Kinerja & Presensi Terpadu PJLP
                    </h1>

                    <p class="text-base sm:text-lg text-slate-300 leading-relaxed mb-8">
                        Portal resmi pelaporan logbook harian, sinkronisasi presensi biometrik fingerprint, dan verifikasi penugasan lapangan bagi Penyedia Jasa Lainnya Perorangan (PJLP) di lingkungan Suku Dinas Komunikasi, Informatika dan Statistik Kota Administrasi Jakarta Utara.
                    </p>

                    <div class="flex flex-wrap items-center gap-4">
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center gap-2.5 px-6 py-3.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold shadow-xl shadow-blue-900/50 transition transform hover:-translate-y-0.5 active:translate-y-0"
                        >
                            <span>Masuk ke Portal e-Kinerja</span>
                            <ArrowRight class="w-4 h-4" />
                        </Link>

                        <a
                            href="#fitur"
                            class="inline-flex items-center gap-2 px-6 py-3.5 rounded-xl bg-white/10 hover:bg-white/15 text-slate-200 text-sm font-semibold border border-white/20 backdrop-blur-md transition"
                        >
                            <span>Pelajari Modul & Regulasi</span>
                        </a>
                    </div>
                </div>

                <!-- Stats Badges -->
                <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-4 border-t border-white/10 pt-8">
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-2xl sm:text-3xl font-black text-amber-400">4 Seksi</div>
                        <div class="text-xs text-slate-300 font-medium mt-1">ASTIK, KIP, Jarkom & TU</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-2xl sm:text-3xl font-black text-emerald-400">Realtime</div>
                        <div class="text-xs text-slate-300 font-medium mt-1">Presensi Fingerprint</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-2xl sm:text-3xl font-black text-sky-400">GPS & Foto</div>
                        <div class="text-xs text-slate-300 font-medium mt-1">Verifikasi Dinas Luar</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white/5 border border-white/10">
                        <div class="text-2xl sm:text-3xl font-black text-purple-400">ISO/SPBE</div>
                        <div class="text-xs text-slate-300 font-medium mt-1">Standar Tata Kelola ASN</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- PILAR LAYANAN SISTEM                                         -->
        <!-- ============================================================ -->
        <section id="fitur" class="py-16 sm:py-24 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <span class="text-xs font-bold uppercase tracking-widest text-blue-600 dark:text-blue-400">
                        Standar Layanan Publik
                    </span>
                    <h2 class="text-2xl sm:text-4xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight mt-2">
                        Pilar Utama Sistem e-Kinerja PJLP
                    </h2>
                    <p class="text-slate-500 dark:text-slate-400 text-sm sm:text-base mt-3">
                        Dirancang khusus untuk mendukung akuntabilitas kinerja, transparansi evaluasi bulanan, dan tertib administrasi kepegawaian non-ASN.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Fitur 1 -->
                    <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-950 text-blue-600 dark:text-blue-400 flex items-center justify-center mb-5 border border-blue-200/60">
                            <FileText class="w-6 h-6" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-2">
                            Logbook Harian Terstruktur
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            Pencatatan rincian pekerjaan per jam dengan pilihan kategori tugas spesifik seksi, uraian hasil kerja, dan bukti fisik foto dokumentasi.
                        </p>
                    </div>

                    <!-- Fitur 2 -->
                    <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-5 border border-emerald-200/60">
                            <Clock class="w-6 h-6" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-2">
                            Presensi Biometrik Terpadu
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            Sinkronisasi data kehadiran masuk dan pulang langsung dari mesin fingerprint kantor Sudin Kominfotik dengan perhitungan ketepatan waktu.
                        </p>
                    </div>

                    <!-- Fitur 3 -->
                    <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400 flex items-center justify-center mb-5 border border-sky-200/60">
                            <MapPin class="w-6 h-6" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-2">
                            Verifikasi Dinas Luar GPS
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            Pengajuan tugas lapangan dengan validasi koordinat geolokasi satelit dan swafoto real-time di lokasi penugasan instansi.
                        </p>
                    </div>

                    <!-- Fitur 4 -->
                    <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 hover:shadow-lg transition">
                        <div class="w-12 h-12 rounded-xl bg-purple-100 dark:bg-purple-950 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-5 border border-purple-200/60">
                            <FileSpreadsheet class="w-6 h-6" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-2">
                            Rekapitulasi SKP & Honor
                        </h3>
                        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                            Kompilasi rekapitulasi kehadiran dan capaian aktivitas otomatis setiap akhir bulan sebagai dasar pembayaran honorarium dan evaluasi perpanjangan kontrak.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- DASAR HUKUM & REGULASI                                       -->
        <!-- ============================================================ -->
        <section class="py-16 bg-slate-100/70 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2.5 rounded-xl bg-amber-500/10 text-amber-600 border border-amber-500/20">
                        <Scale class="w-6 h-6" />
                    </div>
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100">
                            Dasar Hukum & Tata Kelola Regulasi
                        </h2>
                        <p class="text-xs text-slate-500">
                            Penyelenggaraan sistem informasi merujuk pada ketentuan peraturan perundang-undangan yang berlaku
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs text-slate-600 dark:text-slate-300">
                    <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="font-bold text-slate-900 dark:text-slate-100 mb-1">
                            Perpres No. 95 Tahun 2018
                        </div>
                        <div class="text-[11px] text-blue-600 dark:text-blue-400 font-semibold mb-2">
                            Sistem Pemerintahan Berbasis Elektronik (SPBE)
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                            Mewujudkan tata kelola pemerintahan yang bersih, efektif, transparan, dan akuntabel serta pelayanan publik yang berkualitas dan terpercaya.
                        </p>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="font-bold text-slate-900 dark:text-slate-100 mb-1">
                            Pergub DKI Jakarta No. 125 Tahun 2019
                        </div>
                        <div class="text-[11px] text-blue-600 dark:text-blue-400 font-semibold mb-2">
                            Pengelolaan Penyedia Jasa Lainnya Perorangan
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                            Pedoman teknis pengadaan, penugasan, penilaian kinerja harian, dan evaluasi hasil kerja seluruh tenaga PJLP di lingkungan Pemprov DKI Jakarta.
                        </p>
                    </div>

                    <div class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="font-bold text-slate-900 dark:text-slate-100 mb-1">
                            UU No. 27 Tahun 2022
                        </div>
                        <div class="text-[11px] text-blue-600 dark:text-blue-400 font-semibold mb-2">
                            Pelindungan Data Pribadi (UU PDP)
                        </div>
                        <p class="text-slate-500 dark:text-slate-400 leading-relaxed">
                            Penjaminan kerahasiaan data kepegawaian, biodata NIK/PIN, riwayat biometrik presensi, dan bukti dokumen logbook dinas.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- FOOTER RESMI PEMERINTAH                                      -->
        <!-- ============================================================ -->
        <footer class="mt-auto bg-[#071328] border-t border-[#162f58] text-slate-400 text-xs py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8 mb-8 pb-8 border-b border-white/10">
                <!-- Kolom Identitas SKPD -->
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <img
                            src="/images/logo-jakarta-utara.png"
                            alt="Lambang Kota Administrasi Jakarta Utara"
                            class="w-10 h-10 object-contain"
                            draggable="false"
                        />
                        <div>
                            <div class="font-extrabold text-white text-sm">
                                Kota Administrasi Jakarta Utara
                            </div>
                            <div class="text-[11px] text-slate-300">
                                Suku Dinas Komunikasi, Informatika dan Statistik
                            </div>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Gedung Walikota Kota Administrasi Jakarta Utara, Blok B Lantai 4<br />
                        Jl. Laksamana Yos Sudarso No. 27-29, Kebon Bawang, Tanjung Priok, Jakarta 14320
                    </p>
                </div>

                <!-- Kolom Tautan Cepat -->
                <div>
                    <div class="font-bold text-white text-xs uppercase tracking-wider mb-3">
                        Tautan Layanan Terkait
                    </div>
                    <ul class="space-y-1.5 text-[11px]">
                        <li>
                            <a href="https://utara.jakarta.go.id" target="_blank" rel="noopener noreferrer" class="hover:text-blue-400 flex items-center gap-1.5 transition">
                                <ExternalLink class="w-3 h-3 text-slate-500" />
                                <span>Portal Resmi Kota Administrasi Jakarta Utara</span>
                            </a>
                        </li>
                        <li>
                            <a href="https://jakarta.go.id" target="_blank" rel="noopener noreferrer" class="hover:text-blue-400 flex items-center gap-1.5 transition">
                                <ExternalLink class="w-3 h-3 text-slate-500" />
                                <span>Pemerintah Provinsi DKI Jakarta</span>
                            </a>
                        </li>
                        <li>
                            <a href="https://smartcity.jakarta.go.id" target="_blank" rel="noopener noreferrer" class="hover:text-blue-400 flex items-center gap-1.5 transition">
                                <ExternalLink class="w-3 h-3 text-slate-500" />
                                <span>Jakarta Smart City</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Kolom Pusat Bantuan & Kontak -->
                <div>
                    <div class="font-bold text-white text-xs uppercase tracking-wider mb-3">
                        Dukungan Teknis & Helpdesk
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed mb-2">
                        Untuk kendala akses akun, sinkronisasi mesin fingerprint, atau pembaruan data PJLP silakan hubungi tim administrator seksi ASTIK:
                    </p>
                    <div class="text-[11px] text-slate-300 font-mono">
                        Email: kominfotik.ju@jakarta.go.id<br />
                        Waktu Layanan: 07:30 - 16:00 WIB
                    </div>
                </div>
            </div>

            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px]">
                <div>
                    Hak Cipta © 2026 Suku Dinas Kominfotik Kota Administrasi Jakarta Utara. Seluruh Hak Cipta Dilindungi.
                </div>
                <div class="flex items-center gap-3 text-slate-400">
                    <span>Versi SPBE 2.6.4</span>
                    <span>•</span>
                    <span>Protokol Keamanan TLS 1.3</span>
                </div>
            </div>
        </footer>
    </div>
</template>
