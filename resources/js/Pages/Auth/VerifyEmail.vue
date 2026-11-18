<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck, LogOut } from 'lucide-vue-next';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email Kedinasan" />

        <div class="mb-5 text-center">
            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400 mx-auto flex items-center justify-center mb-2">
                <MailCheck class="w-5 h-5" />
            </div>
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                Verifikasi Alamat Email
            </h2>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Terima kasih telah mendaftar. Sebelum memulai, silakan verifikasi alamat email Anda melalui tautan aktivasi yang telah kami kirimkan ke kotak masuk email Anda.
            </p>
        </div>

        <div
            class="mb-4 p-3 rounded-xl bg-emerald-50 text-emerald-800 text-xs font-semibold border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300"
            v-if="verificationLinkSent"
        >
            Tautan verifikasi baru telah berhasil dikirimkan ke alamat email kedinasan yang Anda daftarkan.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                <PrimaryButton
                    class="w-full sm:w-auto py-2.5 px-4 text-xs font-bold bg-blue-600 hover:bg-blue-500 rounded-xl"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Mengirim...' : 'Kirim Ulang Email Verifikasi' }}
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-xs font-bold text-rose-600 hover:text-rose-500 inline-flex items-center gap-1"
                >
                    <LogOut class="w-3.5 h-3.5" />
                    <span>Keluar Sistem</span>
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
