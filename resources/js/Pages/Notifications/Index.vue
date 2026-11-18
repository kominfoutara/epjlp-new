<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    notifications: Object,
    unreadCount: Number,
    currentFilter: String,
});

const activeFilter = ref(props.currentFilter || 'all');
const localUnreadCount = ref(props.unreadCount);

const filterByStatus = (status) => {
    activeFilter.value = status;
    router.get(route('notifications.index'), {
        status: status !== 'all' ? status : undefined,
    }, { preserveState: true, replace: true });
};

const markRead = async (notif) => {
    if (!notif.is_read) {
        try {
            await axios.post(route('notifications.read', notif.id));
            notif.is_read = true;
            if (localUnreadCount.value > 0) localUnreadCount.value--;
        } catch (e) {}
    }
    if (notif.link) {
        router.visit(notif.link);
    }
};

const markAllAsRead = async () => {
    try {
        await axios.post(route('notifications.mark-all-read'));
        localUnreadCount.value = 0;
        props.notifications.data.forEach(n => n.is_read = true);
    } catch (e) {}
};

const deleteNotification = async (notif) => {
    if (!confirm('Hapus notifikasi ini?')) return;
    try {
        await axios.delete(route('notifications.destroy', notif.id));
        const index = props.notifications.data.findIndex(n => n.id === notif.id);
        if (index !== -1) {
            props.notifications.data.splice(index, 1);
        }
    } catch (e) {}
};

const formatTime = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (e) {
        return dateStr;
    }
};
</script>

<template>
    <Head title="Pusat Notifikasi Sistem" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                            Pemberitahuan
                        </span>
                        <span class="text-xs text-slate-400">•</span>
                        <span class="text-xs font-medium text-slate-500">Real-time Feed</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight mt-1">
                        Pusat Notifikasi Sistem
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Daftar seluruh notifikasi pengajuan aktivitas, verifikasi dinas luar, dan peringatan akun Anda.
                    </p>
                </div>

                <div class="flex items-center gap-2.5">
                    <button v-if="localUnreadCount > 0"
                        @click="markAllAsRead"
                        type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-lg text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-xs transition">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Tandai Semua Dibaca</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Filter Bar -->
            <div class="bg-white border border-slate-200 rounded-xl p-3 sm:p-4 flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2">
                    <button @click="filterByStatus('all')"
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                        :class="activeFilter === 'all'
                            ? 'bg-slate-900 text-white shadow-xs'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'">
                        Semua ({{ notifications.total || notifications.data.length }})
                    </button>
                    <button @click="filterByStatus('unread')"
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
                        :class="activeFilter === 'unread'
                            ? 'bg-slate-900 text-white shadow-xs'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'">
                        <span>Belum Dibaca</span>
                        <span v-if="localUnreadCount > 0" class="px-1.5 py-0.2 rounded-full text-[10px] bg-rose-600 text-white">
                            {{ localUnreadCount }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Notifications Card List -->
            <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden divide-y divide-slate-100">
                <div v-for="notif in notifications.data" :key="notif.id"
                    class="p-4 sm:p-5 flex items-start gap-4 transition group hover:bg-slate-50/80"
                    :class="notif.is_read ? 'bg-white' : 'bg-slate-50/60'">
                    <!-- Type Icon -->
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5 shadow-2xs"
                        :class="{
                            'bg-emerald-50 text-emerald-600 border border-emerald-200': notif.type === 'success',
                            'bg-rose-50 text-rose-600 border border-rose-200': notif.type === 'danger',
                            'bg-amber-50 text-amber-600 border border-amber-200': notif.type === 'warning',
                            'bg-sky-50 text-sky-600 border border-sky-200': notif.type === 'info' || !notif.type,
                        }">
                        <svg v-if="notif.type === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <svg v-else-if="notif.type === 'danger'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        <svg v-else-if="notif.type === 'warning'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>

                    <!-- Details -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-900">{{ notif.title }}</h3>
                                <span v-if="!notif.is_read" class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                    Baru
                                </span>
                                <span v-if="notif.module" class="text-[11px] font-medium text-slate-400">
                                    • {{ notif.module }}
                                </span>
                            </div>
                            <span class="text-xs font-mono text-slate-400 whitespace-nowrap">
                                {{ formatTime(notif.created_at) }}
                            </span>
                        </div>

                        <p class="text-xs text-slate-700 mt-1 leading-relaxed">
                            {{ notif.message }}
                        </p>

                        <!-- Action Link -->
                        <div class="mt-3 flex items-center gap-3">
                            <button v-if="notif.link"
                                @click="markRead(notif)"
                                type="button"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-900 text-white hover:bg-slate-800 transition">
                                <span>Buka Modul</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </button>

                            <button v-if="!notif.is_read"
                                @click="markRead(notif)"
                                type="button"
                                class="text-xs font-medium text-slate-500 hover:text-slate-800 hover:underline">
                                Tandai Dibaca
                            </button>

                            <button @click="deleteNotification(notif)"
                                type="button"
                                class="text-xs font-medium text-rose-600 hover:text-rose-800 hover:underline ml-auto">
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="notifications.data.length === 0" class="py-16 text-center">
                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div class="text-sm font-semibold text-slate-800">Tidak ada notifikasi</div>
                    <div class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        Anda sudah membaca semua notifikasi terbaru dari sistem e-Kinerja.
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="notifications.links && notifications.links.length > 3" class="p-4 bg-white border border-slate-200 rounded-xl flex items-center justify-between">
                <div class="text-xs text-slate-500">
                    Menampilkan <span class="font-semibold text-slate-900">{{ notifications.from || 0 }}</span> - <span class="font-semibold text-slate-900">{{ notifications.to || 0 }}</span> dari {{ notifications.total }} notifikasi
                </div>
                <div class="flex items-center gap-1">
                    <template v-for="(link, i) in notifications.links" :key="i">
                        <span v-if="!link.url"
                            v-html="link.label"
                            class="px-2.5 py-1 text-xs rounded border border-slate-200 text-slate-300 bg-white cursor-not-allowed">
                        </span>
                        <Link v-else
                            :href="link.url"
                            v-html="link.label"
                            class="px-2.5 py-1 text-xs rounded border transition"
                            :class="link.active
                                ? 'bg-slate-900 text-white border-slate-900 font-semibold'
                                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                            preserve-scroll />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
