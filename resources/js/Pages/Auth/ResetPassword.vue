<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Lock, KeyRound } from 'lucide-vue-next';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Atur Ulang Kata Sandi" />

        <div class="mb-5 text-center">
            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400 mx-auto flex items-center justify-center mb-2">
                <Lock class="w-5 h-5" />
            </div>
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                Atur Kata Sandi Baru
            </h2>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Tentukan kata sandi baru yang kuat untuk mengamankan akun e-Kinerja Anda.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4 text-xs">
            <div>
                <InputLabel for="email" value="Alamat Email Kedinasan" class="text-xs font-bold" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full text-xs"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Kata Sandi Baru" class="text-xs font-bold" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full text-xs"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Konfirmasi Kata Sandi Baru"
                    class="text-xs font-bold"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full text-xs"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-1.5"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    class="w-full justify-center py-2.5 text-xs font-bold bg-blue-600 hover:bg-blue-500 rounded-xl"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Kata Sandi Baru' }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
