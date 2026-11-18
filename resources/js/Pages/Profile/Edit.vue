<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    User,
    Shield,
    Building2,
    Clock,
    Lock,
    KeyRound,
    UserCheck,
    Mail,
    Phone,
    Fingerprint
} from 'lucide-vue-next';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
</script>

<template>
    <Head title="Profil Pegawai & Biodata" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                            Biodata Pegawai
                        </span>
                        <span class="text-xs text-slate-400">• {{ user.seksi || 'Sudin Kominfotik' }}</span>
                    </div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                        Profil Akun & Data Kepegawaian
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Kelola informasi kontak akun, nomor identitas kedinasan, dan kata sandi keamanan sistem
                    </p>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- ============================================================ -->
            <!-- IDENTITAS RESMI PEGAWAI (KARTU ELEKTRONIK KEDINASAN)         -->
            <!-- ============================================================ -->
            <div class="bg-gradient-to-r from-[#0b1b36] via-[#102a54] to-[#0a162b] text-white rounded-2xl p-6 shadow-md border border-[#162f58] relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-500/10 rounded-full blur-2xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-white/10 p-2 border border-white/20 shadow-lg flex items-center justify-center flex-shrink-0">
                            <img
                                src="/images/logo-jakarta-utara.png"
                                alt="Lambang Kota Administrasi Jakarta Utara"
                                class="w-full h-full object-contain"
                                draggable="false"
                            />
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-xl font-black tracking-tight text-white">
                                    {{ user.nama_lengkap || user.name }}
                                </h2>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-400 text-slate-900">
                                    {{ user.role }}
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-950/80 text-emerald-300 border border-emerald-800">
                                    Status: {{ user.status_user || 'Aktif' }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-300 mt-0.5">
                                {{ user.posisi_pekerjaan || 'Penyedia Jasa Lainnya Perorangan (PJLP)' }} • {{ user.seksi || 'Sudin Kominfotik Jakarta Utara' }}
                            </p>
                            <div class="flex flex-wrap items-center gap-4 text-xs font-mono text-slate-400 mt-2">
                                <span v-if="user.pin" class="flex items-center gap-1 text-emerald-400">
                                    <Fingerprint class="w-3.5 h-3.5" />
                                    PIN Fingerprint: {{ user.pin }}
                                </span>
                                <span v-if="user.nik" class="flex items-center gap-1 text-slate-300">
                                    NIK: {{ user.nik }}
                                </span>
                                <span class="flex items-center gap-1 text-slate-300">
                                    <Clock class="w-3.5 h-3.5 text-slate-400" />
                                    Jam Kerja: {{ user.jam_masuk ? user.jam_masuk.slice(0,5) : '07:30' }} - {{ user.jam_pulang ? user.jam_pulang.slice(0,5) : '16:00' }} WIB
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- FORMULIR INFORMASI AKUN & KATA SANDI                         -->
            <!-- ============================================================ -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                </div>

                <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <UpdatePasswordForm />
                </div>
            </div>

            <!-- Danger Zone: Hapus Akun -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-xl border border-rose-200/60 dark:border-rose-950/40 shadow-xs">
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
