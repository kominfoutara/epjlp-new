<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { User, Mail, CheckCircle2 } from 'lucide-vue-next';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.nama_lengkap || user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header class="border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
                <User class="w-4 h-4 text-blue-600" />
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                    Informasi Akun & Kontak
                </h3>
            </div>
            <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                Perbarui nama tampilan akun dan alamat email resmi yang digunakan untuk menerima pemberitahuan sistem.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-5 space-y-4 text-xs"
        >
            <div>
                <InputLabel for="name" value="Nama Lengkap Pegawai" class="font-bold text-slate-700 dark:text-slate-300" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full text-xs"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Alamat Email Kedinasan" class="font-bold text-slate-700 dark:text-slate-300" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full text-xs"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="text-xs text-amber-600">
                    Alamat email Anda belum terverifikasi.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="underline text-blue-600 hover:text-blue-500 font-semibold"
                    >
                        Kirim ulang email verifikasi.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-xs font-semibold text-emerald-600"
                >
                    Tautan verifikasi baru telah dikirimkan ke alamat email Anda.
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <PrimaryButton
                    :disabled="form.processing"
                    class="px-4 py-2 text-xs font-bold bg-blue-600 hover:bg-blue-500 rounded-lg shadow-xs"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                </PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-xs font-semibold text-emerald-600 flex items-center gap-1"
                    >
                        <CheckCircle2 class="w-3.5 h-3.5" />
                        <span>Data berhasil disimpan.</span>
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
