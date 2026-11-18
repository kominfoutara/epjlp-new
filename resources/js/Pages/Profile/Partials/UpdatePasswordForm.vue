<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Lock, CheckCircle2 } from 'lucide-vue-next';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header class="border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
                <Lock class="w-4 h-4 text-blue-600" />
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                    Perbarui Kata Sandi Akun
                </h3>
            </div>
            <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                Pastikan akun Anda menggunakan kata sandi yang kuat dan tidak dibagikan kepada pihak lain demi menjaga keamanan sistem SPBE.
            </p>
        </header>

        <form @submit.prevent="updatePassword" class="mt-5 space-y-4 text-xs">
            <div>
                <InputLabel for="current_password" value="Kata Sandi Saat Ini" class="font-bold text-slate-700 dark:text-slate-300" />

                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 block w-full text-xs"
                    autocomplete="current-password"
                />

                <InputError
                    :message="form.errors.current_password"
                    class="mt-1.5"
                />
            </div>

            <div>
                <InputLabel for="password" value="Kata Sandi Baru" class="font-bold text-slate-700 dark:text-slate-300" />

                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full text-xs"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" class="mt-1.5" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Konfirmasi Kata Sandi Baru"
                    class="font-bold text-slate-700 dark:text-slate-300"
                />

                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full text-xs"
                    autocomplete="new-password"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-1.5"
                />
            </div>

            <div class="flex items-center gap-4 pt-2">
                <PrimaryButton
                    :disabled="form.processing"
                    class="px-4 py-2 text-xs font-bold bg-blue-600 hover:bg-blue-500 rounded-lg shadow-xs"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Perbarui Kata Sandi' }}
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
                        <span>Kata sandi berhasil diperbarui.</span>
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
