<script setup>
import { Link } from '@inertiajs/vue3';
import { ShieldCheck, ArrowLeft, Building2 } from 'lucide-vue-next';
</script>

<template>
    <div class="min-h-screen flex flex-col bg-slate-900 text-slate-800 dark:text-slate-100 font-sans relative selection:bg-blue-700 selection:text-white">
        <!-- Background Imagery & Overlay -->
        <div class="absolute inset-0 bg-[#0a1628] bg-cover bg-center opacity-40 mix-blend-overlay pointer-events-none" style="background-image: url('/images/login-bg.jpg');"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-[#071325]/90 via-[#0a1b33]/95 to-[#050e1a] pointer-events-none"></div>

        <!-- ============================================================ -->
        <!-- PITA ATAS RESMI PEMERINTAH (SPBE STANDAR)                    -->
        <!-- ============================================================ -->
        <header class="relative z-10 bg-[#061120] border-b border-[#132a4e] text-slate-300 text-[11px] px-4 sm:px-6 py-2 flex items-center justify-between">
            <div class="flex items-center gap-2 sm:gap-3">
                <span class="inline-flex items-center gap-1.5 font-bold text-slate-200">
                    <span class="inline-block w-3.5 h-2.5 bg-gradient-to-b from-[#e11d48] via-[#e11d48] to-white rounded-[2px] shadow-xs"></span>
                    SITUS RESMI PEMERINTAH PROVINSI DKI JAKARTA
                </span>
                <span class="hidden md:inline text-slate-600">•</span>
                <span class="hidden md:inline text-slate-400">Kota Administrasi Jakarta Utara</span>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950/80 text-emerald-400 border border-emerald-800/80">
                    <ShieldCheck class="w-3 h-3 text-emerald-400" />
                    SPBE Terverifikasi
                </span>
            </div>
        </header>

        <!-- ============================================================ -->
        <!-- CONTAINER KONTEN                                             -->
        <!-- ============================================================ -->
        <main class="relative z-10 flex-1 flex flex-col items-center justify-center p-4 sm:p-6 lg:p-8">
            <div class="w-full max-w-md">
                <!-- Branding Lembaga -->
                <div class="flex flex-col items-center text-center mb-6">
                    <Link href="/" class="group flex flex-col items-center">
                        <div class="w-16 h-16 rounded-2xl bg-white/10 p-2 border border-white/20 shadow-xl backdrop-blur-md mb-3 flex items-center justify-center transition-transform group-hover:scale-105">
                            <img
                                src="/images/logo-jakarta-utara.png"
                                alt="Lambang Kota Administrasi Jakarta Utara"
                                class="w-full h-full object-contain"
                                draggable="false"
                            />
                        </div>
                        <span class="text-[11px] font-extrabold tracking-widest uppercase text-amber-400">
                            Pemerintah Kota Administrasi Jakarta Utara
                        </span>
                        <h1 class="text-xl font-black tracking-tight text-white mt-0.5">
                            e-Kinerja & Presensi PJLP
                        </h1>
                        <p class="text-xs text-slate-400">
                            Suku Dinas Komunikasi, Informatika dan Statistik
                        </p>
                    </Link>
                </div>

                <!-- Card Form Utama -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8 text-slate-800 dark:text-slate-100">
                    <slot />
                </div>

                <!-- Back to Home / Login link -->
                <div class="mt-4 text-center">
                    <Link
                        href="/"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white transition"
                    >
                        <ArrowLeft class="w-3.5 h-3.5" />
                        <span>Kembali ke Halaman Utama</span>
                    </Link>
                </div>
            </div>
        </main>

        <!-- ============================================================ -->
        <!-- FOOTER RESMI                                                 -->
        <!-- ============================================================ -->
        <footer class="relative z-10 bg-[#061120] border-t border-[#132a4e] py-4 px-4 text-center text-[11px] text-slate-400">
            <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
                <span>Hak Cipta © 2026 Suku Dinas Kominfotik Kota Administrasi Jakarta Utara</span>
                <span class="text-slate-400 font-medium">Berdasarkan Pergub DKI Jakarta No. 125/2019</span>
            </div>
        </footer>
    </div>
</template>
