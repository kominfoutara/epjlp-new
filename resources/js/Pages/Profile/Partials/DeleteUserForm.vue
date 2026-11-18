<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import { AlertTriangle, Trash2 } from 'lucide-vue-next';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-4">
        <header class="border-b border-rose-100 dark:border-rose-950/40 pb-3">
            <div class="flex items-center gap-2 text-rose-600 dark:text-rose-400">
                <AlertTriangle class="w-4 h-4" />
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">
                    Penonaktifan / Penghapusan Akun
                </h3>
            </div>

            <p class="mt-1 text-xs text-slate-500 leading-relaxed">
                Penghapusan akun akan menonaktifkan akses Anda ke portal e-Kinerja dan mencabut hak pelaporan logbook serta presensi. Hubungi administrator seksi jika Anda tidak yakin.
            </p>
        </header>

        <DangerButton
            type="button"
            @click="confirmUserDeletion"
            class="text-xs font-bold px-4 py-2 rounded-lg"
        >
            Hapus Akun Pengguna
        </DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal" max-width="md">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-rose-100 dark:bg-rose-950 text-rose-600 flex items-center justify-center flex-shrink-0">
                        <Trash2 class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                            Konfirmasi Penghapusan Akun
                        </h3>
                        <p class="text-xs text-slate-500">
                            Tindakan ini tidak dapat dibatalkan
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 dark:text-slate-300 mb-4 leading-relaxed">
                    Apakah Anda yakin ingin menghapus akun Anda secara permanen? Masukkan kata sandi Anda untuk mengonfirmasi permintaan penghapusan akun ini.
                </p>

                <div>
                    <InputLabel
                        for="password"
                        value="Kata Sandi Akun"
                        class="text-xs font-bold"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full text-xs"
                        placeholder="Masukkan kata sandi untuk konfirmasi"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-1.5" />
                </div>

                <div class="mt-6 flex justify-end gap-2.5">
                    <SecondaryButton
                        type="button"
                        @click="closeModal"
                        class="text-xs font-semibold px-4 py-2 rounded-lg"
                    >
                        Batal
                    </SecondaryButton>

                    <DangerButton
                        type="button"
                        class="text-xs font-bold px-4 py-2 rounded-lg"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        Ya, Hapus Akun
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
