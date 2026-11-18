<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { UserPlus, ArrowLeft } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registrasi Akun e-Kinerja" />

        <div class="mb-5 text-center">
            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400 mx-auto flex items-center justify-center mb-2">
                <UserPlus class="w-5 h-5" />
            </div>
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                Pendaftaran Akun Pegawai
            </h2>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Khusus pegawai terdaftar di lingkungan Suku Dinas Kominfotik Kota Administrasi Jakarta Utara.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4 text-xs">
            <div>
                <InputLabel for="name" value="Nama Lengkap Pegawai" class="text-xs font-bold" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full text-xs"
                    v-model="form.name"
                    required
                    autofocus
                    placeholder="Nama lengkap sesuai KTP"
                    autocomplete="name"
                />

                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Alamat Email Kedinasan" class="text-xs font-bold" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full text-xs"
                    v-model="form.email"
                    required
                    placeholder="contoh@jakarta.go.id"
                    autocomplete="username"
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Kata Sandi Akun" class="text-xs font-bold" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full text-xs"
                    v-model="form.password"
                    required
                    placeholder="Minimal 8 karakter"
                    autocomplete="new-password"
                />

                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Konfirmasi Kata Sandi"
                    class="text-xs font-bold"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full text-xs"
                    v-model="form.password_confirmation"
                    required
                    placeholder="Ulangi kata sandi"
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-1.5"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="pt-2 flex flex-col gap-3">
                <PrimaryButton
                    class="w-full justify-center py-2.5 text-xs font-bold bg-blue-600 hover:bg-blue-500 rounded-xl"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Memproses...' : 'Daftar Akun e-Kinerja' }}
                </PrimaryButton>

                <div class="text-center">
                    <Link
                        :href="route('login')"
                        class="text-xs font-bold text-blue-600 hover:text-blue-500 inline-flex items-center gap-1"
                    >
                        <ArrowLeft class="w-3.5 h-3.5" />
                        <span>Sudah memiliki akun? Masuk di sini</span>
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
