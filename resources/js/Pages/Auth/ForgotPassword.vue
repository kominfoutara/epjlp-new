<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { Mail, KeyRound, ArrowLeft } from 'lucide-vue-next';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Pemulihan Kata Sandi Akun" />

        <div class="mb-5 text-center">
            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400 mx-auto flex items-center justify-center mb-2">
                <KeyRound class="w-5 h-5" />
            </div>
            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                Lupa Kata Sandi Akun?
            </h2>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Masukkan alamat email kedinasan Anda yang terdaftar pada sistem e-Kinerja. Kami akan mengirimkan tautan instruksi pengaturan ulang kata sandi yang aman.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 p-3 rounded-xl bg-emerald-50 text-emerald-800 text-xs font-semibold border border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Alamat Email Terdaftar" class="text-xs font-bold" />

                <div class="relative mt-1">
                    <TextInput
                        id="email"
                        type="email"
                        class="w-full pl-9 text-xs"
                        v-model="form.email"
                        required
                        autofocus
                        placeholder="contoh: nama@jakarta.go.id"
                        autocomplete="username"
                    />
                    <Mail class="w-4 h-4 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
                </div>

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div class="pt-2 flex flex-col gap-3">
                <PrimaryButton
                    class="w-full justify-center py-2.5 text-xs font-bold bg-blue-600 hover:bg-blue-500 rounded-xl"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Mengirim Tautan...' : 'Kirim Tautan Pemulihan Kata Sandi' }}
                </PrimaryButton>

                <div class="text-center">
                    <Link
                        :href="route('login')"
                        class="text-xs font-bold text-blue-600 hover:text-blue-500 inline-flex items-center gap-1"
                    >
                        <ArrowLeft class="w-3.5 h-3.5" />
                        <span>Kembali ke Halaman Masuk</span>
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
