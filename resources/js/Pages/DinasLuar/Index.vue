<script setup>
import { ref, computed, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    MapPin,
    Camera,
    Calendar,
    Building2,
    CheckCircle2,
    Clock,
    AlertCircle,
    Plus,
    X,
    ExternalLink,
    UploadCloud,
    RotateCcw,
    SwitchCamera,
    ShieldCheck,
    RefreshCw,
    Lock,
    Compass,
    Navigation,
    Smartphone,
    Monitor,
    Maximize2,
} from 'lucide-vue-next';

const page = usePage();
const authUser = computed(() => page.props.auth?.user || {});

const props = defineProps({
    isPjlp: Boolean,
    canVerify: Boolean,
    isSuperadmin: Boolean,
    list: Object,
    filters: Object,
    seksiList: Array,
});

const canVerify = computed(() => {
    if (typeof props.canVerify === 'boolean') {
        return props.canVerify;
    }
    return !props.isPjlp && authUser.value?.role !== 'Superadmin';
});

const isSuperadmin = computed(() => {
    if (typeof props.isSuperadmin === 'boolean') {
        return props.isSuperadmin;
    }
    return authUser.value?.role === 'Superadmin';
});

const filterMonth = ref(props.filters.bulan || new Date().toISOString().slice(0, 7));
const filterStatus = ref(props.filters.status || '');
const filterSeksi = ref(props.filters.seksi || '');

// Form Pengajuan DL
const showModal = ref(false);
const dlForm = useForm({
    tipe: 'DL PENUH',
    keterangan: '',
    latitude: null,
    longitude: null,
    alamat: '',
    foto: null,
});

// Webcam & Realtime GPS State
const useWebcam = ref(false);
const videoRef = ref(null);
const canvasRef = ref(null);
const capturedImage = ref(null);
const facingMode = ref('user'); // Default kamera depan untuk swafoto kehadiran
const isFlipped = ref(true); // Anti-mirror untuk kamera depan
let mediaStream = null;
const photoError = ref('');
const cameraOrientation = ref('auto'); // 'auto' | 'landscape' | 'portrait'
const videoActualAspect = ref(16 / 9);
const isStreamPortrait = computed(() => videoActualAspect.value < 1.0);

const activeAspectClass = computed(() => {
    if (cameraOrientation.value === 'portrait') {
        return 'aspect-[3/4] max-h-[60vh] sm:max-h-[460px] mx-auto';
    }
    if (cameraOrientation.value === 'landscape') {
        return 'aspect-video max-h-[50vh] sm:max-h-[360px] w-full';
    }
    // auto mode
    if (isStreamPortrait.value) {
        return 'aspect-[3/4] max-h-[60vh] sm:max-h-[460px] mx-auto';
    }
    return 'aspect-video max-h-[50vh] sm:max-h-[360px] w-full';
});

// Realtime GPS & Clock State (100% Free Resources via HTML5 Geolocation API & OSM Nominatim)
const geoCoords = ref({
    latitude: null,
    longitude: null,
    accuracy: null,
    altitude: null,
});
const geoAddress = ref('');
const geoLoading = ref(false);
const geoError = ref(null);
const currentTime = ref('');
let watchId = null;
let clockTimer = null;
let lastGeocodePos = null;

// Photo Preview Modal
const previewPhotoUrl = ref(null);

const formatPhotoUrl = (path) => {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) {
        return path;
    }
    const cleanPath = path.replace(/^\/?storage\//, '').replace(/^\//, '');
    return '/storage/' + cleanPath;
};

const openPreview = (photoPath) => {
    if (!photoPath) return;
    previewPhotoUrl.value = formatPhotoUrl(photoPath);
};

// Bulk Verification State
const selectedIds = ref([]);
const isSubmittingBulk = ref(false);

const applyFilter = () => {
    router.get(route('dinas-luar.index'), {
        bulan: filterMonth.value,
        status: filterStatus.value || undefined,
        seksi: filterSeksi.value || undefined,
    }, { preserveState: true, replace: true });
};

// ============================================================
// REALTIME LOCATION & CLOCK SERVICES (100% FREE & UNLIMITED)
// ============================================================
const updateCurrentTime = () => {
    const now = new Date();
    const options = {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    };
    currentTime.value = `${now.toLocaleDateString('id-ID', options)} • ${now.toLocaleTimeString('id-ID', { hour12: false })} WIB`;
};

const startRealtimeLocation = () => {
    if (!navigator.geolocation) {
        geoError.value = 'Perangkat / peramban Anda tidak mendukung Geolocation.';
        return;
    }

    geoLoading.value = true;
    geoError.value = null;

    updateCurrentTime();
    if (!clockTimer) {
        clockTimer = setInterval(updateCurrentTime, 1000);
    }

    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
    }

    watchId = navigator.geolocation.watchPosition(
        (pos) => {
            geoLoading.value = false;
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            const acc = Math.round(pos.coords.accuracy || 0);
            const alt = pos.coords.altitude ? Math.round(pos.coords.altitude) : null;

            geoCoords.value = {
                latitude: lat,
                longitude: lng,
                accuracy: acc,
                altitude: alt,
            };

            dlForm.latitude = Number(lat.toFixed(6));
            dlForm.longitude = Number(lng.toFixed(6));

            if (!geoAddress.value || shouldGeocode(lat, lng)) {
                fetchReverseGeocode(lat, lng, acc);
            }
        },
        (err) => {
            geoLoading.value = false;
            let msg = 'Sinyal GPS lemah / belum terdeteksi.';
            if (err.code === err.PERMISSION_DENIED) {
                msg = 'Izin akses GPS ditolak peramban. Silakan aktifkan izin lokasi.';
            } else if (err.code === err.POSITION_UNAVAILABLE) {
                msg = 'Sensor lokasi perangkat tidak aktif.';
            }
            geoError.value = msg;
        },
        {
            enableHighAccuracy: true,
            maximumAge: 3000,
            timeout: 10000,
        }
    );
};

const stopRealtimeLocation = () => {
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    if (clockTimer) {
        clearInterval(clockTimer);
        clockTimer = null;
    }
};

const shouldGeocode = (lat, lng) => {
    if (!lastGeocodePos) return true;
    const dLat = Math.abs(lat - lastGeocodePos.lat);
    const dLng = Math.abs(lng - lastGeocodePos.lng);
    return (dLat > 0.0004 || dLng > 0.0004);
};

const fetchReverseGeocode = async (lat, lng, acc) => {
    lastGeocodePos = { lat, lng };
    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=id`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            const addr = data.address || {};
            const street = addr.road || addr.pedestrian || addr.suburb || addr.neighbourhood || '';
            const subdistrict = addr.village || addr.suburb || addr.city_district || '';
            const city = addr.city || addr.town || addr.county || 'Kota Jakarta Utara';

            const segments = [data.name || street, subdistrict, city].filter(Boolean);
            const finalAddress = segments.length > 0 ? segments.join(', ') : (data.display_name || '');
            if (finalAddress) {
                geoAddress.value = finalAddress;
                dlForm.alamat = finalAddress;
                return;
            }
        }
    } catch (e) {
        console.warn('OSM Nominatim fallback to coordinate format', e);
    }

    const coordText = `Lat: ${lat.toFixed(6)}, Long: ${lng.toFixed(6)} (Akurasi: ±${acc}m)`;
    geoAddress.value = coordText;
    dlForm.alamat = coordText;
};

// ============================================================
// CAMERA STREAM & GEOTAG WATERMARKING (CLIENT-SIDE FREE)
// ============================================================
const startCamera = async () => {
    useWebcam.value = true;
    photoError.value = '';
    startRealtimeLocation();
    try {
        if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
        }

        // Tentukan ideal resolusi berdasarkan orientasi layar perangkat saat ini
        const isDevicePortrait = typeof window !== 'undefined' && window.innerHeight > window.innerWidth;
        const widthConstraint = isDevicePortrait ? { ideal: 720 } : { ideal: 1280 };
        const heightConstraint = isDevicePortrait ? { ideal: 1280 } : { ideal: 720 };

        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: facingMode.value,
                width: widthConstraint,
                height: heightConstraint,
            },
            audio: false,
        });
        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
        }
    } catch (err) {
        try {
            // Fallback constraint standar
            mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            if (videoRef.value) {
                videoRef.value.srcObject = mediaStream;
            }
        } catch (fallbackErr) {
            alert('Gagal mengakses kamera: ' + fallbackErr.message);
            useWebcam.value = false;
        }
    }
};

const onVideoMetadata = () => {
    if (videoRef.value && videoRef.value.videoWidth && videoRef.value.videoHeight) {
        videoActualAspect.value = videoRef.value.videoWidth / videoRef.value.videoHeight;
    }
};

const toggleOrientationMode = () => {
    if (cameraOrientation.value === 'auto') {
        cameraOrientation.value = 'landscape';
    } else if (cameraOrientation.value === 'landscape') {
        cameraOrientation.value = 'portrait';
    } else {
        cameraOrientation.value = 'auto';
    }
};

const flipCamera = async () => {
    facingMode.value = facingMode.value === 'user' ? 'environment' : 'user';
    isFlipped.value = facingMode.value === 'user';
    stopCamera(false);
    await startCamera();
};

const stopCamera = (resetUi = true) => {
    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
    }
    if (resetUi) {
        useWebcam.value = false;
    }
};

const retakePhoto = () => {
    capturedImage.value = null;
    dlForm.foto = null;
    photoError.value = '';
    startCamera();
};

const capturePhoto = () => {
    if (!videoRef.value || !canvasRef.value) return;
    const canvas = canvasRef.value;
    const video = videoRef.value;

    const width = video.videoWidth || 1280;
    const height = video.videoHeight || 720;
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    // 1. Gambar frame video (dengan koreksi anti-mirror hanya jika kamera depan)
    if (isFlipped.value && facingMode.value === 'user') {
        ctx.save();
        ctx.translate(width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, width, height);
        ctx.restore();
    } else {
        ctx.drawImage(video, 0, 0, width, height);
    }

    // 2. Metadata Presensi Geotagging
    const user = authUser.value;
    const namaPegawai = user.nama_lengkap || user.name || 'Pegawai PJLP';
    const identitasPegawai = user.nrk || user.nik || user.pin || user.username || '-';
    const seksiPegawai = user.seksi || 'Sudin Kominfotik Jakarta Utara';
    const lat = geoCoords.value.latitude !== null ? geoCoords.value.latitude.toFixed(6) : (dlForm.latitude ? Number(dlForm.latitude).toFixed(6) : 'N/A');
    const lng = geoCoords.value.longitude !== null ? geoCoords.value.longitude.toFixed(6) : (dlForm.longitude ? Number(dlForm.longitude).toFixed(6) : 'N/A');
    const acc = geoCoords.value.accuracy !== null ? `±${geoCoords.value.accuracy}m` : 'Normal';
    const timeStr = currentTime.value || new Date().toLocaleString('id-ID');
    const alamatStr = geoAddress.value || dlForm.alamat || 'Koordinat Geotagging Lapangan';

    // 3. Deteksi Responsivitas Orientasi (Landscape vs Portrait / Horizontal vs Vertikal)
    const isPortrait = height > width;
    const baseDimension = Math.min(width, height);

    // Tipografi Adaptif sesuai dimensi terkecil
    const fontPrimary = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
    const fontSizeHeader = Math.max(12, Math.round(baseDimension * 0.025));
    const fontSizeBody = Math.max(10, Math.round(baseDimension * 0.019));
    const paddingX = Math.max(14, Math.round(width * 0.03));
    const lineSpacing = Math.round(fontSizeBody * 0.38);

    // Hitung tinggi banner watermark adaptif
    const numLines = isPortrait ? 7 : 4;
    const totalContentHeight = fontSizeHeader + (numLines - 1) * (fontSizeBody + lineSpacing);
    const bannerHeight = Math.max(isPortrait ? 155 : 98, totalContentHeight + Math.round(baseDimension * 0.07));
    const bannerY = height - bannerHeight;

    // Gradien vignette natural gelap di bagian bawah
    const gradient = ctx.createLinearGradient(0, bannerY - 20, 0, height);
    gradient.addColorStop(0, 'rgba(0, 0, 0, 0)');
    gradient.addColorStop(0.25, 'rgba(0, 0, 0, 0.65)');
    gradient.addColorStop(1, 'rgba(0, 0, 0, 0.88)');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, bannerY - 20, width, bannerHeight + 20);

    ctx.save();
    ctx.shadowColor = 'rgba(0, 0, 0, 0.95)';
    ctx.shadowBlur = 4;
    ctx.shadowOffsetX = 1;
    ctx.shadowOffsetY = 1;

    let textY = bannerY + fontSizeHeader + 8;
    const maxTextWidth = width - paddingX * 2;

    if (isPortrait) {
        // TAMPILAN RESPONSIVE MODE VERTIKAL (PORTRAIT)
        // Baris 1: Instansi Resmi
        ctx.font = `bold ${fontSizeHeader}px ${fontPrimary}`;
        ctx.fillStyle = '#ffffff';
        ctx.fillText('PEMERINTAH PROVINSI DKI JAKARTA', paddingX, textY, maxTextWidth);

        textY += fontSizeBody + lineSpacing;
        ctx.font = `bold ${fontSizeBody}px ${fontPrimary}`;
        ctx.fillStyle = '#f8fafc';
        ctx.fillText('SUDIN KOMINFOTIK KOTA ADM. JAKARTA UTARA', paddingX, textY, maxTextWidth);

        // Baris 2: Nama & Identitas Pegawai
        textY += fontSizeBody + lineSpacing + 2;
        ctx.font = `600 ${fontSizeBody}px ${fontPrimary}`;
        ctx.fillStyle = '#ffffff';
        ctx.fillText(`Pegawai: ${namaPegawai} (${identitasPegawai})`, paddingX, textY, maxTextWidth);

        textY += fontSizeBody + lineSpacing;
        ctx.font = `normal ${fontSizeBody}px ${fontPrimary}`;
        ctx.fillStyle = '#e2e8f0';
        ctx.fillText(`Unit: ${seksiPegawai}`, paddingX, textY, maxTextWidth);

        // Baris 3: Koordinat Presisi & Waktu
        textY += fontSizeBody + lineSpacing + 2;
        ctx.font = `normal ${fontSizeBody}px ${fontPrimary}`;
        ctx.fillStyle = '#f1f5f9';
        ctx.fillText(`Koordinat: ${lat}, ${lng} (Akurasi: ${acc})`, paddingX, textY, maxTextWidth);

        textY += fontSizeBody + lineSpacing;
        ctx.font = `normal ${fontSizeBody}px ${fontPrimary}`;
        ctx.fillStyle = '#e2e8f0';
        ctx.fillText(`Waktu: ${timeStr}`, paddingX, textY, maxTextWidth);

        // Baris 4: Alamat Lokasi Nyata
        textY += fontSizeBody + lineSpacing + 2;
        ctx.font = `normal ${fontSizeBody}px ${fontPrimary}`;
        ctx.fillStyle = '#cbd5e1';
        ctx.fillText(`Lokasi: ${alamatStr}`, paddingX, textY, maxTextWidth);
    } else {
        // TAMPILAN RESPONSIVE MODE HORIZONTAL (LANDSCAPE)
        // Baris 1: Instansi Resmi
        ctx.font = `bold ${fontSizeHeader}px ${fontPrimary}`;
        ctx.fillStyle = '#ffffff';
        ctx.fillText('PEMERINTAH PROVINSI DKI JAKARTA — SUDIN KOMINFOTIK KOTA ADM. JAKARTA UTARA', paddingX, textY, maxTextWidth);

        // Baris 2: Nama & Identitas Pegawai
        textY += fontSizeBody + lineSpacing + 2;
        ctx.font = `600 ${fontSizeBody}px ${fontPrimary}`;
        ctx.fillStyle = '#ffffff';
        ctx.fillText(`Pegawai: ${namaPegawai} (${identitasPegawai}) • Seksi: ${seksiPegawai}`, paddingX, textY, maxTextWidth);

        // Baris 3: Koordinat Presisi & Waktu
        textY += fontSizeBody + lineSpacing + 1;
        ctx.font = `normal ${fontSizeBody}px ${fontPrimary}`;
        ctx.fillStyle = '#f8fafc';
        ctx.fillText(`Koordinat: ${lat}, ${lng} (Akurasi: ${acc}) • Waktu: ${timeStr}`, paddingX, textY, maxTextWidth);

        // Baris 4: Alamat Lokasi Nyata
        textY += fontSizeBody + lineSpacing + 1;
        ctx.font = `normal ${fontSizeBody}px ${fontPrimary}`;
        ctx.fillStyle = '#e2e8f0';
        ctx.fillText(`Lokasi: ${alamatStr}`, paddingX, textY, maxTextWidth);
    }

    ctx.restore();

    // 4. Export ke DataURL dan simpan ke form
    const dataUrl = canvas.toDataURL('image/jpeg', 0.90);
    capturedImage.value = dataUrl;
    dlForm.foto = dataUrl;
    photoError.value = '';
    stopCamera();
};

const handleFileInput = (e) => {
    photoError.value = '';
    const file = e.target.files[0];
    if (file) {
        dlForm.foto = file;
        const reader = new FileReader();
        reader.onload = (re) => {
            capturedImage.value = re.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const openDlModal = () => {
    showModal.value = true;
    photoError.value = '';
    startRealtimeLocation();
};

const closeDlModal = () => {
    showModal.value = false;
    photoError.value = '';
    stopCamera();
    stopRealtimeLocation();
};

const submitDl = () => {
    photoError.value = '';

    // Validasi Wajib Foto (Kamera / Unggah)
    if (!dlForm.foto) {
        photoError.value = 'Wajib mengambil atau mengunggah swafoto kehadiran dinas luar sebelum mengirim.';
        return;
    }

    if (!dlForm.keterangan || dlForm.keterangan.trim().length === 0) {
        return;
    }

    dlForm.post(route('dinas-luar.store'), {
        forceFormData: true,
        onSuccess: () => {
            closeDlModal();
            capturedImage.value = null;
            photoError.value = '';
            dlForm.reset();
        }
    });
};

const verifySingle = (id, status) => {
    if (!canVerify.value) return;
    router.patch(route('dinas-luar.verify', id), { status });
};

const verifyBulk = (status) => {
    if (!canVerify.value || selectedIds.value.length === 0) return;
    isSubmittingBulk.value = true;
    router.post(route('dinas-luar.verify-bulk'), {
        ids: selectedIds.value,
        status,
    }, {
        onFinish: () => {
            isSubmittingBulk.value = false;
            selectedIds.value = [];
        }
    });
};

onUnmounted(() => {
    stopCamera();
    stopRealtimeLocation();
});
</script>

<template>
    <Head title="Dinas Luar & Geotagging Lapangan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                            Penugasan Lapangan
                        </span>
                        <span class="text-xs text-slate-400">• Geotagging GPS & Swafoto</span>
                        <span v-if="isSuperadmin" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                            Akses Monitoring
                        </span>
                    </div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                        {{ isPjlp ? 'Pengajuan Dinas Luar' : 'Verifikasi Penugasan Dinas Luar' }}
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        <template v-if="isPjlp">
                            Pelaporan presensi penugasan lapangan dengan swafoto kehadiran dan geotag lokasi satelit
                        </template>
                        <template v-else-if="isSuperadmin">
                            Pemantauan titik koordinat GPS dan permohonan dinas luar seluruh PJLP (Superadmin hanya dapat melihat data)
                        </template>
                        <template v-else>
                            Pemeriksaan titik koordinat GPS dan validasi surat tugas penugasan dinas luar PJLP
                        </template>
                    </p>
                </div>

                <div v-if="isPjlp">
                    <button
                        type="button"
                        @click="openDlModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-xl bg-blue-600 text-white shadow-xs hover:bg-blue-500 transition active:scale-95"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Ajukan Dinas Luar</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Filter Bar -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <Calendar class="w-4 h-4 text-slate-400" />
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300">Periode:</label>
                        <input
                            type="month"
                            v-model="filterMonth"
                            @change="applyFilter"
                            class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300">Status:</label>
                        <select
                            v-model="filterStatus"
                            @change="applyFilter"
                            class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3"
                        >
                            <option value="">Semua Status</option>
                            <option value="Menunggu">Menunggu</option>
                            <option value="Disetujui">Disetujui</option>
                            <option value="Ditolak">Ditolak</option>
                        </select>
                    </div>

                    <div v-if="!isPjlp" class="flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-slate-400" />
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300">Seksi:</label>
                        <select
                            v-model="filterSeksi"
                            @change="applyFilter"
                            class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-1.5 px-3"
                        >
                            <option value="">Semua Seksi</option>
                            <option v-for="s in seksiList" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                </div>

                <!-- Admin Bulk Buttons (Khusus Supervisor / Kasi / Kasudin) -->
                <div v-if="canVerify && selectedIds.length > 0" class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                        {{ selectedIds.length }} terpilih:
                    </span>
                    <button
                        type="button"
                        @click="verifyBulk('Disetujui')"
                        :disabled="isSubmittingBulk"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold shadow-xs transition"
                    >
                        Setujui Semua
                    </button>
                    <button
                        type="button"
                        @click="verifyBulk('Ditolak')"
                        :disabled="isSubmittingBulk"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 text-white rounded-lg text-xs font-bold shadow-xs transition"
                    >
                        Tolak Semua
                    </button>
                </div>
            </div>

            <!-- Tabel & Kartu Riwayat Dinas Luar (Responsive Semua Perangkat) -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <!-- Tampilan Tabel (Desktop & Tablet Lebar: Layar md ke atas) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold uppercase tracking-wider">
                                <th v-if="canVerify" class="py-3.5 px-4 w-10">
                                    <input
                                        type="checkbox"
                                        @change="selectedIds = $event.target.checked ? list.data.map(d => d.id) : []"
                                        class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    />
                                </th>
                                <th class="py-3.5 px-4">Waktu & Tipe Penugasan</th>
                                <th v-if="!isPjlp" class="py-3.5 px-4">Pegawai PJLP</th>
                                <th class="py-3.5 px-4">Titik Geotag / Alamat</th>
                                <th class="py-3.5 px-4">Uraian Penugasan</th>
                                <th class="py-3.5 px-4">Swafoto Lapangan</th>
                                <th class="py-3.5 px-4">Status Verifikasi</th>
                                <th v-if="canVerify" class="py-3.5 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-if="!list.data || list.data.length === 0">
                                <td :colspan="canVerify ? 8 : (!isPjlp ? 6 : 5)" class="py-12 text-center text-slate-400">
                                    <MapPin class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                                    <span>Tidak ada permohonan dinas luar pada periode bulan ini.</span>
                                </td>
                            </tr>
                            <tr
                                v-for="dl in list.data"
                                :key="dl.id"
                                class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition"
                            >
                                <td v-if="canVerify" class="py-3.5 px-4">
                                    <input
                                        type="checkbox"
                                        :value="dl.id"
                                        v-model="selectedIds"
                                        class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    />
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                        {{ dl.tipe }}
                                    </span>
                                    <div class="text-[11px] text-slate-500 mt-1">
                                        {{ dl.waktu ? new Date(dl.waktu).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '-' }} WIB
                                    </div>
                                </td>
                                <td v-if="!isPjlp" class="py-3.5 px-4 whitespace-nowrap font-semibold text-slate-900 dark:text-slate-100">
                                    {{ dl.user?.nama_lengkap }}
                                    <div class="text-[10px] font-normal text-slate-500">
                                        {{ dl.user?.seksi || 'Sudin Kominfotik' }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 max-w-xs">
                                    <div class="text-slate-700 dark:text-slate-300 line-clamp-2">
                                        {{ dl.alamat || 'Koordinat tercatat' }}
                                    </div>
                                    <a
                                        v-if="dl.latitude && dl.longitude"
                                        :href="'https://www.google.com/maps?q=' + dl.latitude + ',' + dl.longitude"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-500 mt-1"
                                    >
                                        <MapPin class="w-3 h-3" />
                                        <span>Buka Peta Satelit</span>
                                        <ExternalLink class="w-3 h-3" />
                                    </a>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300 max-w-xs">
                                    <p class="line-clamp-2">{{ dl.keterangan || '-' }}</p>
                                </td>
                                <td class="py-3.5 px-4">
                                    <button
                                        v-if="dl.foto"
                                        type="button"
                                        @click="openPreview(dl.foto)"
                                        class="w-12 h-12 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 shadow-xs hover:ring-2 hover:ring-blue-500 hover:opacity-90 transition block cursor-pointer group relative"
                                        title="Klik untuk melihat foto ukuran penuh"
                                    >
                                        <img :src="formatPhotoUrl(dl.foto)" alt="Selfie Kehadiran" class="w-full h-full object-cover group-hover:scale-110 transition duration-200" />
                                        <div class="absolute inset-0 bg-black/25 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-200">
                                            <Maximize2 class="w-3.5 h-3.5 text-white" />
                                        </div>
                                    </button>
                                    <span v-else class="text-[11px] text-slate-400 italic">Tanpa foto</span>
                                </td>
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold"
                                        :class="{
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300': dl.status_verifikasi === 'Disetujui',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300': dl.status_verifikasi === 'Menunggu',
                                            'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-300': dl.status_verifikasi === 'Ditolak',
                                        }"
                                    >
                                        <CheckCircle2 v-if="dl.status_verifikasi === 'Disetujui'" class="w-3.5 h-3.5" />
                                        <Clock v-else-if="dl.status_verifikasi === 'Menunggu'" class="w-3.5 h-3.5" />
                                        <AlertCircle v-else class="w-3.5 h-3.5" />
                                        <span>{{ dl.status_verifikasi }}</span>
                                    </span>
                                    <div v-if="dl.verifier" class="text-[10px] text-slate-400 mt-1">
                                        Oleh: {{ dl.verifier.nama_lengkap }}
                                    </div>
                                </td>
                                <td v-if="canVerify" class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div v-if="dl.status_verifikasi === 'Menunggu'" class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="verifySingle(dl.id, 'Disetujui')"
                                            class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-xs transition"
                                        >
                                            Setujui
                                        </button>
                                        <button
                                            type="button"
                                            @click="verifySingle(dl.id, 'Ditolak')"
                                            class="px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-xs transition"
                                        >
                                            Tolak
                                        </button>
                                    </div>
                                    <span v-else class="text-xs text-slate-400">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Tampilan Kartu Mobile (Layar Handphone: max md) -->
                <div class="block md:hidden divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-if="!list.data || list.data.length === 0" class="py-10 px-4 text-center text-slate-400">
                        <MapPin class="w-8 h-8 mx-auto text-slate-300 mb-2" />
                        <span class="text-xs">Tidak ada permohonan dinas luar pada periode bulan ini.</span>
                    </div>

                    <div
                        v-for="dl in list.data"
                        :key="'mobile-' + dl.id"
                        class="p-4 space-y-3 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition"
                    >
                        <!-- Top Row: Tipe DL & Status Verifikasi -->
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300">
                                    {{ dl.tipe }}
                                </span>
                                <span class="text-[11px] text-slate-500 flex items-center gap-1 font-mono">
                                    <Clock class="w-3 h-3 text-slate-400" />
                                    {{ dl.waktu ? new Date(dl.waktu).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) : '-' }} WIB
                                </span>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold shrink-0"
                                :class="{
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300': dl.status_verifikasi === 'Disetujui',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300': dl.status_verifikasi === 'Menunggu',
                                    'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-300': dl.status_verifikasi === 'Ditolak',
                                }"
                            >
                                <CheckCircle2 v-if="dl.status_verifikasi === 'Disetujui'" class="w-3 h-3" />
                                <Clock v-else-if="dl.status_verifikasi === 'Menunggu'" class="w-3 h-3" />
                                <AlertCircle v-else class="w-3 h-3" />
                                <span>{{ dl.status_verifikasi }}</span>
                            </span>
                        </div>

                        <!-- Info Pegawai (Jika Kasi / Admin / Kasudin) -->
                        <div v-if="!isPjlp" class="flex items-center justify-between text-xs font-semibold text-slate-900 dark:text-slate-100 pt-0.5">
                            <div class="flex items-center gap-2">
                                <input
                                    v-if="canVerify"
                                    type="checkbox"
                                    :value="dl.id"
                                    v-model="selectedIds"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span>{{ dl.user?.nama_lengkap }}</span>
                            </div>
                            <span class="text-[10px] font-normal text-slate-500">{{ dl.user?.seksi || 'Sudin Kominfotik' }}</span>
                        </div>

                        <!-- Content Row: Swafoto Thumbnail + Geotag & Deskripsi -->
                        <div class="flex items-start gap-3">
                            <!-- Thumbnail Swafoto -->
                            <div class="shrink-0">
                                <button
                                    v-if="dl.foto"
                                    type="button"
                                    @click="openPreview(dl.foto)"
                                    class="w-16 h-16 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 shadow-xs relative group block cursor-pointer hover:ring-2 hover:ring-blue-500 transition"
                                    title="Klik untuk melihat foto ukuran penuh"
                                >
                                    <img :src="formatPhotoUrl(dl.foto)" alt="Selfie Lapangan" class="w-full h-full object-cover group-hover:scale-105 transition" />
                                    <div class="absolute inset-0 bg-black/20 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                        <Maximize2 class="w-4 h-4 text-white" />
                                    </div>
                                </button>
                                <div v-else class="w-16 h-16 rounded-xl bg-slate-100 dark:bg-slate-800 border border-dashed border-slate-300 dark:border-slate-700 flex flex-col items-center justify-center text-slate-400 text-[10px]">
                                    <Camera class="w-4 h-4 mb-0.5 text-slate-300" />
                                    <span>Tanpa foto</span>
                                </div>
                            </div>

                            <!-- Detail Alamat & Keterangan -->
                            <div class="flex-1 min-w-0 space-y-1 text-xs">
                                <div class="text-slate-700 dark:text-slate-300 line-clamp-2 text-[11px] leading-relaxed">
                                    <span class="font-semibold text-slate-900 dark:text-slate-100">Lokasi: </span>
                                    {{ dl.alamat || 'Koordinat tercatat' }}
                                </div>
                                <div v-if="dl.keterangan" class="text-slate-600 dark:text-slate-400 line-clamp-2 text-[11px]">
                                    <span class="font-semibold text-slate-900 dark:text-slate-100">Uraian: </span>
                                    {{ dl.keterangan }}
                                </div>
                                <a
                                    v-if="dl.latitude && dl.longitude"
                                    :href="'https://www.google.com/maps?q=' + dl.latitude + ',' + dl.longitude"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 hover:text-blue-500 pt-0.5"
                                >
                                    <MapPin class="w-3 h-3" />
                                    <span>Buka Google Maps</span>
                                    <ExternalLink class="w-3 h-3" />
                                </a>
                            </div>
                        </div>

                        <!-- Verifier Info / Admin Direct Actions -->
                        <div v-if="dl.verifier" class="text-[10px] text-slate-400 pt-1">
                            Diverifikasi oleh: {{ dl.verifier.nama_lengkap }}
                        </div>
                        <div v-if="canVerify && dl.status_verifikasi === 'Menunggu'" class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-slate-800">
                            <button
                                type="button"
                                @click="verifySingle(dl.id, 'Disetujui')"
                                class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-xs transition cursor-pointer"
                            >
                                Setujui
                            </button>
                            <button
                                type="button"
                                @click="verifySingle(dl.id, 'Ditolak')"
                                class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-xs transition cursor-pointer"
                            >
                                Tolak
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL AJUKAN DINAS LUAR (PJLP)                               -->
        <!-- ============================================================ -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-xl w-full p-4 sm:p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 max-h-[92vh] overflow-y-auto custom-scrollbar">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-400">
                            <MapPin class="w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                                Permohonan Dinas Luar (DL)
                            </h3>
                            <p class="text-[11px] text-slate-500">
                                Geotagging & Presensi Tugas Lapangan PJLP
                            </p>
                        </div>
                    </div>
                    <button @click="closeDlModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitDl" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Tipe Dinas Luar <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="dlForm.tipe"
                            required
                            class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 py-2 px-3"
                        >
                            <option value="DL AWAL">DL AWAL (Pagi dinas luar, siang/sore hadir di kantor)</option>
                            <option value="DL AKHIR">DL AKHIR (Pagi di kantor, siang/sore dinas luar)</option>
                            <option value="DL PENUH">DL PENUH (Seharian penuh dinas luar di lokasi tugas)</option>
                        </select>
                    </div>

                    <!-- Realtime GPS Status & Geotag Card -->
                    <div class="rounded-xl p-3 bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-xs">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-block w-2 h-2 rounded-full"
                                    :class="geoCoords.latitude !== null ? 'bg-emerald-500' : (geoError ? 'bg-rose-500' : 'bg-amber-500')"></span>
                                <label class="font-bold text-slate-800 dark:text-slate-200">
                                    Sinyal Lokasi GPS (Realtime)
                                </label>
                            </div>
                            <button
                                type="button"
                                @click="startRealtimeLocation"
                                :disabled="geoLoading"
                                class="text-blue-600 hover:text-blue-700 dark:text-blue-400 font-semibold inline-flex items-center gap-1.5 transition disabled:opacity-50 cursor-pointer"
                                title="Sinkronisasi Ulang Titik Satelit"
                            >
                                <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': geoLoading }" />
                                <span>{{ geoLoading ? 'Mencari...' : 'Perbarui Sinyal' }}</span>
                            </button>
                        </div>

                        <!-- Coordinates Row -->
                        <div class="flex items-center justify-between flex-wrap gap-2 text-slate-700 dark:text-slate-300">
                            <div v-if="geoCoords.latitude !== null" class="flex items-center gap-2 font-medium">
                                <span>{{ geoCoords.latitude.toFixed(6) }}, {{ geoCoords.longitude.toFixed(6) }}</span>
                                <span v-if="geoCoords.accuracy !== null" class="text-[11px] text-slate-400">
                                    (±{{ geoCoords.accuracy }}m)
                                </span>
                            </div>
                            <div v-else class="text-slate-400 italic text-[11px]">
                                {{ geoError || 'Mencari sinyal GPS satelit...' }}
                            </div>
                        </div>

                        <!-- Lokasi Terkunci Otomatis (Read-only / Terkunci dari Ketikan Manual) -->
                        <div class="mt-2.5 flex items-start gap-2 p-2.5 rounded-lg bg-slate-100/90 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-700/80 text-slate-700 dark:text-slate-300 select-none">
                            <MapPin class="w-4 h-4 text-blue-500 mt-0.5 shrink-0" />
                            <div class="min-w-0 flex-1 space-y-0.5">
                                <div class="text-[11px] font-medium leading-relaxed break-words text-slate-800 dark:text-slate-200">
                                    {{ dlForm.alamat || geoAddress || 'Mendeteksi titik koordinat satelit...' }}
                                </div>
                                <div class="flex items-center gap-1 text-[10px] text-slate-400 dark:text-slate-500 font-normal">
                                    <Lock class="w-3 h-3 text-slate-400 shrink-0" />
                                    <span>Terkunci otomatis via sensor GPS perangkat (tidak dapat diubah)</span>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" v-model="dlForm.alamat" />

                        <p v-if="geoError" class="text-[11px] text-rose-600 mt-1 flex items-center gap-1 font-medium">
                            <AlertCircle class="w-3 h-3 flex-shrink-0" />
                            <span>{{ geoError }}</span>
                        </p>
                    </div>

                    <!-- Kamera & Foto Selfie -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block font-bold text-slate-700 dark:text-slate-300">
                                Swafoto Kehadiran Lapangan (Geotagged) <span class="text-rose-500">*</span>
                            </label>
                            <span v-if="currentTime" class="text-[10px] text-slate-400 font-mono">
                                {{ currentTime }}
                            </span>
                        </div>

                        <!-- Video Stream Viewfinder (Responsive Landscape / Horizontal & Portrait / Vertikal) -->
                        <div v-if="useWebcam" class="space-y-2.5">
                            <div
                                class="relative rounded-2xl overflow-hidden bg-black shadow-lg border border-slate-700/60 transition-all duration-300"
                                :class="activeAspectClass"
                            >
                                <!-- Video feed (Anti-Mirror dengan transform flip horizontal jika kamera depan) -->
                                <video
                                    ref="videoRef"
                                    autoplay
                                    playsinline
                                    @loadedmetadata="onVideoMetadata"
                                    class="w-full h-full object-cover transition-transform duration-150"
                                    :class="{ '-scale-x-100': isFlipped }"
                                ></video>

                                <!-- Top Viewfinder Bar: Live Status & Responsive Orientation Toggle -->
                                <div class="absolute top-0 inset-x-0 p-2.5 bg-gradient-to-b from-black/80 via-black/40 to-transparent flex items-center justify-between pointer-events-auto">
                                    <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-black/60 backdrop-blur-xs text-[10px] font-bold text-white border border-white/10">
                                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                        <span class="tracking-wider uppercase">Live Camera</span>
                                    </div>

                                    <!-- Quick Controls inside Viewfinder -->
                                    <div class="flex items-center gap-1.5">
                                        <!-- Orientation Mode Switcher -->
                                        <button
                                            type="button"
                                            @click="toggleOrientationMode"
                                            class="px-2.5 py-1 rounded-full bg-black/60 hover:bg-black/85 backdrop-blur-xs text-[10px] font-semibold text-white border border-white/15 flex items-center gap-1 transition cursor-pointer"
                                            title="Ubah Rasio / Orientasi Kamera (Landscape / Portrait)"
                                        >
                                            <Monitor v-if="cameraOrientation === 'landscape'" class="w-3.5 h-3.5 text-blue-400" />
                                            <Smartphone v-else-if="cameraOrientation === 'portrait'" class="w-3.5 h-3.5 text-blue-400" />
                                            <Maximize2 v-else class="w-3.5 h-3.5 text-emerald-400" />
                                            <span class="capitalize">{{ cameraOrientation === 'auto' ? (isStreamPortrait ? 'Vertikal (Auto)' : 'Horizontal (Auto)') : (cameraOrientation === 'landscape' ? 'Horizontal' : 'Vertikal') }}</span>
                                        </button>

                                        <!-- Flip Front/Back Camera -->
                                        <button
                                            type="button"
                                            @click="flipCamera"
                                            class="p-1.5 rounded-full bg-black/60 hover:bg-black/85 backdrop-blur-xs text-white border border-white/15 transition cursor-pointer"
                                            title="Ganti Kamera Depan / Belakang"
                                        >
                                            <SwitchCamera class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Authentic Field Survey Camera Timestamp (Warna Natural & Adaptif Responsive) -->
                                <div class="absolute bottom-0 inset-x-0 p-3 bg-gradient-to-t from-black/90 via-black/60 to-transparent text-white pointer-events-none select-none">
                                    <div class="space-y-0.5 text-[10px] sm:text-[11px] font-sans drop-shadow-sm">
                                        <div class="font-bold text-white text-xs truncate">
                                            PEMERINTAH PROVINSI DKI JAKARTA — SUDIN KOMINFOTIK JAKARTA UTARA
                                        </div>
                                        <div class="text-slate-100 font-medium truncate">
                                            Pegawai: {{ authUser.nama_lengkap }} ({{ authUser.nrk || authUser.nik || '-' }}) • Seksi: {{ authUser.seksi || 'KIP' }}
                                        </div>
                                        <div class="text-slate-200 flex items-center gap-1.5 flex-wrap text-[10px]">
                                            <span v-if="geoCoords.latitude !== null">
                                                Koordinat: {{ geoCoords.latitude.toFixed(6) }}, {{ geoCoords.longitude.toFixed(6) }}
                                            </span>
                                            <span v-else class="text-slate-400 italic">
                                                Menghubungkan sinyal satelit GPS...
                                            </span>
                                            <span v-if="geoCoords.accuracy !== null" class="text-slate-300">
                                                (±{{ geoCoords.accuracy }}m)
                                            </span>
                                            <span class="text-slate-400">•</span>
                                            <span>{{ currentTime || 'WIB' }}</span>
                                        </div>
                                        <div class="text-slate-300 truncate max-w-md text-[10px]">
                                            Lokasi: {{ geoAddress || dlForm.alamat || 'Menentukan titik presensi lapangan...' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Camera Controls Bar -->
                            <div class="flex items-center justify-between gap-2 pt-1">
                                <button
                                    type="button"
                                    @click="stopCamera(true)"
                                    class="px-3 py-2 text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 font-semibold transition cursor-pointer"
                                >
                                    Batal Kamera
                                </button>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="flipCamera"
                                        title="Ganti Kamera Depan / Belakang"
                                        class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700/60 shadow-xs flex items-center gap-1.5 transition cursor-pointer"
                                    >
                                        <SwitchCamera class="w-4 h-4" />
                                        <span class="hidden sm:inline">{{ facingMode === 'user' ? 'Kamera Belakang' : 'Kamera Depan' }}</span>
                                    </button>

                                    <button
                                        type="button"
                                        @click="capturePhoto"
                                        class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl shadow-md hover:shadow-lg flex items-center gap-2 transition active:scale-95 cursor-pointer"
                                    >
                                        <Camera class="w-4 h-4" />
                                        <span>Ambil Foto & Watermark</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Hasil Foto (Responsive & Badge Geotagged) -->
                        <div
                            v-else-if="capturedImage"
                            class="relative rounded-2xl overflow-hidden max-h-80 bg-slate-950 flex flex-col items-center justify-center shadow-md border border-slate-700/60 group"
                        >
                            <img
                                :src="capturedImage"
                                alt="Preview Foto Geotag"
                                class="w-full max-h-72 object-contain cursor-pointer hover:opacity-95 transition"
                                @click="openPreview(capturedImage)"
                                title="Klik foto untuk melihat ukuran penuh"
                            />
                            
                            <!-- Geotagged Verified Badge -->
                            <div class="absolute bottom-2 left-2 px-2.5 py-1 rounded-lg bg-black/75 backdrop-blur-xs text-white text-[11px] font-bold flex items-center gap-1.5 border border-white/10 shadow-sm">
                                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-400" />
                                <span>Swafoto Geotagged Terverifikasi (Bisa Diklik)</span>
                            </div>

                            <button
                                type="button"
                                @click="retakePhoto"
                                class="absolute top-2.5 right-2.5 bg-black/70 hover:bg-black/90 text-white rounded-full p-2 shadow-lg transition cursor-pointer flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 border border-white/20"
                                title="Ambil Ulang Foto"
                            >
                                <RotateCcw class="w-3.5 h-3.5" />
                                <span>Foto Ulang</span>
                            </button>
                        </div>

                        <!-- Tombol Buka Kamera / Upload File -->
                        <div v-else class="space-y-2">
                            <!-- Alert Wajib Foto -->
                            <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-[11px] flex items-start gap-2">
                                <AlertCircle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                                <div>
                                    <span class="font-bold">Wajib Mengambil Foto:</span>
                                    <span> Sistem mewajibkan swafoto lapangan langsung (live GPS) atau unggah berkas foto bukti penugasan sebelum permohonan dapat dikirim.</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    @click="startCamera"
                                    class="py-3.5 px-4 border-2 border-dashed border-blue-400 dark:border-blue-600 rounded-2xl text-blue-600 dark:text-blue-400 font-bold hover:bg-blue-50 dark:hover:bg-blue-950/40 flex items-center justify-center gap-2 transition shadow-2xs cursor-pointer group"
                                >
                                    <Camera class="w-5 h-5 group-hover:scale-110 transition" />
                                    <span>Buka Kamera (GPS Live)</span>
                                </button>
                                <label class="py-3.5 px-4 border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer flex items-center justify-center gap-2 transition shadow-2xs group">
                                    <UploadCloud class="w-5 h-5 group-hover:scale-110 transition text-slate-400" />
                                    <span>Unggah Berkas</span>
                                    <input type="file" accept="image/*" @change="handleFileInput" class="hidden" />
                                </label>
                            </div>
                        </div>

                        <!-- Pesan Kesalahan Validasi Foto -->
                        <div v-if="photoError || dlForm.errors.foto" class="mt-2.5 p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2 font-semibold">
                            <AlertCircle class="w-4 h-4 text-rose-500 shrink-0" />
                            <span>{{ photoError || dlForm.errors.foto }}</span>
                        </div>

                        <canvas ref="canvasRef" class="hidden"></canvas>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
                            Keterangan / Nomor Surat Tugas <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="dlForm.keterangan"
                            rows="2"
                            placeholder="Uraikan maksud penugasan, nomor surat perintah tugas (SPT), atau agenda lapangan..."
                            required
                            class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-xs focus:ring-blue-500 focus:border-blue-500 p-2.5"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="closeDlModal"
                            class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 rounded-lg transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="dlForm.processing || !dlForm.foto"
                            class="px-5 py-2.5 text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5"
                            :class="dlForm.foto
                                ? 'bg-blue-600 hover:bg-blue-500 text-white cursor-pointer active:scale-95'
                                : 'bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-500 cursor-not-allowed border border-slate-300/60 dark:border-slate-700'"
                        >
                            <CheckCircle2 v-if="dlForm.foto" class="w-4 h-4" />
                            <AlertCircle v-else class="w-4 h-4 text-amber-500" />
                            <span>{{ dlForm.processing ? 'Mengirim...' : (!dlForm.foto ? 'Wajib Ambil Foto Sebelum Kirim' : 'Kirim Pengajuan DL') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MODAL PREVIEW FOTO FULL                                      -->
        <!-- ============================================================ -->
        <!-- ============================================================ -->
        <!-- MODAL PREVIEW FOTO FULL (DAPAT DIKLIK & DIBUKA RESOLUSI ASLI) -->
        <!-- ============================================================ -->
        <div
            v-if="previewPhotoUrl"
            class="fixed inset-0 z-[70] flex items-center justify-center p-3 sm:p-6 bg-black/90 backdrop-blur-md cursor-pointer select-none"
            @click="previewPhotoUrl = null"
        >
            <div class="relative max-w-4xl max-h-[94vh] flex flex-col items-center justify-center" @click.stop>
                <img
                    :src="previewPhotoUrl"
                    alt="Foto Geotag"
                    class="max-w-full max-h-[82vh] object-contain shadow-2xl block rounded-xl border border-white/15"
                />
                <div class="mt-3 flex items-center gap-2">
                    <a
                        :href="previewPhotoUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="px-4 py-2 rounded-xl bg-white/20 hover:bg-white/30 text-white text-xs font-bold backdrop-blur-xs transition flex items-center gap-1.5 cursor-pointer shadow-md"
                        title="Buka atau unduh file resolusi penuh di tab baru"
                    >
                        <ExternalLink class="w-3.5 h-3.5" />
                        <span>Buka / Unduh Resolusi Asli</span>
                    </a>
                    <button
                        type="button"
                        @click="previewPhotoUrl = null"
                        class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-md transition flex items-center gap-1.5 cursor-pointer"
                    >
                        <X class="w-3.5 h-3.5" />
                        <span>Tutup Pratinjau</span>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
