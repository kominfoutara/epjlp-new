<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const user = computed(() => page.props.auth.user || {});
const isPjlp = computed(() => user.value.role === 'PJLP');
const isKasi = computed(() => user.value.role === 'Kasi');
const isKasudin = computed(() => user.value.role === 'Kasudin');
const isKasiOrAdmin = computed(() => ['Kasi', 'Superadmin', 'Kasudin'].includes(user.value.role));
const isSuperadmin = computed(() => user.value.role === 'Superadmin');

const isActivityValidationActive = computed(() => {
    if (page.url.includes('/aktivitas/saya') || route().current('activity.my')) {
        return false;
    }
    if (user.value.id && page.url.includes(`user_id=${user.value.id}`)) {
        return false;
    }
    return route().current('activity.*') && !page.url.includes('status=Menunggu');
});

const isMyActivityActive = computed(() => {
    if (route().current('activity.my') || page.url.includes('/aktivitas/saya')) {
        return true;
    }
    if (isKasi.value && user.value.id && page.url.includes(`user_id=${user.value.id}`)) {
        return true;
    }
    if (isPjlp.value && route().current('activity.*') && !page.url.includes('status=Menunggu')) {
        return true;
    }
    return false;
});

const showingMobileSidebar = ref(false);

// User Initials
const userInitials = computed(() => {
    const name = user.value.nama_lengkap || user.value.name || 'User';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
});

// User Subtitle
const userSubtitle = computed(() => {
    if (isPjlp.value) {
        return 'PJLP - Sudin Kominfotik';
    }
    if (user.value.role === 'Kasi') {
        return (user.value.seksi || 'Kepala Seksi') + ' - Sudin Kominfotik';
    }
    if (user.value.role === 'Kasudin') {
        return 'Kepala Suku Dinas Kominfotik';
    }
    return 'Administrator - Sudin Kominfotik';
});

// Reminder / Pending count from props if available
const pendingCount = computed(() => {
    return page.props.kpiStats?.menunggu || page.props.pendingActivitiesCount || 0;
});

// Laporan Kendala active count (reactive from props + realtime polling)
const dynamicPendingIssuesCount = ref(page.props.pendingIssuesCount || 0);

watch(() => page.props.pendingIssuesCount, (newVal) => {
    if (newVal !== undefined && newVal !== null) {
        dynamicPendingIssuesCount.value = newVal;
    }
});

const pendingIssuesCount = computed(() => {
    return dynamicPendingIssuesCount.value ?? page.props.pendingIssuesCount ?? 0;
});

// Real-time Notifications state
const unreadNotificationCount = ref(0);
const recentNotifications = ref([]);
const showNotificationDropdown = ref(false);
const toastNotification = ref(null);
const lastKnownNotificationId = ref(null);
const notificationDropdownRef = ref(null);
let pollingInterval = null;
let toastTimeout = null;
let handleVisibilityChange = null;

// Audio notification chime (Web Audio API - no static asset dependency)
const playNotificationSound = () => {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // Note D5
        osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.12); // Note A5
        gain.gain.setValueAtTime(0.08, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.32);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.33);
    } catch (e) {
        // Safe to ignore if browser policy requires user interaction first
    }
};

const fetchNotifications = async (initial = false) => {
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
        return; // Pause when tab is not active to conserve bandwidth
    }
    try {
        const response = await axios.get(route('notifications.unread'));
        const newUnread = response.data.unread_count || 0;
        const latest = response.data.latest || [];
        const pendingIssues = response.data.pending_issues_count;

        if (pendingIssues !== undefined && pendingIssues !== null) {
            dynamicPendingIssuesCount.value = pendingIssues;
        }

        const newest = latest.length > 0 ? latest[0] : null;

        if (initial) {
            lastKnownNotificationId.value = newest?.id || null;
        } else if (newest && !newest.is_read) {
            const isBrandNew = (lastKnownNotificationId.value !== null && newest.id !== lastKnownNotificationId.value)
                || (newUnread > unreadNotificationCount.value);

            if (isBrandNew) {
                lastKnownNotificationId.value = newest.id;
                showToast(newest);
                playNotificationSound();

                // Dispatch global event so active pages (e.g. Laporan Kendala, Dashboard) update immediately
                window.dispatchEvent(new CustomEvent('app-notification-received', {
                    detail: {
                        notification: newest,
                        unreadCount: newUnread,
                        pendingIssuesCount: pendingIssues,
                    }
                }));
            }
        }

        unreadNotificationCount.value = newUnread;
        recentNotifications.value = latest;
    } catch (e) {
        // Silently catch network errors during polling
    }
};

const showToast = (notif) => {
    toastNotification.value = notif;
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => {
        toastNotification.value = null;
    }, 6000);
};

const toggleNotificationDropdown = () => {
    showNotificationDropdown.value = !showNotificationDropdown.value;
    if (showNotificationDropdown.value) {
        fetchNotifications(true);
    }
};

const handleClickOutsideNotification = (event) => {
    if (showNotificationDropdown.value && notificationDropdownRef.value) {
        if (!notificationDropdownRef.value.contains(event.target)) {
            showNotificationDropdown.value = false;
        }
    }
};

const handleKeyDownNotification = (event) => {
    if (event.key === 'Escape' && showNotificationDropdown.value) {
        showNotificationDropdown.value = false;
    }
};

const handleNotificationClick = async (notif) => {
    if (!notif.is_read) {
        try {
            await axios.post(route('notifications.read', notif.id));
            notif.is_read = true;
            if (unreadNotificationCount.value > 0) {
                unreadNotificationCount.value--;
            }
        } catch (e) {}
    }
    showNotificationDropdown.value = false;
    const targetUrl = notif.url || notif.link;
    if (targetUrl) {
        router.visit(targetUrl);
    }
};

const markAllNotificationsRead = async () => {
    try {
        await axios.post(route('notifications.mark-all-read'));
        unreadNotificationCount.value = 0;
        recentNotifications.value.forEach(n => n.is_read = true);
    } catch (e) {}
};

const formatTimeAgo = (dateStr) => {
    if (!dateStr) return '';
    try {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const seconds = Math.floor(diffMs / 1000);
        if (seconds < 60) return 'Baru saja';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `${minutes}m lalu`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}j lalu`;
        const days = Math.floor(hours / 24);
        if (days < 7) return `${days}h lalu`;
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    } catch (e) {
        return '';
    }
};

// Flash message auto-dismiss (2 detik)
const showFlashSuccess = ref(false);
const flashSuccessMessage = ref('');
let flashSuccessTimeout = null;

const showFlashError = ref(false);
const flashErrorMessage = ref('');
let flashErrorTimeout = null;

watch(() => page.props.flash?.success, (newVal) => {
    if (newVal) {
        flashSuccessMessage.value = newVal;
        showFlashSuccess.value = true;
        if (flashSuccessTimeout) clearTimeout(flashSuccessTimeout);
        flashSuccessTimeout = setTimeout(() => {
            showFlashSuccess.value = false;
        }, 2000);
    } else {
        showFlashSuccess.value = false;
    }
}, { immediate: true });

watch(() => page.props.flash?.error, (newVal) => {
    if (newVal) {
        flashErrorMessage.value = newVal;
        showFlashError.value = true;
        if (flashErrorTimeout) clearTimeout(flashErrorTimeout);
        flashErrorTimeout = setTimeout(() => {
            showFlashError.value = false;
        }, 2000);
    } else {
        showFlashError.value = false;
    }
}, { immediate: true });

onMounted(() => {
    fetchNotifications(true);
    // Realtime polling every 4 seconds for instant responsiveness
    pollingInterval = setInterval(() => {
        fetchNotifications(false);
    }, 4000);

    // Immediately fetch notifications when user returns to this tab
    handleVisibilityChange = () => {
        if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
            fetchNotifications(false);
        }
    };
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // Close notification dropdown when clicking anywhere outside or pressing Esc
    document.addEventListener('pointerdown', handleClickOutsideNotification);
    document.addEventListener('keydown', handleKeyDownNotification);
});

onUnmounted(() => {
    if (pollingInterval) clearInterval(pollingInterval);
    if (handleVisibilityChange) {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    }
    document.removeEventListener('pointerdown', handleClickOutsideNotification);
    document.removeEventListener('keydown', handleKeyDownNotification);
    if (toastTimeout) clearTimeout(toastTimeout);
    if (flashSuccessTimeout) clearTimeout(flashSuccessTimeout);
    if (flashErrorTimeout) clearTimeout(flashErrorTimeout);
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-800 print:bg-white print:min-h-0 print:text-black">
        <!-- ============================================================ -->
        <!-- SIDEBAR (Desktop) -->
        <!-- ============================================================ -->
        <aside class="print:hidden hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:flex lg:flex-col lg:w-64 bg-white border-r border-slate-200 text-slate-600 select-none">
            <!-- Brand Logo -->
            <div class="h-16 px-5 flex items-center gap-3 border-b border-slate-200 bg-white flex-shrink-0">
                <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center shadow-xs">
                    <!-- Jakarta Utara Emblem SVG -->
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div class="flex flex-col leading-tight">
                    <span class="font-bold text-sm tracking-tight text-slate-900 flex items-center gap-1.5">
                        e-Kinerja PJLP
                    </span>
                    <span class="text-[11px] font-medium text-slate-500 tracking-tight">
                        Kota Adm. Jakarta Utara
                    </span>
                </div>
            </div>

            <!-- Navigation Links -->
            <div class="flex-1 overflow-y-auto py-5 px-3 space-y-6 custom-scrollbar">
                <!-- Group 1: Menu Utama -->
                <div>
                    <div class="px-3 mb-2 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        Menu Utama
                    </div>
                    <nav class="space-y-1">
                        <!-- Dashboard -->
                        <Link :href="route('dashboard')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('dashboard')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('dashboard') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span>Dashboard</span>
                        </Link>

                        <!-- Validasi Aktivitas (Kasi, Kasudin, Superadmin) -->
                        <Link v-if="isKasiOrAdmin" :href="route('activity.index')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="isActivityValidationActive
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <!-- Clipboard Check Icon -->
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="isActivityValidationActive ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            <span>{{ isSuperadmin ? 'Rekap Aktivitas' : 'Validasi Aktivitas' }}</span>
                        </Link>

                        <!-- Aktivitas Harian (PJLP & Kasi) -->
                        <Link v-if="isPjlp || isKasi" :href="isKasi ? route('activity.my') : route('activity.index')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="isMyActivityActive
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <!-- Document / Pencil Note Icon -->
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="isMyActivityActive ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>Aktivitas Harian</span>
                        </Link>

                        <!-- Absensi -->
                        <Link :href="route('attendance.index')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('attendance.index')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('attendance.index') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Absensi Pegawai</span>
                        </Link>

                        <!-- Rekap Absensi -->
                        <Link :href="route('attendance.recap')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('attendance.recap')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('attendance.recap') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Rekap Absensi</span>
                        </Link>

                        <!-- Dinas Luar -->
                        <Link :href="route('dinas-luar.index')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('dinas-luar.*')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('dinas-luar.*') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Dinas Luar (DL)</span>
                        </Link>

                        <!-- Reminder -->
                        <Link v-if="isKasiOrAdmin" :href="route('activity-download.index')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition"
                            :class="route().current('activity-download.*') ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200' : 'text-slate-600 hover:bg-slate-100/70'">
                            <span>Tarik Data Aktivitas PJLP</span>
                        </Link>
                        <Link v-if="isPjlp || isKasiOrAdmin" :href="route('reminder.index')"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('reminder.index')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <div class="flex items-center gap-3">
                                <svg class="w-4 h-4 flex-shrink-0 text-slate-400 group-hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span>Reminder Aktivitas</span>
                            </div>
                        </Link>

                        <!-- Laporan Kendala (Aduan IT ke Superadmin) -->
                        <Link :href="route('issue-reports.index')"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('issue-reports.*')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <div class="flex items-center gap-3">
                                <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('issue-reports.*') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <span>Laporan Kendala</span>
                            </div>
                            <span v-if="pendingIssuesCount > 0" class="px-1.5 py-0.5 text-[10px] font-mono font-semibold rounded bg-amber-100 text-amber-800 border border-amber-200">
                                {{ pendingIssuesCount }}
                            </span>
                        </Link>
                    </nav>
                </div>

                <!-- Group 2: Manajemen & Master Data -->
                <div v-if="isKasiOrAdmin">
                    <div class="px-3 mb-2 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        Manajemen & Master
                    </div>
                    <nav class="space-y-1">
                        <!-- Manajemen Pengguna (Kasi / Admin) -->
                        <Link :href="route('pjlp.index')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('pjlp.*')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('pjlp.*') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span>Manajemen Pengguna</span>
                        </Link>

                        <!-- Kategori Aktivitas -->
                        <Link :href="route('categories.index')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('categories.*')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('categories.*') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                            <span>Kategori Aktivitas</span>
                        </Link>

                        <!-- Master Seksi (Khusus Superadmin) -->
                        <Link v-if="isSuperadmin" :href="route('seksi.index')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('seksi.*')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('seksi.*') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span>Master Seksi</span>
                        </Link>

                        <!-- Kelola Pengguna (Khusus Superadmin) -->
                        <Link v-if="isSuperadmin" :href="route('users.index')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('users.*')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('users.*') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span>Kelola Pengguna</span>
                        </Link>

                        <!-- User Sementara & Registrasi (Khusus Superadmin) -->
                        <Link v-if="isSuperadmin" :href="route('temporary-users.index')"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('temporary-users.*')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <div class="flex items-center gap-3">
                                <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('temporary-users.*') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>User Sementara</span>
                            </div>
                            <span v-if="page.props.pendingSubmissionsCount > 0" class="px-1.5 py-0.5 text-[10px] font-mono font-semibold rounded bg-amber-100 text-amber-800 border border-amber-200">
                                {{ page.props.pendingSubmissionsCount }}
                            </span>
                        </Link>

                        <!-- Log Aktivitas (Superadmin, Kasudin, Kasi) -->
                        <Link v-if="isKasiOrAdmin" :href="route('activity-logs.index')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('activity-logs.*')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('activity-logs.*') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Log Aktivitas</span>
                        </Link>
                    </nav>
                </div>

                <!-- Group 3: Profil & Keluar -->
                <div>
                    <div class="px-3 mb-2 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                        Profil & Keluar
                    </div>
                    <nav class="space-y-1">
                        <!-- Profil Saya -->
                        <Link :href="route('profile.edit')"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition group"
                            :class="route().current('profile.edit')
                                ? 'bg-slate-100 text-slate-900 font-semibold border border-slate-200/90 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'">
                            <svg class="w-4 h-4 flex-shrink-0 transition" :class="route().current('profile.edit') ? 'text-slate-900' : 'text-slate-400 group-hover:text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Profil Saya</span>
                        </Link>

                        <!-- Keluar Sistem -->
                        <Link :href="route('logout')" method="post" as="button"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition text-rose-600 hover:text-rose-700 hover:bg-rose-50/70 group text-left">
                            <svg class="w-4 h-4 flex-shrink-0 text-rose-500 group-hover:text-rose-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>Keluar Sistem</span>
                        </Link>
                    </nav>
                </div>
            </div>

            <!-- Footer Sidebar Info -->
            <div class="p-4 border-t border-slate-200 bg-slate-50/70 text-[11px] text-slate-500 space-y-1 flex-shrink-0">
                <div class="flex items-center justify-between text-slate-700 font-semibold mb-1">
                    <span class="inline-flex items-center gap-1.5 text-slate-700 font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Sistem Daring
                    </span>
                    <span class="text-slate-400 font-mono text-[10px]">JKT-UTARA</span>
                </div>
                <div class="text-[10px] text-slate-500 leading-tight">
                    Hak Cipta © 2026 - Sudin Kominfotik Jakarta Utara
                </div>
                <div class="text-[10px] text-slate-400">
                    Versi 1.0
                </div>
            </div>
        </aside>

        <!-- ============================================================ -->
        <!-- MAIN CONTENT AREA -->
        <!-- ============================================================ -->
        <div class="lg:pl-64 flex flex-col min-h-screen min-w-0 print:pl-0 print:m-0 print:block print:min-h-0 print:w-full">
            <!-- Top Navbar -->
            <header class="print:hidden h-16 bg-white border-b border-slate-200 px-4 sm:px-6 lg:px-8 flex items-center justify-between sticky top-0 z-20 backdrop-blur-md bg-white/95 shadow-xs">
                <!-- Left: Hamburger (mobile) + Breadcrumbs -->
                <div class="flex items-center gap-3">
                    <button @click="showingMobileSidebar = !showingMobileSidebar"
                        type="button"
                        class="lg:hidden p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Breadcrumbs -->
                    <div class="hidden sm:flex items-center gap-2 text-xs font-medium text-slate-500">
                        <span class="hover:text-slate-700 transition">Pemerintah Kota Jakarta Utara</span>
                        <span>›</span>
                        <span class="text-slate-900 font-semibold">e-Kinerja Portal</span>
                    </div>
                </div>

                <!-- Right: Notification & User Profile -->
                <div class="flex items-center gap-3 sm:gap-4">
                    <!-- Interactive Real-time Notification Bell Dropdown -->
                    <div class="relative" ref="notificationDropdownRef">
                        <button @click="toggleNotificationDropdown"
                            type="button"
                            class="p-2 text-slate-500 hover:text-slate-800 rounded-full hover:bg-slate-100 transition relative focus:outline-none"
                            title="Notifikasi Sistem Real-time">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <!-- Live Unread Badge Counter -->
                            <span v-if="unreadNotificationCount > 0"
                                class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white font-mono font-bold text-[10px] flex items-center justify-center ring-2 ring-white animate-pulse">
                                {{ unreadNotificationCount > 99 ? '99+' : unreadNotificationCount }}
                            </span>
                        </button>

                        <!-- Notification Dropdown Panel -->
                        <transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="transform scale-95 opacity-0"
                            enter-to-class="transform scale-100 opacity-100"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="transform scale-100 opacity-100"
                            leave-to-class="transform scale-95 opacity-0"
                        >
                            <div v-if="showNotificationDropdown"
                                class="absolute right-0 mt-2 w-80 sm:w-96 max-w-[calc(100vw-2rem)] bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-slate-100">
                                <!-- Panel Header -->
                                <div class="p-3.5 bg-slate-50 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-900">Notifikasi Sistem</span>
                                        <span v-if="unreadNotificationCount > 0" class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-rose-100 text-rose-700">
                                            {{ unreadNotificationCount }} Baru
                                        </span>
                                    </div>
                                    <button v-if="unreadNotificationCount > 0"
                                        @click="markAllNotificationsRead"
                                        type="button"
                                        class="text-[11px] font-semibold text-slate-600 hover:text-slate-900 hover:underline">
                                        Tandai Semua Dibaca
                                    </button>
                                </div>

                                <!-- Notifications List -->
                                <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                                    <div v-for="notif in recentNotifications" :key="notif.id"
                                        @click="handleNotificationClick(notif)"
                                        class="p-3.5 hover:bg-slate-50 cursor-pointer transition flex items-start gap-3"
                                        :class="notif.is_read ? 'opacity-70 bg-white' : 'bg-slate-50/60'">
                                        <!-- Type Icon -->
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5"
                                            :class="{
                                                'bg-emerald-50 text-emerald-600 border border-emerald-200': notif.type === 'success',
                                                'bg-rose-50 text-rose-600 border border-rose-200': notif.type === 'danger',
                                                'bg-amber-50 text-amber-600 border border-amber-200': notif.type === 'warning',
                                                'bg-sky-50 text-sky-600 border border-sky-200': notif.type === 'info' || !notif.type,
                                            }">
                                            <svg v-if="notif.type === 'success'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            <svg v-else-if="notif.type === 'danger'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            <svg v-else-if="notif.type === 'warning'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </div>

                                        <!-- Content -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="text-xs font-bold text-slate-900 truncate">{{ notif.title }}</span>
                                                <span class="text-[10px] text-slate-400 whitespace-nowrap">{{ formatTimeAgo(notif.created_at) }}</span>
                                            </div>
                                            <p class="text-xs text-slate-600 mt-0.5 line-clamp-2 leading-relaxed">
                                                {{ notif.message }}
                                            </p>
                                        </div>

                                        <!-- Unread Dot Indicator -->
                                        <div v-if="!notif.is_read" class="w-2 h-2 rounded-full bg-rose-500 mt-1 flex-shrink-0"></div>
                                    </div>

                                    <!-- Empty State -->
                                    <div v-if="recentNotifications.length === 0" class="p-6 text-center">
                                        <div class="w-8 h-8 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                            </svg>
                                        </div>
                                        <div class="text-xs font-semibold text-slate-700">Tidak ada notifikasi baru</div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">Semua pembaruan sistem akan muncul di sini.</div>
                                    </div>
                                </div>

                                <!-- Panel Footer -->
                                <div class="p-2.5 bg-slate-50 text-center">
                                    <Link :href="route('notifications.index')"
                                        @click="showNotificationDropdown = false"
                                        class="text-xs font-semibold text-slate-700 hover:text-slate-900 hover:underline">
                                        Buka Daftar Notifikasi Lengkap →
                                    </Link>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <!-- User Profile Info Badge (No Pop-up) -->
                    <div class="flex items-center gap-2.5 p-1 rounded-xl text-left select-none">
                        <div class="w-8 h-8 rounded-lg bg-slate-900 text-white font-black text-xs flex items-center justify-center tracking-tight shadow-xs">
                            {{ userInitials }}
                        </div>
                        <div class="hidden md:flex flex-col text-left leading-tight">
                            <span class="text-xs font-bold text-slate-800 line-clamp-1">
                                {{ user.nama_lengkap || user.name }}
                            </span>
                            <span class="text-[11px] text-slate-500 font-medium line-clamp-1">
                                {{ userSubtitle }}
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash Notifications (Auto-dismiss 2 detik) -->
            <transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2 max-h-0"
                enter-to-class="opacity-100 translate-y-0 max-h-24"
                leave-active-class="transition-all duration-300 ease-in"
                leave-from-class="opacity-100 translate-y-0 max-h-24"
                leave-to-class="opacity-0 -translate-y-2 max-h-0">
                <div v-if="showFlashSuccess && flashSuccessMessage" class="print:hidden bg-emerald-600 text-white px-4 py-2.5 text-xs sm:text-sm shadow-sm flex items-center justify-between overflow-hidden">
                    <div class="max-w-7xl mx-auto w-full flex items-center justify-between gap-2 font-medium">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            <span>{{ flashSuccessMessage }}</span>
                        </div>
                        <button type="button" @click="showFlashSuccess = false" class="text-white/80 hover:text-white p-1 rounded-lg transition" title="Tutup">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
            </transition>

            <transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 -translate-y-2 max-h-0"
                enter-to-class="opacity-100 translate-y-0 max-h-24"
                leave-active-class="transition-all duration-300 ease-in"
                leave-from-class="opacity-100 translate-y-0 max-h-24"
                leave-to-class="opacity-0 -translate-y-2 max-h-0">
                <div v-if="showFlashError && flashErrorMessage" class="print:hidden bg-rose-600 text-white px-4 py-2.5 text-xs sm:text-sm shadow-sm flex items-center justify-between overflow-hidden">
                    <div class="max-w-7xl mx-auto w-full flex items-center justify-between gap-2 font-medium">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            <span>{{ flashErrorMessage }}</span>
                        </div>
                        <button type="button" @click="showFlashError = false" class="text-white/80 hover:text-white p-1 rounded-lg transition" title="Tutup">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
            </transition>

            <!-- Page Header Slot -->
            <div v-if="$slots.header" class="print:hidden border-b border-slate-200/80 bg-white px-4 sm:px-6 lg:px-8 py-5">
                <slot name="header" />
            </div>

            <!-- Main Page Slot -->
            <main class="flex-1 py-6 px-4 sm:px-6 lg:px-8 pb-24 lg:pb-8 bg-slate-50 print:p-0 print:m-0 print:bg-white print:w-full">
                <slot />
            </main>
        </div>

        <!-- ============================================================ -->
        <!-- MOBILE BOTTOM NAVIGATION BAR -->
        <!-- ============================================================ -->
        <nav class="print:hidden lg:hidden fixed bottom-0 inset-x-0 z-30 bg-white/95 backdrop-blur-md border-t border-slate-200/90 shadow-lg px-2 py-1.5 flex items-center justify-around select-none">
            <!-- 1. Dashboard -->
            <Link :href="route('dashboard')"
                class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl text-[10px] font-semibold transition active:scale-95"
                :class="route().current('dashboard') ? 'text-slate-950 font-bold' : 'text-slate-500 hover:text-slate-800'">
                <div class="relative">
                    <svg class="w-5 h-5 transition" :class="route().current('dashboard') ? 'text-slate-950' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span v-if="route().current('dashboard')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-slate-900"></span>
                </div>
                <span class="mt-0.5">Beranda</span>
            </Link>

            <!-- 2. Aktivitas -->
            <Link :href="isKasi ? route('activity.my') : route('activity.index')"
                class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl text-[10px] font-semibold transition active:scale-95"
                :class="(route().current('activity.*') || isMyActivityActive || isActivityValidationActive) ? 'text-slate-950 font-bold' : 'text-slate-500 hover:text-slate-800'">
                <div class="relative">
                    <svg class="w-5 h-5 transition" :class="(route().current('activity.*') || isMyActivityActive || isActivityValidationActive) ? 'text-slate-950' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <span v-if="isKasiOrAdmin && pendingCount > 0" class="absolute -top-1 -right-1.5 min-w-[14px] h-[14px] px-0.5 rounded-full bg-amber-500 text-white font-mono text-[9px] font-bold flex items-center justify-center">
                        {{ pendingCount }}
                    </span>
                    <span v-if="route().current('activity.*') || isMyActivityActive || isActivityValidationActive" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-slate-900"></span>
                </div>
                <span class="mt-0.5">{{ isSuperadmin ? 'Rekap' : 'Aktivitas' }}</span>
            </Link>

            <!-- 3. Absensi -->
            <Link :href="route('attendance.index')"
                class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl text-[10px] font-semibold transition active:scale-95"
                :class="route().current('attendance.index') ? 'text-slate-950 font-bold' : 'text-slate-500 hover:text-slate-800'">
                <div class="relative">
                    <svg class="w-5 h-5 transition" :class="route().current('attendance.index') ? 'text-slate-950' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span v-if="route().current('attendance.index')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-slate-900"></span>
                </div>
                <span class="mt-0.5">Presensi</span>
            </Link>

            <!-- 4. Dinas Luar -->
            <Link :href="route('dinas-luar.index')"
                class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl text-[10px] font-semibold transition active:scale-95"
                :class="route().current('dinas-luar.*') ? 'text-slate-950 font-bold' : 'text-slate-500 hover:text-slate-800'">
                <div class="relative">
                    <svg class="w-5 h-5 transition" :class="route().current('dinas-luar.*') ? 'text-slate-950' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span v-if="route().current('dinas-luar.*')" class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-slate-900"></span>
                </div>
                <span class="mt-0.5">Dinas Luar</span>
            </Link>

            <!-- 5. Menu Lainnya (Drawer Toggle) -->
            <button @click="showingMobileSidebar = true"
                type="button"
                class="flex flex-col items-center justify-center py-1 px-2.5 rounded-xl text-[10px] font-semibold transition active:scale-95 text-slate-500 hover:text-slate-800">
                <div class="relative">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span v-if="pendingIssuesCount > 0 || unreadNotificationCount > 0" class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-rose-600 animate-pulse"></span>
                </div>
                <span class="mt-0.5">Menu</span>
            </button>
        </nav>

        <!-- ============================================================ -->
        <!-- MOBILE DRAWER BACKDROP & SLIDE-IN PANEL -->
        <!-- ============================================================ -->
        <transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="showingMobileSidebar"
                @click="showingMobileSidebar = false"
                class="print:hidden fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden">
            </div>
        </transition>

        <!-- Mobile Drawer with Slide-in Animation -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full">
            <div v-if="showingMobileSidebar"
                class="print:hidden fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] bg-white text-slate-700 shadow-2xl lg:hidden flex flex-col border-r border-slate-200">
                <!-- Mobile Brand Header -->
                <div class="h-16 px-4 flex items-center justify-between border-b border-slate-200 bg-white flex-shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-slate-900 flex items-center justify-center text-white font-bold text-sm shadow-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-sm text-slate-900 leading-tight">e-Kinerja PJLP</span>
                            <span class="text-[10px] text-slate-500 font-medium">Sudin Kominfotik Jakut</span>
                        </div>
                    </div>
                    <button @click="showingMobileSidebar = false" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg active:scale-95 transition" title="Tutup Menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- User Profile Card in Drawer -->
                <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-white font-black text-xs flex items-center justify-center tracking-tight shadow-xs flex-shrink-0">
                        {{ userInitials }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-bold text-slate-900 truncate">
                            {{ user.nama_lengkap || user.name }}
                        </div>
                        <div class="text-[11px] text-slate-500 truncate mt-0.5">
                            {{ userSubtitle }}
                        </div>
                    </div>
                    <Link :href="route('profile.edit')" @click="showingMobileSidebar = false"
                        class="p-1.5 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-200/60 transition" title="Edit Profil">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </Link>
                </div>

                <div class="flex-1 overflow-y-auto p-3.5 space-y-4 custom-scrollbar">
                    <!-- Group 1: Menu Utama -->
                    <div>
                        <div class="px-2.5 mb-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                            Menu Utama
                        </div>
                        <div class="space-y-1">
                            <Link :href="route('dashboard')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
                                :class="route().current('dashboard') ? 'bg-slate-100 text-slate-900 font-bold border border-slate-200/90 shadow-2xs' : 'text-slate-700 hover:bg-slate-100'">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                                <span>Dashboard</span>
                            </Link>

                            <!-- Validasi Aktivitas (Kasi, Kasudin, Superadmin) -->
                            <Link v-if="isKasiOrAdmin" :href="route('activity.index')" @click="showingMobileSidebar = false"
                                class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium transition"
                                :class="isActivityValidationActive ? 'bg-slate-100 text-slate-900 font-bold border border-slate-200/90 shadow-2xs' : 'text-slate-700 hover:bg-slate-100'">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                                    <span>{{ isSuperadmin ? 'Rekap Aktivitas' : 'Validasi Aktivitas' }}</span>
                                </div>
                                <span v-if="pendingCount > 0" class="px-1.5 py-0.5 text-[10px] font-mono font-bold rounded-full bg-amber-500 text-white">
                                    {{ pendingCount }}
                                </span>
                            </Link>

                            <!-- Aktivitas Harian (PJLP & Kasi) -->
                            <Link v-if="isPjlp || isKasi" :href="isKasi ? route('activity.my') : route('activity.index')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
                                :class="isMyActivityActive ? 'bg-slate-100 text-slate-900 font-bold border border-slate-200/90 shadow-2xs' : 'text-slate-700 hover:bg-slate-100'">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                <span>Aktivitas Harian</span>
                            </Link>

                            <Link :href="route('attendance.index')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
                                :class="route().current('attendance.index') ? 'bg-slate-100 text-slate-900 font-bold border border-slate-200/90 shadow-2xs' : 'text-slate-700 hover:bg-slate-100'">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Absensi Pegawai</span>
                            </Link>

                            <Link :href="route('attendance.recap')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
                                :class="route().current('attendance.recap') ? 'bg-slate-100 text-slate-900 font-bold border border-slate-200/90 shadow-2xs' : 'text-slate-700 hover:bg-slate-100'">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                <span>Rekap Absensi</span>
                            </Link>

                            <Link :href="route('dinas-luar.index')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
                                :class="route().current('dinas-luar.*') ? 'bg-slate-100 text-slate-900 font-bold border border-slate-200/90 shadow-2xs' : 'text-slate-700 hover:bg-slate-100'">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <span>Dinas Luar (DL)</span>
                            </Link>
                        </div>
                    </div>

                    <Link v-if="isKasiOrAdmin" :href="route('activity-download.index')" @click="showingMobileSidebar = false"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium text-slate-700 hover:bg-slate-100">
                        <span>Tarik Data Aktivitas PJLP</span>
                    </Link>
                    <Link v-if="isPjlp || isKasiOrAdmin" :href="route('reminder.index')" @click="showingMobileSidebar = false"
                        class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition"
                        :class="route().current('reminder.index') ? 'bg-slate-100 text-slate-900 font-bold border border-slate-200' : 'text-slate-700 hover:bg-slate-100'">
                        <span>Reminder Aktivitas</span>
                    </Link>

                    <!-- Group 2: Administrasi & Master (Khusus Kasi / Superadmin) -->
                    <div v-if="isKasiOrAdmin">
                        <div class="px-2.5 mb-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                            Administrasi & Master
                        </div>
                        <div class="space-y-1">
                            <Link :href="route('categories.index')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium hover:bg-slate-100 text-slate-700">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                <span>Kategori Aktivitas</span>
                            </Link>
                            <Link :href="route('pjlp.index')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium hover:bg-slate-100 text-slate-700">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                <span>Manajemen Pegawai</span>
                            </Link>
                            <Link v-if="isSuperadmin" :href="route('seksi.index')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium hover:bg-slate-100 text-slate-700">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                <span>Master Seksi</span>
                            </Link>
                            <Link v-if="isSuperadmin" :href="route('users.index')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium hover:bg-slate-100 text-slate-700">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Kelola Akun Pengguna</span>
                            </Link>
                            <Link v-if="isSuperadmin" :href="route('temporary-users.index')" @click="showingMobileSidebar = false"
                                class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium hover:bg-slate-100 text-slate-700">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                                    <span>Pendaftaran PJLP</span>
                                </div>
                                <span v-if="page.props.pendingSubmissionsCount > 0" class="px-1.5 py-0.5 text-[10px] font-mono font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200">
                                    {{ page.props.pendingSubmissionsCount }}
                                </span>
                            </Link>
                            <Link :href="route('activity-logs.index')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium hover:bg-slate-100 text-slate-700">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                                <span>Log Audit Sistem</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Group 3: Komunikasi & Dukungan -->
                    <div>
                        <div class="px-2.5 mb-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                            Komunikasi & Bantuan
                        </div>
                        <div class="space-y-1">
                            <Link :href="route('notifications.index')" @click="showingMobileSidebar = false"
                                class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium hover:bg-slate-100 text-slate-700">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                                    <span>Notifikasi Sistem</span>
                                </div>
                                <span v-if="unreadNotificationCount > 0" class="px-1.5 py-0.5 text-[10px] font-mono font-bold rounded-full bg-rose-600 text-white">
                                    {{ unreadNotificationCount }}
                                </span>
                            </Link>

                            <Link :href="route('issue-reports.index')" @click="showingMobileSidebar = false"
                                class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium hover:bg-slate-100 text-slate-700">
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                    <span>Laporan Kendala</span>
                                </div>
                                <span v-if="pendingIssuesCount > 0" class="px-1.5 py-0.5 text-[10px] font-mono font-bold rounded-full bg-amber-500 text-white">
                                    {{ pendingIssuesCount }}
                                </span>
                            </Link>
                        </div>
                    </div>

                    <!-- Group 4: Profil & Keluar -->
                    <div class="pt-3 border-t border-slate-200">
                        <div class="px-2.5 mb-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                            Sesi Akun
                        </div>
                        <div class="space-y-1">
                            <Link :href="route('profile.edit')" @click="showingMobileSidebar = false"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium hover:bg-slate-100 text-slate-700">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                <span>Profil Pengguna</span>
                            </Link>
                            <Link :href="route('logout')" method="post" as="button" @click="showingMobileSidebar = false"
                                class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold hover:bg-rose-50 text-rose-600 text-left transition">
                                <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                <span>Keluar Sistem</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ============================================================ -->
        <!-- REALTIME TOAST NOTIFICATION POPUP -->
        <!-- ============================================================ -->
        <transition enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="toastNotification"
                class="print:hidden fixed bottom-5 right-5 z-50 max-w-sm w-full bg-white rounded-xl shadow-2xl border border-slate-200 p-4 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center flex-shrink-0 mt-0.5 shadow-xs">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0 cursor-pointer" @click="handleNotificationClick(toastNotification); toastNotification = null;">
                    <div class="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                        {{ toastNotification.title }}
                    </div>
                    <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">
                        {{ toastNotification.message }}
                    </p>
                    <div v-if="toastNotification.link || toastNotification.url" class="mt-1.5 text-[11px] font-semibold text-blue-600 hover:underline">
                        Buka tautan →
                    </div>
                </div>
                <button @click="toastNotification = null" class="text-slate-400 hover:text-slate-600 p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </transition>
    </div>
</template>
