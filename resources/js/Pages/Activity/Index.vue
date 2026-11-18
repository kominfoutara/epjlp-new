<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    isOwner: Boolean,
    targetUser: Object,
    activities: [Array, Object],
    monthlyReport: Object,
    categories: Array,
    stats: Object,
    filters: Object,
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isSuperadmin = computed(() => currentUser.value.role === 'Superadmin');

const activityList = computed(() => {
    if (!props.activities) return [];
    if (Array.isArray(props.activities)) return props.activities;
    return props.activities.data || [];
});

const isPaginated = computed(() => {
    return props.activities && typeof props.activities === 'object' && !Array.isArray(props.activities) && props.activities.total !== undefined;
});

const totalActivitiesCount = computed(() => {
    if (props.stats && props.stats.total !== undefined) return props.stats.total;
    if (isPaginated.value) return props.activities.total;
    return activityList.value.length;
});

const monthsList = [
    { value: '01', label: 'Januari' },
    { value: '02', label: 'Februari' },
    { value: '03', label: 'Maret' },
    { value: '04', label: 'April' },
    { value: '05', label: 'Mei' },
    { value: '06', label: 'Juni' },
    { value: '07', label: 'Juli' },
    { value: '08', label: 'Agustus' },
    { value: '09', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const getTodayDateString = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const getCurrentYearMonthString = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}`;
};

const parseYearMonth = (val) => {
    if (val && typeof val === 'string' && val.includes('-')) {
        const [y, m] = val.split('-');
        return { year: y, month: m.padStart(2, '0') };
    }
    const now = new Date();
    return {
        year: String(now.getFullYear()),
        month: String(now.getMonth() + 1).padStart(2, '0'),
    };
};

const filterMonth = ref(props.filters?.bulan || getCurrentYearMonthString());
const initialYM = parseYearMonth(filterMonth.value);
const selectedMonth = ref(initialYM.month);
const selectedYear = ref(initialYM.year);

const currentYearNum = new Date().getFullYear();
const availableYears = computed(() => {
    const years = [];
    for (let y = currentYearNum - 2; y <= currentYearNum + 2; y++) {
        years.push(String(y));
    }
    if (selectedYear.value && !years.includes(selectedYear.value)) {
        years.push(selectedYear.value);
        years.sort();
    }
    return years;
});

const updatePeriodFilter = () => {
    filterMonth.value = `${selectedYear.value}-${selectedMonth.value}`;
    applyMonthFilter();
};

watch(() => props.filters?.bulan, (newVal) => {
    if (newVal) {
        const { year, month } = parseYearMonth(newVal);
        selectedYear.value = year;
        selectedMonth.value = month;
        filterMonth.value = `${year}-${month}`;
    }
}, { immediate: true });

// Form Tambah / Edit Aktivitas
const editingActivity = ref(null);
const previewActivity = ref(null);
const showDetailModal = ref(false);

// Standar fallback kategori tugas agar dropdown tidak pernah kosong dalam kondisi apapun
const FALLBACK_CATEGORIES = [
    'Update modul/website/microsite',
    'Monitoring Server',
    'Dukungan Teknis Aplikasi',
    'Fasilitasi Zoom Meeting / Live Streaming',
    'Statistik Sektoral',
    'Satu Data Indonesia',
    'Penerbitan Sertifikat Elektronik',
    'Instalasi Antivirus',
    'Insiden Siber',
    'Kontra Pengindraan',
    'Fasilitasi Email JakGo',
    'Diseminasi Keamanan Informasi',
    'Survey Indeks Kepuasan Layanan Persandian',
    'Dokumentasi',
    'Liputan',
    'Edit Video',
    'Desain Grafis',
    'Membuat Konten',
    'Edit Berita',
    'Melakukan pengecekan dan pemeliharaan sistem komputer dan jaringan',
    'Mendiagnosis dan memecahkan masalah (troubleshooting) sistem, jaringan dan hardware yang rumit/kompleks',
    'Melakukan instalasi/re-instalasi jaringan (crimping, splicing dan testing)',
    'Melaksanakan penataan sarana dan prasarana perangkat jaringan',
    'Administrasi Kepegawaian',
    'Pengelolaan Arsip dan Surat Menyurat',
    'Penyusunan Laporan dan Notula Rapat',
    'Pengelolaan Perlengkapan dan Aset',
    'Tugas dan Arahan Pimpinan'
];

const availableCategories = ref(
    Array.isArray(props.categories) && props.categories.length > 0
        ? [...props.categories]
        : [...FALLBACK_CATEGORIES]
);

const fetchCategoriesAsync = async () => {
    try {
        const response = await window.axios.get(route('activity.category-list', { user_id: props.targetUser?.id }));
        if (Array.isArray(response.data) && response.data.length > 0) {
            availableCategories.value = response.data;
            if (!activityForm.kategori) {
                activityForm.kategori = availableCategories.value[0];
            }
        }
    } catch (err) {
        console.warn('Gagal memuat kategori dari server, menggunakan daftar standar.', err);
    }
};

let syncInterval = null;
const isSyncing = ref(false);
const lastSyncTime = ref(new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));

const syncDataRealtime = (silent = false) => {
    if (!silent) isSyncing.value = true;
    router.reload({
        only: ['activities', 'stats'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isSyncing.value = false;
            lastSyncTime.value = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    });
};

const getFileType = (filename) => {
    if (!filename) return 'other';
    const clean = filename.trim().split('?')[0];
    const ext = clean.split('.').pop()?.toLowerCase();
    if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(ext)) return 'image';
    if (ext === 'pdf') return 'pdf';
    if (['xlsx', 'xls', 'csv'].includes(ext)) return 'excel';
    return 'other';
};

const getFileBadgeClass = (filename) => {
    const type = getFileType(filename);
    if (type === 'image') return 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 dark:hover:bg-emerald-900/40';
    if (type === 'pdf') return 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800 hover:bg-rose-100 dark:hover:bg-rose-900/40';
    if (type === 'excel') return 'bg-teal-50 dark:bg-teal-950/60 text-teal-700 dark:text-teal-300 border-teal-200 dark:border-teal-800 hover:bg-teal-100 dark:hover:bg-teal-900/40';
    return 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700';
};

const getFileIcon = (filename) => {
    const type = getFileType(filename);
    if (type === 'image') return '🖼️';
    if (type === 'pdf') return '📄';
    if (type === 'excel') return '📊';
    return '📎';
};

const getFileLabel = (filename, index) => {
    const type = getFileType(filename);
    if (type === 'image') return `Foto ${index + 1}`;
    if (type === 'pdf') return `PDF ${index + 1}`;
    if (type === 'excel') return `Excel ${index + 1}`;
    return `Bukti ${index + 1}`;
};

onMounted(() => {
    fetchCategoriesAsync();
    syncInterval = setInterval(() => {
        if (typeof document !== 'undefined' && document.visibilityState === 'visible' && !activityForm.processing) {
            syncDataRealtime(true);
        }
    }, 20000);
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    if (syncInterval) clearInterval(syncInterval);
    window.removeEventListener('keydown', handleGlobalKeydown);
});

const activityForm = useForm({
    tanggal: getTodayDateString(),
    jam_mulai: '08:00',
    jam_selesai: '16:00',
    kategori: availableCategories.value[0] || '',
    lokasi: '',
    nama_unit: '',
    uraian: '',
    lampiran: [],
    deleted_files: [],
});

// Form Catatan Bulanan (Analisa & Rekomendasi)
const monthlyForm = useForm({
    periode: filterMonth.value,
    user_id: props.targetUser?.id || currentUser.value?.id,
    analisa_kesimpulan: props.monthlyReport?.analisa_kesimpulan || '',
    rekomendasi: props.monthlyReport?.rekomendasi || '',
});

// Rejection Prompt Modal State
const showRejectModal = ref(false);
const rejectingActivity = ref(null);
const rejectionNote = ref('');
const rejectPreset = ref('');

// Bulk validation state for Admin/Kasi
const selectedIds = ref([]);
const validationStatus = ref('Disetujui');
const validationNote = ref('');
const isSubmittingValidation = ref(false);

// Toast Notification
const toastMessage = ref('');
const toastType = ref('success');
const showToast = ref(false);

const triggerToast = (msg, type = 'success') => {
    toastMessage.value = msg;
    toastType.value = type;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 2000);
};

const allSelected = computed({
    get: () => activityList.value.length > 0 && selectedIds.value.length === activityList.value.length,
    set: (val) => {
        selectedIds.value = val ? activityList.value.map(a => a.id) : [];
    }
});

const currentStatusFilter = computed(() => props.filters?.status || '');

const filterByStatus = (status) => {
    const nextStatus = currentStatusFilter.value === status ? '' : status;
    const targetRoute = (props.isOwner && currentUser.value.role === 'Kasi')
        ? route('activity.my')
        : route('activity.index');

    router.get(targetRoute, {
        user_id: props.isOwner ? undefined : props.targetUser?.id,
        bulan: filterMonth.value,
        status: nextStatus || undefined,
    }, { preserveState: true, replace: true });
};

const applyMonthFilter = () => {
    const targetRoute = (props.isOwner && currentUser.value.role === 'Kasi')
        ? route('activity.my')
        : route('activity.index');

    router.get(targetRoute, {
        user_id: props.isOwner ? undefined : props.targetUser?.id,
        bulan: filterMonth.value,
        status: currentStatusFilter.value || undefined,
    }, { preserveState: true, replace: true });
};

const fileInputRef = ref(null);

// State Lampiran Berkas
const selectedFiles = ref([]); // List of { file, name, size, previewUrl, isImage, isPdf, wasCompressed, originalSize, compressedSize }
const isProcessingUpload = ref(false);

// State Lightbox Photo Viewer
const showPhotoViewer = ref(false);
const activePhotoViewer = ref({
    url: '',
    title: '',
    index: 0,
    list: [],
});

const openPhotoViewer = (url, title = 'Foto Lampiran', list = [], index = 0) => {
    activePhotoViewer.value = {
        url,
        title,
        index,
        list: list.length > 0 ? list : [url],
    };
    showPhotoViewer.value = true;
};

const closePhotoViewer = () => {
    showPhotoViewer.value = false;
};

const nextPhotoViewer = () => {
    if (!activePhotoViewer.value.list || activePhotoViewer.value.list.length <= 1) return;
    const nextIdx = (activePhotoViewer.value.index + 1) % activePhotoViewer.value.list.length;
    const item = activePhotoViewer.value.list[nextIdx];
    activePhotoViewer.value.index = nextIdx;
    activePhotoViewer.value.url = typeof item === 'string' ? item : (item.url || item.previewUrl);
    activePhotoViewer.value.title = item.name || `Foto ${nextIdx + 1}`;
};

const prevPhotoViewer = () => {
    if (!activePhotoViewer.value.list || activePhotoViewer.value.list.length <= 1) return;
    const prevIdx = (activePhotoViewer.value.index - 1 + activePhotoViewer.value.list.length) % activePhotoViewer.value.list.length;
    const item = activePhotoViewer.value.list[prevIdx];
    activePhotoViewer.value.index = prevIdx;
    activePhotoViewer.value.url = typeof item === 'string' ? item : (item.url || item.previewUrl);
    activePhotoViewer.value.title = item.name || `Foto ${prevIdx + 1}`;
};

const handleGlobalKeydown = (e) => {
    if (!showPhotoViewer.value) return;
    if (e.key === 'Escape') closePhotoViewer();
    if (e.key === 'ArrowRight') nextPhotoViewer();
    if (e.key === 'ArrowLeft') prevPhotoViewer();
};

const isImageFile = (fileOrName) => {
    if (!fileOrName) return false;
    if (typeof fileOrName === 'string') {
        const ext = fileOrName.trim().split('?')[0].split('.').pop()?.toLowerCase();
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'avif', 'heic', 'heif', 'tiff', 'jfif', 'ico'].includes(ext);
    }
    return fileOrName.type?.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif|bmp|svg|avif|heic|heif|tiff|jfif|ico)$/i.test(fileOrName.name);
};

const isPdfFile = (fileOrName) => {
    if (!fileOrName) return false;
    if (typeof fileOrName === 'string') {
        const ext = fileOrName.trim().split('?')[0].split('.').pop()?.toLowerCase();
        return ext === 'pdf';
    }
    return fileOrName.type === 'application/pdf' || /\.pdf$/i.test(fileOrName.name);
};

const formatFileSize = (bytes) => {
    if (!bytes || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
};

// Kompresi foto jika ukuran > 5MB menggunakan HTML5 Canvas
const compressImageIfNeeded = async (file) => {
    if (!isImageFile(file) || file.size <= 5 * 1024 * 1024) {
        return {
            file,
            wasCompressed: false,
            originalSize: file.size,
            compressedSize: file.size,
        };
    }

    try {
        const compressedFile = await new Promise((resolve) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (e) => {
                const img = new Image();
                img.src = e.target.result;
                img.onload = () => {
                    let width = img.width;
                    let height = img.height;
                    const maxDim = 1920;

                    if (width > maxDim || height > maxDim) {
                        if (width > height) {
                            height = Math.round((height * maxDim) / width);
                            width = maxDim;
                        } else {
                            width = Math.round((width * maxDim) / height);
                            height = maxDim;
                        }
                    }

                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob((blob) => {
                        if (!blob) {
                            resolve(file);
                            return;
                        }
                        const newName = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                        const newFile = new File([blob], newName, {
                            type: 'image/jpeg',
                            lastModified: Date.now(),
                        });
                        resolve(newFile);
                    }, 'image/jpeg', 0.78);
                };
                img.onerror = () => resolve(file);
            };
            reader.onerror = () => resolve(file);
        });

        return {
            file: compressedFile,
            wasCompressed: true,
            originalSize: file.size,
            compressedSize: compressedFile.size,
        };
    } catch (err) {
        console.warn('Gagal mengompresi gambar:', err);
        return {
            file,
            wasCompressed: false,
            originalSize: file.size,
            compressedSize: file.size,
        };
    }
};

const handleFileUpload = async (e) => {
    const rawFiles = Array.from(e.target.files);
    if (rawFiles.length === 0) return;

    isProcessingUpload.value = true;

    // Hitung berkas lama yang tersisa jika mode edit
    const existingFiles = (editingActivity.value && editingActivity.value.lampiran)
        ? editingActivity.value.lampiran.split(',').map(f => f.trim()).filter(f => f && !activityForm.deleted_files.includes(f))
        : [];
    const existingImagesCount = existingFiles.filter(isImageFile).length;
    const existingPdfCount = existingFiles.filter(isPdfFile).length;

    // Filter foto & PDF dari pilihan baru
    let currentImages = selectedFiles.value.filter(item => item.isImage);
    let currentPdf = selectedFiles.value.find(item => item.isPdf);

    for (const file of rawFiles) {
        // 1. Aturan wajib: 1 file maksimal 10 MB. Jika lebih dari 10 MB, munculkan peringatan & tolak berkas
        if (file.size > 10 * 1024 * 1024) {
            triggerToast(`Peringatan: Ukuran berkas "${file.name}" (${formatFileSize(file.size)}) melebihi batas maksimal 10 MB. Berkas tidak dapat diunggah.`, 'error');
            continue;
        }

        if (isImageFile(file)) {
            // Batas maksimal 5 foto
            if (currentImages.length + existingImagesCount >= 5) {
                triggerToast('Maksimal 5 foto dokumentasi yang dapat diunggah.', 'error');
                continue;
            }

            // Kompresi otomatis jika foto berukuran > 5MB
            const comp = await compressImageIfNeeded(file);
            const previewUrl = URL.createObjectURL(comp.file);

            currentImages.push({
                file: comp.file,
                name: comp.file.name,
                previewUrl,
                isImage: true,
                isPdf: false,
                originalSize: comp.originalSize,
                compressedSize: comp.compressedSize,
                wasCompressed: comp.wasCompressed,
            });

            if (comp.wasCompressed) {
                triggerToast(`Foto "${file.name}" otomatis dikompresi (${formatFileSize(comp.originalSize)} → ${formatFileSize(comp.compressedSize)}).`, 'success');
            }
        } else if (isPdfFile(file)) {
            if (currentPdf || existingPdfCount >= 1) {
                triggerToast('Maksimal 1 file PDF yang dapat diunggah.', 'error');
                continue;
            }

            currentPdf = {
                file,
                name: file.name,
                previewUrl: null,
                isImage: false,
                isPdf: true,
                originalSize: file.size,
                compressedSize: file.size,
                wasCompressed: false,
            };
        } else {
            triggerToast(`Format berkas "${file.name}" tidak didukung. Harap unggah Foto (format bebas) atau Dokumen PDF.`, 'error');
        }
    }

    selectedFiles.value = [...currentImages, ...(currentPdf ? [currentPdf] : [])];
    activityForm.lampiran = selectedFiles.value.map(item => item.file);

    if (activityForm.errors.lampiran) {
        activityForm.clearErrors('lampiran');
    }

    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }

    isProcessingUpload.value = false;
};

const removeSelectedFile = (index) => {
    const item = selectedFiles.value[index];
    if (item && item.previewUrl) {
        URL.revokeObjectURL(item.previewUrl);
    }
    selectedFiles.value.splice(index, 1);
    activityForm.lampiran = selectedFiles.value.map(f => f.file);
};

const clearSelectedFiles = () => {
    selectedFiles.value.forEach(item => {
        if (item.previewUrl) URL.revokeObjectURL(item.previewUrl);
    });
    selectedFiles.value = [];
};

const getPhotoUrlsFromLampiran = (lampiranStr) => {
    if (!lampiranStr) return [];
    return lampiranStr.split(',')
        .map(f => f.trim())
        .filter(f => f && isImageFile(f))
        .map(f => ({
            url: '/storage/lampiran_aktivitas/' + f,
            name: f,
        }));
};

const getPhotoIndexInLampiran = (lampiranStr, targetFilename) => {
    const list = getPhotoUrlsFromLampiran(lampiranStr);
    const idx = list.findIndex(item => item.name === targetFilename);
    return idx >= 0 ? idx : 0;
};

const cancelEdit = () => {
    editingActivity.value = null;
    clearSelectedFiles();
    activityForm.reset();
    activityForm.tanggal = getTodayDateString();
    activityForm.jam_mulai = '08:00';
    activityForm.jam_selesai = '16:00';
    activityForm.kategori = availableCategories.value[0] || '';
    activityForm.lokasi = '';
    activityForm.nama_unit = '';
    activityForm.uraian = '';
    activityForm.lampiran = [];
    activityForm.deleted_files = [];
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const openEditModal = (act) => {
    editingActivity.value = act;
    clearSelectedFiles();
    activityForm.tanggal = act.tanggal ? (typeof act.tanggal === 'string' ? act.tanggal.slice(0, 10) : act.tanggal) : '';
    activityForm.jam_mulai = act.jam_mulai ? (typeof act.jam_mulai === 'string' ? act.jam_mulai.slice(0, 5) : act.jam_mulai) : '';
    activityForm.jam_selesai = act.jam_selesai ? (typeof act.jam_selesai === 'string' ? act.jam_selesai.slice(0, 5) : act.jam_selesai) : '';
    activityForm.kategori = act.kategori;
    activityForm.lokasi = act.lokasi || '';
    activityForm.nama_unit = act.nama_unit || '';
    activityForm.uraian = act.uraian;
    activityForm.lampiran = [];
    activityForm.deleted_files = [];
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
    const el = document.getElementById('form-aktivitas');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
};

const openDetailModal = (act) => {
    previewActivity.value = act;
    showDetailModal.value = true;
};

const submitActivity = () => {
    if (!activityForm.tanggal) {
        triggerToast('Tanggal kegiatan wajib dipilih.', 'error');
        return;
    }
    if (!activityForm.jam_mulai || !activityForm.jam_selesai) {
        triggerToast('Jam mulai dan jam selesai wajib diisi.', 'error');
        return;
    }
    if (!activityForm.kategori) {
        triggerToast('Kategori kegiatan wajib dipilih.', 'error');
        return;
    }
    if (!activityForm.uraian || !activityForm.uraian.trim()) {
        triggerToast('Uraian Tugas & Hasil Capaian wajib diisi.', 'error');
        return;
    }

    const existingFiles = (editingActivity.value && editingActivity.value.lampiran)
        ? editingActivity.value.lampiran.split(',').filter(f => f && !activityForm.deleted_files.includes(f.trim()))
        : [];
    const remainingOldImages = existingFiles.filter(isImageFile).length;
    const newImagesCount = selectedFiles.value.filter(f => f.isImage).length;
    const totalImagesCount = remainingOldImages + newImagesCount;

    const remainingOldPdfs = existingFiles.filter(isPdfFile).length;
    const newPdfCount = selectedFiles.value.filter(f => f.isPdf).length;
    const totalPdfCount = remainingOldPdfs + newPdfCount;

    if (totalImagesCount < 1) {
        triggerToast('Lampiran foto wajib diunggah minimal 1 foto dokumentasi kegiatan (maksimal 5 foto).', 'error');
        return;
    }

    if (totalImagesCount > 5) {
        triggerToast('Maksimal 5 foto dokumentasi yang dapat diunggah.', 'error');
        return;
    }

    if (totalPdfCount > 1) {
        triggerToast('Maksimal 1 file dokumen PDF yang dapat diunggah.', 'error');
        return;
    }

    const scrollToTable = () => {
        setTimeout(() => {
            const el = document.getElementById('tabel-log-aktivitas');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth' });
            }
        }, 150);
    };

    if (editingActivity.value) {
        activityForm.post(route('activity.update', editingActivity.value.id), {
            forceFormData: true,
            preserveScroll: false,
            onSuccess: () => {
                cancelEdit();
                triggerToast('Aktivitas berhasil diperbarui.', 'success');
                scrollToTable();
            },
            onError: (errors) => {
                const errorList = Object.values(errors).flat();
                const firstError = errorList[0] || 'Gagal memperbarui aktivitas. Silakan periksa kembali data pada formulir.';
                triggerToast(firstError, 'error');
            }
        });
    } else {
        activityForm.post(route('activity.store'), {
            forceFormData: true,
            preserveScroll: false,
            onSuccess: () => {
                cancelEdit();
                triggerToast('Aktivitas baru berhasil disimpan ke Log Aktivitas Harian.', 'success');
                scrollToTable();
            },
            onError: (errors) => {
                const errorList = Object.values(errors).flat();
                const firstError = errorList[0] || 'Gagal menyimpan aktivitas. Silakan periksa kembali data pada formulir.';
                triggerToast(firstError, 'error');
            }
        });
    }
};

const deleteActivity = (act) => {
    if (confirm('Apakah Anda yakin ingin menghapus catatan aktivitas ini?')) {
        router.delete(route('activity.destroy', act.id), {
            preserveScroll: true,
            onSuccess: () => {
                triggerToast('Aktivitas berhasil dihapus.', 'success');
                syncDataRealtime();
            },
        });
    }
};

// Quick Validate Single Action (Admin / Kasi)
const quickValidateSingle = (activity, status, note = null) => {
    router.post(route('activity.quick-validate', activity.id), {
        status: status,
        catatan: note,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            activity.status_validasi = status;
            if (note) activity.catatan = note;
            triggerToast(`Aktivitas berhasil diubah menjadi ${status}.`, 'success');
            if (showDetailModal.value) showDetailModal.value = false;
            if (showRejectModal.value) showRejectModal.value = false;
        },
        onError: () => {
            triggerToast('Gagal memproses validasi aktivitas.', 'error');
        }
    });
};

const openRejectPrompt = (act) => {
    rejectingActivity.value = act;
    rejectionNote.value = '';
    rejectPreset.value = '';
    showRejectModal.value = true;
};

const confirmReject = () => {
    if (!rejectingActivity.value) return;
    const note = rejectionNote.value || rejectPreset.value || 'Catatan perbaikan verifikator';
    quickValidateSingle(rejectingActivity.value, 'Ditolak', note);
};

// Bulk Validate (Admin / Kasi)
const submitBulkValidation = () => {
    if (selectedIds.value.length === 0) return;
    isSubmittingValidation.value = true;
    router.post(route('activity.validate-checkbox'), {
        activity_ids: selectedIds.value,
        status: validationStatus.value,
        catatan: validationNote.value || undefined,
    }, {
        onSuccess: () => {
            triggerToast(`${selectedIds.value.length} aktivitas berhasil divalidasi (${validationStatus.value}).`, 'success');
            selectedIds.value = [];
            validationNote.value = '';
            isSubmittingValidation.value = false;
        },
        onError: () => {
            triggerToast('Gagal memproses validasi massal.', 'error');
            isSubmittingValidation.value = false;
        }
    });
};

const saveMonthlyNotes = () => {
    monthlyForm.periode = filterMonth.value;
    monthlyForm.post(route('activity.save-monthly-report'), {
        preserveScroll: true,
        onSuccess: () => triggerToast('Catatan laporan bulanan berhasil disimpan.', 'success'),
    });
};

const formatDateIndo = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
};
</script>

<template>
    <Head :title="isOwner ? 'Aktivitas Harian Saya' : `Aktivitas PJLP: ${targetUser.nama_lengkap}`" />

    <AuthenticatedLayout>
        <!-- Floating Toast -->
        <transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="showToast" class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-2xl text-white font-medium text-xs sm:text-sm border"
                :class="toastType === 'success' ? 'bg-emerald-600 border-emerald-500' : 'bg-rose-600 border-rose-500'">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <span>{{ toastMessage }}</span>
            </div>
        </transition>

        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div v-if="!isOwner" class="mb-1">
                        <Link :href="route('activity.index')" class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 hover:text-emerald-700">
                            <span>←</span>
                            <span>Kembali ke Validasi Aktivitas</span>
                        </Link>
                    </div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-300/40">
                            {{ isOwner ? 'Pelaporan Kerja' : 'Validasi Aktivitas' }}
                        </span>
                        <span class="text-xs text-slate-400">• Periode {{ filterMonth }}</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">
                        {{ isOwner ? 'Aktivitas Harian Saya' : targetUser.nama_lengkap }}
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {{ targetUser.seksi || 'Seksi Kominfotik' }} • {{ targetUser.posisi_pekerjaan || (targetUser.role === 'Kasi' ? 'Kepala Seksi' : 'PJLP') }} • PIN: {{ targetUser.pin || '-' }}
                    </p>
                </div>

                <!-- Realtime Sync Toolbar -->
                <div class="flex items-center gap-2.5 self-start sm:self-center">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm text-xs text-slate-600 dark:text-slate-300">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span class="font-bold text-slate-800 dark:text-slate-100">Data Realtime</span>
                        <span class="text-[11px] text-slate-400">Pkl {{ lastSyncTime }}</span>
                    </div>
                    <button @click="syncDataRealtime(false)" :disabled="isSyncing" type="button" title="Sinkronkan data sekarang"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/80 shadow-sm transition disabled:opacity-50">
                        <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" :class="{ 'animate-spin': isSyncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>{{ isSyncing ? 'Sinkron...' : 'Segarkan' }}</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <!-- 1. STATS & MONTH FILTER TOOLBAR -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Periode Selector -->
                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col justify-center">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
                        <span>Pilih Bulan Periode</span>
                        <span class="text-[10px] font-medium text-slate-400">Bulan & Tahun</span>
                    </label>
                    <div class="grid grid-cols-5 gap-2">
                        <select v-model="selectedMonth" @change="updatePeriodFilter"
                            class="col-span-3 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm px-2.5 py-2 cursor-pointer transition">
                            <option v-for="m in monthsList" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <select v-model="selectedYear" @change="updatePeriodFilter"
                            class="col-span-2 rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm px-2 py-2 cursor-pointer transition">
                            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                </div>

                <!-- Total Aktivitas -->
                <button type="button" @click="filterByStatus('')"
                    class="p-5 rounded-2xl border text-left transition-all duration-200 flex items-center justify-between cursor-pointer"
                    :class="currentStatusFilter === ''
                        ? 'bg-white dark:bg-slate-900 border-blue-400 dark:border-blue-600 ring-2 ring-blue-500/30 shadow-md'
                        : 'bg-white/80 dark:bg-slate-900/80 border-slate-200/80 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 shadow-sm opacity-90 hover:opacity-100'">
                    <div>
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span>Total Laporan</span>
                            <span v-if="currentStatusFilter === ''" class="text-[10px] text-blue-600 dark:text-blue-400 font-extrabold">• Aktif</span>
                        </div>
                        <div class="text-3xl font-black text-slate-900 dark:text-slate-100 mt-1">{{ stats?.total || 0 }}</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Semua entri bulan ini</div>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-lg shadow-xs">
                        📝
                    </div>
                </button>

                <!-- Disetujui -->
                <button type="button" @click="filterByStatus('Disetujui')"
                    class="p-5 rounded-2xl border text-left transition-all duration-200 flex items-center justify-between cursor-pointer"
                    :class="currentStatusFilter === 'Disetujui'
                        ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-500 dark:border-emerald-600 ring-2 ring-emerald-500/30 shadow-md'
                        : 'bg-white/80 dark:bg-slate-900/80 border-slate-200/80 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700 shadow-sm opacity-90 hover:opacity-100'">
                    <div>
                        <div class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span>Disetujui</span>
                            <span v-if="currentStatusFilter === 'Disetujui'" class="text-[10px] text-emerald-700 dark:text-emerald-300 font-extrabold">• Aktif</span>
                        </div>
                        <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ stats?.disetujui || 0 }}</div>
                        <div class="text-[11px] text-emerald-600/70 mt-0.5 font-medium">Tervalidasi atasan</div>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold shadow-xs">
                        ✓
                    </div>
                </button>

                <!-- Menunggu Validasi -->
                <button type="button" @click="filterByStatus('Menunggu')"
                    class="p-5 rounded-2xl border text-left transition-all duration-200 flex items-center justify-between cursor-pointer"
                    :class="currentStatusFilter === 'Menunggu'
                        ? 'bg-amber-50/50 dark:bg-amber-950/30 border-amber-500 dark:border-amber-600 ring-2 ring-amber-500/30 shadow-md'
                        : 'bg-white/80 dark:bg-slate-900/80 border-slate-200/80 dark:border-slate-800 hover:border-amber-300 dark:hover:border-amber-700 shadow-sm opacity-90 hover:opacity-100'">
                    <div>
                        <div class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span>Menunggu Validasi</span>
                            <span v-if="currentStatusFilter === 'Menunggu'" class="text-[10px] text-amber-700 dark:text-amber-300 font-extrabold">• Aktif</span>
                        </div>
                        <div class="text-3xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ stats?.menunggu || 0 }}</div>
                        <div class="text-[11px] text-amber-600/70 mt-0.5 font-medium">Perlu review</div>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shadow-xs">
                        ⏳
                    </div>
                </button>
            </div>

            <!-- Active Status Filter Banner -->
            <div v-if="currentStatusFilter" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/90 dark:border-amber-800/80 text-xs shadow-xs">
                <div class="flex items-center gap-2.5 text-amber-900 dark:text-amber-200 font-medium">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>Filter Aktif: Menampilkan aktivitas dengan status <strong>"{{ currentStatusFilter }}"</strong> ({{ activities.total }} entri)</span>
                </div>
                <button type="button" @click="filterByStatus('')" class="inline-flex items-center gap-1 text-xs font-bold text-amber-800 hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-100 underline self-start sm:self-auto cursor-pointer">
                    <span>✕</span>
                    <span>Tampilkan Semua Laporan</span>
                </button>
            </div>

            <!-- 2. FORM TAMBAH / EDIT AKTIVITAS (INLINE DI BAWAH STATS) -->
            <div v-if="isOwner" id="form-aktivitas" class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-sm p-6 sm:p-7 space-y-5 transition-all scroll-mt-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white flex items-center justify-center shadow-md shadow-emerald-500/20 text-base">
                            <span v-if="!editingActivity">✍️</span>
                            <span v-else>✏️</span>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                                {{ editingActivity
                                    ? (editingActivity.status_validasi === 'Ditolak' ? 'Perbaiki Aktivitas (Ditolak Atasan)' : 'Edit Catatan Aktivitas Harian')
                                    : 'Tambah Aktivitas Harian Baru' }}
                            </h3>
                            <p class="text-xs text-slate-400">
                                {{ editingActivity && editingActivity.status_validasi === 'Ditolak'
                                    ? 'Perbaiki uraian atau lampiran sesuai arahan verifikator, lalu simpan untuk validasi ulang.'
                                    : 'Pastikan seluruh uraian tugas sesuai dengan KAK penugasan.' }}
                            </p>
                        </div>
                    </div>
                    <div v-if="editingActivity" class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                            :class="editingActivity.status_validasi === 'Ditolak'
                                ? 'bg-rose-100 text-rose-800 ring-1 ring-rose-400/40 dark:bg-rose-950/80 dark:text-rose-200'
                                : 'bg-amber-50 text-amber-700 ring-1 ring-amber-300/40 dark:bg-amber-950/60 dark:text-amber-300'">
                            {{ editingActivity.status_validasi === 'Ditolak' ? 'Mode Perbaikan (Ditolak)' : 'Mode Edit' }}
                        </span>
                        <button type="button" @click="cancelEdit" class="text-xs font-bold text-slate-500 hover:text-rose-600 transition cursor-pointer">
                            Batal Edit
                        </button>
                    </div>
                </div>

                <!-- Banner Informasi Khusus jika Aktivitas Ditolak -->
                <div v-if="editingActivity && editingActivity.status_validasi === 'Ditolak'"
                    class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200/90 dark:border-rose-800/80 text-xs space-y-1.5 animate-in fade-in duration-200">
                    <div class="flex items-center gap-2 font-bold text-rose-800 dark:text-rose-200">
                        <span class="text-base">⚠️</span>
                        <span>Aktivitas ini sebelumnya ditolak oleh atasan/verifikator</span>
                    </div>
                    <p v-if="editingActivity.catatan" class="text-rose-700 dark:text-rose-300 pl-6 italic">
                        Alasan penolakan: "<strong>{{ editingActivity.catatan }}</strong>"
                    </p>
                    <p class="text-slate-600 dark:text-slate-400 pl-6">
                        Silakan perbarui data tugas atau dokumen lampiran di bawah ini. Setelah Anda menekan tombol <strong>Perbarui Aktivitas</strong>, status akan otomatis kembali menjadi <strong>Menunggu</strong> agar dapat ditinjau dan divalidasi ulang oleh atasan.
                    </p>
                </div>

                <form @submit.prevent="submitActivity" novalidate class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Tanggal</label>
                            <input type="date" v-model="activityForm.tanggal" required
                                class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold dark:text-slate-200 focus:ring-emerald-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Jam Mulai</label>
                            <input type="time" v-model="activityForm.jam_mulai" required
                                class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold dark:text-slate-200 focus:ring-emerald-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Jam Selesai</label>
                            <input type="time" v-model="activityForm.jam_selesai" required
                                class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold dark:text-slate-200 focus:ring-emerald-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Kategori Pelayanan</label>
                            <select v-model="activityForm.kategori" required
                                class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs font-bold dark:text-slate-200 focus:ring-emerald-500">
                                <option value="" disabled>-- Pilih Kategori Pelayanan --</option>
                                <option v-if="activityForm.kategori && availableCategories.length && !availableCategories.includes(activityForm.kategori)" :value="activityForm.kategori">{{ activityForm.kategori }}</option>
                                <option v-for="cat in availableCategories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Lokasi Kerja <span class="text-slate-400 font-normal text-[11px]">(Opsional)</span>
                            </label>
                            <input type="text" v-model="activityForm.lokasi" placeholder="Misal: Kantor Walikota JU (Opsional)"
                                class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs dark:text-slate-200 focus:ring-emerald-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                                Nama Unit <span class="text-slate-400 font-normal text-[11px]">(Opsional)</span>
                            </label>
                            <input type="text" v-model="activityForm.nama_unit" placeholder="Misal: Ruang NOC / Server"
                                class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs dark:text-slate-200 focus:ring-emerald-500" />
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Uraian Tugas & Hasil Capaian <span class="text-rose-500 font-bold">*</span>
                            </label>
                            <span class="text-[10px] text-slate-400">Wajib diisi</span>
                        </div>
                        <textarea v-model="activityForm.uraian" rows="3" placeholder="Jelaskan secara komprehensif apa yang dikerjakan beserta output hasil pekerjaannya..." required
                            class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs dark:text-slate-200 focus:ring-emerald-500 leading-relaxed transition"
                            :class="{ 'border-rose-500 focus:border-rose-500 focus:ring-rose-500': activityForm.errors.uraian }"></textarea>
                        <p v-if="activityForm.errors.uraian" class="mt-1 text-xs text-rose-500 font-medium">
                            {{ activityForm.errors.uraian }}
                        </p>
                    </div>

                    <div>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-1.5">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Lampiran Foto Dokumentasi & PDF <span class="text-rose-500 font-bold">*</span>
                            </label>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-300/40">
                                    Foto: Min. 1, Maks. 5 (Format Bebas, Auto-Kompres > 5MB, Maks. 10MB)
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-50 dark:bg-rose-950 text-rose-700 dark:text-rose-300 ring-1 ring-rose-300/40">
                                    PDF: Maks. 1 Dokumen (Maks. 10MB)
                                </span>
                            </div>
                        </div>

                        <!-- Berkas Tersimpan Saat Mode Edit -->
                        <div v-if="editingActivity && editingActivity.lampiran" class="mb-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                            <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 block mb-2">Berkas Tersimpan Saat Ini:</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                                <template v-for="(file, idx) in editingActivity.lampiran.split(',')" :key="idx">
                                    <div v-if="!activityForm.deleted_files.includes(file.trim())"
                                        class="p-2.5 rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-between gap-2 shadow-xs">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <!-- Jika foto: thumbnail kecil yang bisa diklik untuk preview -->
                                            <div v-if="isImageFile(file.trim())"
                                                @click="openPhotoViewer('/storage/lampiran_aktivitas/' + file.trim(), file.trim(), getPhotoUrlsFromLampiran(editingActivity.lampiran), getPhotoIndexInLampiran(editingActivity.lampiran, file.trim()))"
                                                class="w-10 h-10 rounded-lg overflow-hidden bg-slate-100 flex-shrink-0 cursor-pointer border border-slate-200 dark:border-slate-600 hover:opacity-90 relative group">
                                                <img :src="'/storage/lampiran_aktivitas/' + file.trim()" :alt="file.trim()" class="w-full h-full object-cover" />
                                                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center text-[10px] text-white">🔍</div>
                                            </div>
                                            <span v-else class="text-xl flex-shrink-0">{{ getFileIcon(file.trim()) }}</span>
                                            <div class="truncate">
                                                <div class="font-bold text-slate-800 dark:text-slate-200 text-xs truncate">{{ getFileLabel(file.trim(), idx) }}</div>
                                                <div class="text-[10px] text-slate-400 truncate">{{ file.trim() }}</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5 flex-shrink-0">
                                            <button v-if="isImageFile(file.trim())" type="button"
                                                @click="openPhotoViewer('/storage/lampiran_aktivitas/' + file.trim(), file.trim(), getPhotoUrlsFromLampiran(editingActivity.lampiran), getPhotoIndexInLampiran(editingActivity.lampiran, file.trim()))"
                                                class="text-xs text-emerald-600 hover:text-emerald-700 font-bold" title="Lihat Foto">
                                                Lihat
                                            </button>
                                            <a v-else :href="'/storage/lampiran_aktivitas/' + file.trim()" target="_blank"
                                                class="text-xs text-blue-600 hover:text-blue-700 font-bold" title="Buka PDF">
                                                Buka ↗
                                            </a>
                                            <button type="button" @click="activityForm.deleted_files.push(file.trim())"
                                                class="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition" title="Hapus berkas ini">
                                                ✕
                                            </button>
                                        </div>
                                    </div>
                                    <div v-else class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 flex items-center justify-between text-xs text-rose-600">
                                        <span class="line-through truncate text-[11px]">{{ file.trim() }} (dihapus)</span>
                                        <button type="button" @click="activityForm.deleted_files = activityForm.deleted_files.filter(f => f !== file.trim())"
                                            class="text-xs font-bold text-blue-600 hover:underline ml-2">Batal</button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Area Input File -->
                        <div class="relative">
                            <input ref="fileInputRef" type="file" multiple @change="handleFileUpload"
                                accept="image/*,application/pdf,.pdf"
                                :disabled="isProcessingUpload"
                                class="w-full text-xs text-slate-500 file:mr-3 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-950 dark:file:text-emerald-300 cursor-pointer disabled:opacity-50"
                                :class="{ 'ring-1 ring-rose-500 rounded-xl': activityForm.errors.lampiran || activityForm.errors['lampiran.0'] }" />

                            <div v-if="isProcessingUpload" class="mt-2 text-xs text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>Sedang memeriksa dan mengompres foto...</span>
                            </div>
                        </div>

                        <!-- Pratinjau Foto & PDF Terpilih yang Akan Diunggah -->
                        <div v-if="selectedFiles.length > 0" class="mt-3 space-y-2.5">
                            <!-- Section Foto (Maks 5) -->
                            <div v-if="selectedFiles.some(f => f.isImage)">
                                <div class="flex items-center justify-between text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1.5">
                                    <span>Foto yang Dipilih ({{ selectedFiles.filter(f => f.isImage).length }}/5):</span>
                                    <span class="text-slate-400 font-normal">Klik foto untuk melihat pratinjau</span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-2.5">
                                    <div v-for="(photo, pIdx) in selectedFiles.filter(f => f.isImage)" :key="'sel-photo-' + pIdx"
                                        class="group relative rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden shadow-xs">
                                        <!-- Thumbnail Image -->
                                        <div @click="openPhotoViewer(photo.previewUrl, photo.name, selectedFiles.filter(f => f.isImage).map(f => ({ url: f.previewUrl, name: f.name })), pIdx)"
                                            class="w-full h-24 bg-slate-100 dark:bg-slate-900 cursor-pointer relative overflow-hidden">
                                            <img :src="photo.previewUrl" :alt="photo.name" class="w-full h-full object-cover group-hover:scale-105 transition duration-200" />
                                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-bold transition">
                                                Lihat Foto 🔍
                                            </div>
                                        </div>
                                        <!-- Info Card -->
                                        <div class="p-1.5 text-[10px]">
                                            <div class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ photo.name }}</div>
                                            <div class="flex items-center justify-between mt-0.5 text-slate-500">
                                                <span v-if="photo.wasCompressed" class="text-emerald-600 font-bold" title="Auto kompres dari > 5MB">
                                                    ✓ {{ formatFileSize(photo.compressedSize) }}
                                                </span>
                                                <span v-else>{{ formatFileSize(photo.originalSize) }}</span>
                                                <button type="button" @click="removeSelectedFile(selectedFiles.indexOf(photo))"
                                                    class="text-rose-500 hover:text-rose-700 font-bold ml-1" title="Hapus foto ini">
                                                    ✕
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section Dokumen PDF (Maks 1) -->
                            <div v-if="selectedFiles.some(f => f.isPdf)">
                                <div class="text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                                    Dokumen PDF yang Dipilih (1/1):
                                </div>
                                <div v-for="pdf in selectedFiles.filter(f => f.isPdf)" :key="'sel-pdf-' + pdf.name"
                                    class="p-2.5 rounded-xl border border-rose-200 dark:border-rose-800/60 bg-rose-50/50 dark:bg-rose-950/30 flex items-center justify-between text-xs shadow-xs">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="text-2xl flex-shrink-0">📄</span>
                                        <div class="truncate">
                                            <div class="font-bold text-slate-800 dark:text-slate-200 truncate text-xs">{{ pdf.name }}</div>
                                            <div class="text-[10px] text-slate-500">{{ formatFileSize(pdf.originalSize) }} (Maks. 10MB)</div>
                                        </div>
                                    </div>
                                    <button type="button" @click="removeSelectedFile(selectedFiles.indexOf(pdf))"
                                        class="px-2 py-1 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-100 dark:hover:bg-rose-900/50 transition" title="Hapus PDF">
                                        ✕ Hapus
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-if="activityForm.errors.lampiran" class="mt-1 text-xs text-rose-500 font-medium">
                            {{ activityForm.errors.lampiran }}
                        </p>
                        <p v-if="activityForm.errors['lampiran.0']" class="mt-1 text-xs text-rose-500 font-medium">
                            {{ activityForm.errors['lampiran.0'] }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="cancelEdit" class="px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition">
                            {{ editingActivity ? 'Batal Edit' : 'Reset' }}
                        </button>
                        <button type="submit" :disabled="activityForm.processing"
                            class="px-5 py-2.5 text-xs font-bold bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl shadow-md shadow-emerald-600/20 disabled:opacity-50 transition">
                            {{ activityForm.processing ? 'Menyimpan...' : (editingActivity ? 'Perbarui Aktivitas' : 'Simpan Aktivitas') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- 2. ADMIN BULK ACTION TOOLBAR (TASK 16 OPTIMIZATION) -->
            <div v-if="!isOwner && !isSuperadmin && activityList.length > 0"
                class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-indigo-800/60 p-4 sm:p-5 rounded-2xl shadow-xl text-white flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-400 animate-pulse"></span>
                        <span class="text-xs sm:text-sm font-bold text-white">
                            {{ selectedIds.length }} dari {{ activityList.length }} entri terpilih
                        </span>
                    </div>

                    <div class="h-4 w-px bg-slate-700 hidden sm:block"></div>

                    <!-- Target Status -->
                    <select v-model="validationStatus"
                        class="rounded-xl border-slate-700 bg-slate-800 text-xs font-bold text-white focus:ring-indigo-500 py-1.5 px-3">
                        <option value="Disetujui">Setujui Massal</option>
                        <option value="Ditolak">Tolak Massal</option>
                    </select>

                    <!-- Optional Note -->
                    <input type="text" v-model="validationNote" placeholder="Catatan evaluasi massal (opsional)..."
                        class="rounded-xl border-slate-700 bg-slate-800 text-xs text-white placeholder-slate-400 focus:ring-indigo-500 py-1.5 px-3 w-64" />
                </div>

                <div class="flex items-center gap-2 self-end md:self-center">
                    <button @click="selectedIds = []" type="button" :disabled="selectedIds.length === 0"
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-300 hover:text-white disabled:opacity-40 transition">
                        Batal Pilih
                    </button>
                    <button @click="submitBulkValidation" :disabled="selectedIds.length === 0 || isSubmittingValidation" type="button"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-xl shadow-md disabled:opacity-50 transition flex items-center gap-1.5">
                        <svg v-if="isSubmittingValidation" class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>{{ isSubmittingValidation ? 'Memproses...' : 'Terapkan Validasi Massal' }}</span>
                    </button>
                </div>
            </div>

            <!-- 3. CATATAN BULANAN PERKIN (ANALISA & REKOMENDASI) -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Catatan Laporan Bulanan (Perkin)</h3>
                        <p class="text-xs text-slate-500">Analisa kesimpulan dan rekomendasi kinerja bulanan {{ targetUser?.role === 'Kasi' ? 'Kepala Seksi' : 'pegawai PJLP' }} periode {{ filterMonth }}</p>
                    </div>
                    <button v-if="!isSuperadmin" @click="saveMonthlyNotes" :disabled="monthlyForm.processing" type="button"
                        class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 disabled:opacity-50 transition">
                        {{ monthlyForm.processing ? 'Menyimpan...' : 'Simpan Catatan Perkin' }}
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Analisa & Kesimpulan</label>
                        <textarea v-model="monthlyForm.analisa_kesimpulan" rows="4" placeholder="Uraikan analisa dan kesimpulan pelaksanaan tugas selama sebulan..."
                            class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:text-slate-200 leading-relaxed"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Rekomendasi</label>
                        <textarea v-model="monthlyForm.rekomendasi" rows="4" placeholder="Uraikan rekomendasi atau saran peningkatan untuk bulan berikutnya..."
                            class="w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-xs shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:text-slate-200 leading-relaxed"></textarea>
                    </div>
                </div>
            </div>

            <!-- 4. TABEL AKTIVITAS HARIAN -->
            <div id="tabel-log-aktivitas" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden scroll-mt-6">
                <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Log Aktivitas Harian</h3>
                        <span class="text-xs text-slate-400">• Total {{ totalActivitiesCount }} Catatan</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-300/40">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live Sync
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="syncDataRealtime(false)" :disabled="isSyncing" type="button" title="Perbarui log sekarang"
                            class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400 font-semibold transition">
                            <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span>{{ isSyncing ? 'Memperbarui...' : 'Sinkronkan' }}</span>
                        </button>
                        <div v-if="isPaginated" class="text-xs text-slate-400 font-medium">
                            Halaman {{ activities.current_page }} dari {{ activities.last_page }} (Maks. 10 per hal)
                        </div>
                    </div>
                </div>

                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 text-[11px]">
                            <tr>
                                <th v-if="!isOwner && !isSuperadmin" class="py-3.5 px-4 w-10 text-center">
                                    <input type="checkbox" v-model="allSelected" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
                                </th>
                                <th class="py-3.5 px-4">Waktu & Tanggal</th>
                                <th class="py-3.5 px-4">Kategori & Lokasi</th>
                                <th class="py-3.5 px-4">Uraian Aktivitas</th>
                                <th class="py-3.5 px-4">Lampiran</th>
                                <th class="py-3.5 px-4">Status Validasi</th>
                                <th class="py-3.5 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <tr v-if="!activityList || activityList.length === 0">
                                <td :colspan="isOwner ? 6 : (isSuperadmin ? 6 : 7)" class="py-12 text-center text-slate-400">
                                    <div class="text-sm font-semibold">Belum ada catatan aktivitas pada periode ini.</div>
                                    <p class="text-xs mt-1">{{ isOwner ? 'Klik tombol "Tambah Aktivitas Baru" di atas untuk mulai mencatat pekerjaan.' : 'Pegawai ini belum mengunggah laporan pekerjaan pada bulan terpilih.' }}</p>
                                </td>
                            </tr>
                            <tr v-for="act in activityList" :key="act.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition group">
                                <!-- Checkbox for Kasi/Admin -->
                                <td v-if="!isOwner && !isSuperadmin" class="py-4 px-4 text-center">
                                    <input type="checkbox" :value="act.id" v-model="selectedIds" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
                                </td>

                                <!-- Waktu & Tanggal -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">{{ formatDateIndo(act.tanggal) }}</div>
                                    <div class="text-[11px] font-mono text-slate-500 mt-0.5">{{ act.jam_mulai }} - {{ act.jam_selesai }} WIB</div>
                                </td>

                                <!-- Kategori & Lokasi -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 ring-1 ring-slate-200/60 dark:ring-slate-700">
                                        {{ act.kategori }}
                                    </span>
                                    <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1">
                                        <span v-if="act.lokasi">📍 {{ act.lokasi }}</span>
                                        <span v-else class="italic text-slate-400">📍 (Lokasi tidak diisi)</span>
                                        <span v-if="act.nama_unit">({{ act.nama_unit }})</span>
                                    </div>
                                </td>

                                <!-- Uraian Aktivitas -->
                                <td class="py-4 px-4 max-w-md">
                                    <div class="text-slate-800 dark:text-slate-200 text-xs line-clamp-3 leading-relaxed">
                                        {{ act.uraian }}
                                    </div>
                                    <div v-if="act.catatan" class="mt-1.5 text-[11px] text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 p-2 rounded-xl border border-rose-200 dark:border-rose-900">
                                        <strong>Catatan Verifikator:</strong> {{ act.catatan }}
                                    </div>
                                </td>

                                <!-- Lampiran (Multi-Format Smart Badges: Image, PDF, Excel) -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div v-if="act.lampiran" class="flex flex-wrap gap-1.5">
                                        <template v-for="(file, fIdx) in act.lampiran.split(',')" :key="fIdx">
                                            <!-- Jika Foto: klik membuka Lightbox Photo Viewer -->
                                            <button v-if="getFileType(file.trim()) === 'image'" type="button"
                                                @click="openPhotoViewer('/storage/lampiran_aktivitas/' + file.trim(), file.trim(), getPhotoUrlsFromLampiran(act.lampiran), getPhotoIndexInLampiran(act.lampiran, file.trim()))"
                                                :class="getFileBadgeClass(file.trim())"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition shadow-sm cursor-pointer"
                                                title="Klik untuk melihat foto">
                                                <span>🖼️</span>
                                                <span>{{ getFileLabel(file.trim(), fIdx) }}</span>
                                                <span class="text-[9px] opacity-75">🔍</span>
                                            </button>
                                            <!-- Jika PDF atau dokumen lain: buka tab baru -->
                                            <a v-else
                                                :href="'/storage/lampiran_aktivitas/' + file.trim()" target="_blank"
                                                :class="getFileBadgeClass(file.trim())"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition shadow-sm"
                                                title="Buka dokumen">
                                                <span>{{ getFileIcon(file.trim()) }}</span>
                                                <span>{{ getFileLabel(file.trim(), fIdx) }}</span>
                                                <span class="text-[9px] opacity-75">↗</span>
                                            </a>
                                        </template>
                                    </div>
                                    <span v-else class="text-slate-400 text-xs italic">-</span>
                                </td>

                                <!-- Status Validasi -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                                        :class="{
                                            'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800': act.status_validasi === 'Disetujui',
                                             'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800': act.status_validasi === 'Menunggu',
                                            'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800': act.status_validasi === 'Ditolak',
                                        }">
                                        <span class="w-1.5 h-1.5 rounded-full"
                                            :class="{
                                                'bg-emerald-500': act.status_validasi === 'Disetujui',
                                                'bg-amber-500 animate-pulse': act.status_validasi === 'Menunggu',
                                                'bg-rose-500': act.status_validasi === 'Ditolak',
                                            }"></span>
                                        {{ act.status_validasi }}
                                    </span>
                                </td>

                                <!-- Aksi Buttons -->
                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <!-- Aksi untuk PJLP (Owner) -->
                                    <div v-if="isOwner" class="flex items-center justify-center gap-2">
                                        <button @click="openDetailModal(act)" type="button" title="Lihat Detail Aktivitas"
                                            class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-emerald-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-emerald-400 transition cursor-pointer">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </button>

                                        <!-- Tombol Edit: Jika aktivitas Ditolak atau Menunggu -->
                                        <button v-if="act.status_validasi === 'Ditolak' || act.status_validasi === 'Menunggu'"
                                            @click="openEditModal(act)"
                                            type="button"
                                            :title="act.status_validasi === 'Ditolak' ? 'Aktivitas ditolak: Klik untuk perbaiki dan kirim ulang' : 'Edit Aktivitas'"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                                            :class="act.status_validasi === 'Ditolak'
                                                ? 'bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white border border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800'
                                                : 'bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white border border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800'">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            <span>{{ act.status_validasi === 'Ditolak' ? 'Perbaiki (Edit)' : 'Edit' }}</span>
                                        </button>
                                    </div>

                                    <!-- Aksi untuk Admin / Kasi (TASK 16: QUICK-VALIDATE BUTTONS) -->
                                    <div v-else class="flex items-center justify-center gap-1.5">
                                        <button @click="openDetailModal(act)" type="button" title="Lihat Detail Lengkap"
                                            class="p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </button>

                                        <!-- OPSI VALIDASI HANYA MUNCUL KETIKA MASIH MENUNGGU -->
                                        <template v-if="!isSuperadmin && act.status_validasi === 'Menunggu'">
                                            <!-- One-Click Quick Approve -->
                                            <button @click="quickValidateSingle(act, 'Disetujui')" type="button" title="Setujui Cepat"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition cursor-pointer">
                                                ✓ Setujui
                                            </button>

                                            <!-- One-Click Reject Prompt -->
                                            <button @click="openRejectPrompt(act)" type="button" title="Tolak dengan Alasan"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950 dark:hover:bg-rose-900 transition cursor-pointer">
                                                ✕ Tolak
                                            </button>
                                        </template>

                                        <!-- JIKA SUDAH DIVALIDASI: TOMBOL SEBALIKNYA MENGHILANG & STATUS TERKUNCI -->
                                        <span v-else-if="!isSuperadmin && act.status_validasi === 'Disetujui'" class="text-xs font-bold text-emerald-600">
                                            ✓ Disetujui
                                        </span>
                                        <span v-else-if="!isSuperadmin && act.status_validasi === 'Ditolak'" class="text-xs font-bold text-rose-600">
                                            ✕ Ditolak
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Adaptive Cards Feed (Display on mobile screens) -->
                <div class="block md:hidden p-3 sm:p-4 space-y-3 bg-slate-50/50 dark:bg-slate-950/20">
                    <!-- Mobile Select All Bar for Verifikator -->
                    <div v-if="!isOwner && !isSuperadmin && activityList && activityList.length > 0"
                        class="p-2.5 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 flex items-center justify-between text-xs shadow-2xs">
                        <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-700 dark:text-slate-300">
                            <input type="checkbox" v-model="allSelected" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
                            <span>Pilih Semua Halaman Ini</span>
                        </label>
                        <span class="text-[11px] font-mono text-slate-500">{{ activityList.length }} Entri</span>
                    </div>

                    <!-- Empty State Mobile -->
                    <div v-if="!activityList || activityList.length === 0" class="py-10 text-center text-slate-400 bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 p-6">
                        <div class="w-10 h-10 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mb-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Belum ada catatan aktivitas</div>
                        <p class="text-xs mt-1 text-slate-500">{{ isOwner ? 'Klik tombol "Tambah Aktivitas Baru" di atas untuk mulai mencatat pekerjaan.' : 'Pegawai ini belum mengunggah laporan pekerjaan pada bulan terpilih.' }}</p>
                    </div>

                    <!-- Activity Mobile Card -->
                    <div v-for="act in activityList" :key="'mob-act-' + act.id"
                        class="p-4 rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xs space-y-3 transition active:bg-slate-50/50">
                        <!-- Top Header: Tanggal & Waktu + Status Badge -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2.5 min-w-0">
                                <input v-if="!isOwner && !isSuperadmin" type="checkbox" :value="act.id" v-model="selectedIds"
                                    class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer mt-0.5" />
                                <div class="min-w-0">
                                    <div class="font-bold text-xs text-slate-900 dark:text-slate-100 flex items-center gap-1.5 flex-wrap">
                                        <span>{{ formatDateIndo(act.tanggal) }}</span>
                                    </div>
                                    <div class="text-[11px] font-mono text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>{{ act.jam_mulai }} - {{ act.jam_selesai }} WIB</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold flex-shrink-0"
                                :class="{
                                    'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800': act.status_validasi === 'Disetujui',
                                    'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800': act.status_validasi === 'Menunggu',
                                    'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800': act.status_validasi === 'Ditolak',
                                }">
                                <span class="w-1.5 h-1.5 rounded-full"
                                    :class="{
                                        'bg-emerald-500': act.status_validasi === 'Disetujui',
                                        'bg-amber-500 animate-pulse': act.status_validasi === 'Menunggu',
                                        'bg-rose-500': act.status_validasi === 'Ditolak',
                                    }"></span>
                                <span>{{ act.status_validasi }}</span>
                            </span>
                        </div>

                        <!-- Kategori & Lokasi Tag -->
                        <div class="flex items-center gap-2 flex-wrap text-[11px]">
                            <span class="px-2.5 py-0.5 rounded-lg font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 ring-1 ring-slate-200/60 dark:ring-slate-700">
                                {{ act.kategori }}
                            </span>
                            <span class="text-slate-500 dark:text-slate-400 flex items-center gap-1 truncate max-w-full">
                                <span>📍</span>
                                <span>{{ act.lokasi || '(Lokasi tidak diisi)' }}</span>
                                <span v-if="act.nama_unit" class="text-slate-400">({{ act.nama_unit }})</span>
                            </span>
                        </div>

                        <!-- Uraian Aktivitas -->
                        <div class="text-xs text-slate-800 dark:text-slate-200 leading-relaxed bg-slate-50/70 dark:bg-slate-800/40 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                            {{ act.uraian }}
                        </div>

                        <!-- Catatan Verifikator if any -->
                        <div v-if="act.catatan" class="text-[11px] text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 p-2 rounded-xl border border-rose-200 dark:border-rose-900">
                            <strong>Catatan Verifikator:</strong> {{ act.catatan }}
                        </div>

                        <!-- Lampiran -->
                        <div v-if="act.lampiran" class="pt-1 flex flex-wrap items-center gap-1.5">
                            <span class="text-[10px] text-slate-400 font-semibold mr-1">Lampiran:</span>
                            <template v-for="(file, fIdx) in act.lampiran.split(',')" :key="'mob-f-' + fIdx">
                                <button v-if="getFileType(file.trim()) === 'image'" type="button"
                                    @click="openPhotoViewer('/storage/lampiran_aktivitas/' + file.trim(), file.trim(), getPhotoUrlsFromLampiran(act.lampiran), getPhotoIndexInLampiran(act.lampiran, file.trim()))"
                                    :class="getFileBadgeClass(file.trim())"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition shadow-2xs active:scale-95 cursor-pointer">
                                    <span>🖼️</span>
                                    <span>{{ getFileLabel(file.trim(), fIdx) }}</span>
                                    <span class="text-[9px] opacity-75">🔍</span>
                                </button>
                                <a v-else
                                    :href="'/storage/lampiran_aktivitas/' + file.trim()" target="_blank"
                                    :class="getFileBadgeClass(file.trim())"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition shadow-2xs active:scale-95">
                                    <span>{{ getFileIcon(file.trim()) }}</span>
                                    <span>{{ getFileLabel(file.trim(), fIdx) }}</span>
                                    <span class="text-[9px] opacity-75">↗</span>
                                </a>
                            </template>
                        </div>

                        <!-- Footer Actions -->
                        <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2 flex-wrap">
                            <!-- PJLP (Owner) Actions -->
                            <template v-if="isOwner">
                                <button @click="openDetailModal(act)" type="button"
                                    class="flex-1 py-2 px-3 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition flex items-center justify-center gap-1.5 active:scale-95">
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Lihat Detail</span>
                                </button>

                                <button v-if="act.status_validasi === 'Ditolak' || act.status_validasi === 'Menunggu'"
                                    @click="openEditModal(act)"
                                    type="button"
                                    class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 active:scale-95 shadow-2xs"
                                    :class="act.status_validasi === 'Ditolak'
                                        ? 'bg-rose-600 text-white hover:bg-rose-500'
                                        : 'bg-amber-500 text-white hover:bg-amber-600'">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    <span>{{ act.status_validasi === 'Ditolak' ? 'Perbaiki' : 'Edit' }}</span>
                                </button>
                            </template>

                            <!-- Kasi / Admin Actions -->
                            <template v-else>
                                <button @click="openDetailModal(act)" type="button"
                                    class="py-2 px-3 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 transition flex items-center justify-center gap-1.5 active:scale-95">
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Detail</span>
                                </button>

                                <div v-if="!isSuperadmin && act.status_validasi === 'Menunggu'" class="flex items-center gap-1.5 flex-1 justify-end">
                                    <button @click="quickValidateSingle(act, 'Disetujui')" type="button"
                                        class="py-2 px-3.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-2xs transition flex items-center gap-1 active:scale-95">
                                        ✓ Setujui
                                    </button>
                                    <button @click="openRejectPrompt(act)" type="button"
                                        class="py-2 px-3 rounded-xl text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950 dark:text-rose-300 dark:border-rose-800 transition flex items-center gap-1 active:scale-95">
                                        ✕ Tolak
                                    </button>
                                </div>
                                <div v-else-if="!isSuperadmin" class="text-xs font-bold" :class="act.status_validasi === 'Disetujui' ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ act.status_validasi === 'Disetujui' ? '✓ Telah Disetujui' : '✕ Telah Ditolak' }}
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- 4. PAGINATION CONTROLS (MAX 10 PER HALAMAN) -->
                <div v-if="isPaginated && activities.total > activities.per_page"
                    class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 bg-slate-50/50 dark:bg-slate-800/30">
                    <div>
                        Menampilkan entri ke- <span class="font-bold text-slate-800 dark:text-slate-200">{{ activities.from || 0 }}</span> sampai
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ activities.to || 0 }}</span> dari total
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ activities.total }}</span> aktivitas
                        <span class="text-slate-400 dark:text-slate-500 text-[11px]">(Maks. 10 entri per halaman)</span>
                    </div>
                    <div class="flex items-center gap-1 flex-wrap">
                        <template v-for="(link, idx) in activities.links" :key="idx">
                            <button v-if="link.url"
                                @click="router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                                v-html="link.label"
                                :class="[
                                    'px-3 py-1.5 rounded-xl border text-xs font-semibold transition',
                                    link.active
                                        ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-600/30'
                                        : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700'
                                ]"></button>
                            <span v-else
                                v-html="link.label"
                                class="px-3 py-1.5 rounded-xl border border-transparent text-xs text-slate-400 dark:text-slate-600 font-medium"></span>
                        </template>
                    </div>
                </div>
            </div>
        </div>



        <!-- ===================================== -->
        <!-- MODAL DETAIL AKTIVITAS (PREVIEW) -->
        <!-- ===================================== -->
        <div v-if="showDetailModal && previewActivity" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 max-h-[90vh] overflow-y-auto animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div>
                        <h4 class="font-bold text-base text-slate-900 dark:text-slate-100">Detail Aktivitas</h4>
                        <p class="text-xs text-slate-400">{{ formatDateIndo(previewActivity.tanggal) }} • {{ previewActivity.jam_mulai }} - {{ previewActivity.jam_selesai }} WIB</p>
                    </div>
                    <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Kategori:</span>
                            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ previewActivity.kategori }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase">Status:</span>
                            <div class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ previewActivity.status_validasi }}</div>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Lokasi / Unit:</span>
                        <div class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
                            {{ previewActivity.lokasi || '-' }} {{ previewActivity.nama_unit ? `(${previewActivity.nama_unit})` : '' }}
                        </div>
                    </div>

                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Uraian Tugas & Capaian:</span>
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 text-slate-800 dark:text-slate-200 whitespace-pre-line leading-relaxed">
                            {{ previewActivity.uraian }}
                        </div>
                    </div>

                    <!-- Lampiran Berkas / Bukti (Image, PDF, Excel) -->
                    <div v-if="previewActivity.lampiran">
                        <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1.5">Berkas Bukti / Lampiran:</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div v-for="(file, fIdx) in previewActivity.lampiran.split(',')" :key="fIdx"
                                class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/80 flex items-center justify-between gap-2 shadow-sm">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-base flex-shrink-0">{{ getFileIcon(file.trim()) }}</span>
                                    <div class="truncate">
                                        <div class="font-bold text-slate-800 dark:text-slate-200 truncate text-[11px]">{{ getFileLabel(file.trim(), fIdx) }}</div>
                                        <div class="text-[10px] text-slate-400 truncate">{{ file.trim() }}</div>
                                    </div>
                                </div>
                                <a :href="'/storage/lampiran_aktivitas/' + file.trim()" target="_blank"
                                    class="flex-shrink-0 px-2.5 py-1 text-[11px] font-bold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition">
                                    Buka ↗
                                </a>
                            </div>
                        </div>

                        <!-- Mini Image Gallery for Photo Attachments -->
                        <div class="flex flex-wrap gap-2 mt-2.5">
                            <template v-for="(file, fIdx) in previewActivity.lampiran.split(',')" :key="'thumb-'+fIdx">
                                <div v-if="getFileType(file.trim()) === 'image'"
                                    @click="openPhotoViewer('/storage/lampiran_aktivitas/' + file.trim(), file.trim(), getPhotoUrlsFromLampiran(previewActivity.lampiran), getPhotoIndexInLampiran(previewActivity.lampiran, file.trim()))"
                                    class="relative block w-20 h-20 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 shadow-sm hover:opacity-90 transition group cursor-pointer"
                                    title="Klik untuk memperbesar foto">
                                    <img :src="'/storage/lampiran_aktivitas/' + file.trim()" :alt="file.trim()" class="w-full h-full object-cover" />
                                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-[10px] font-bold transition">
                                        Lihat Foto 🔍
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div v-if="previewActivity.catatan" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-rose-800 dark:text-rose-200">
                        <strong>Catatan Verifikator:</strong> {{ previewActivity.catatan }}
                    </div>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button @click="showDetailModal = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-500 hover:bg-slate-100 rounded-xl cursor-pointer">
                        Tutup
                    </button>
                    <!-- Quick action edit for PJLP if Ditolak or Menunggu -->
                    <div v-if="isOwner && (previewActivity.status_validasi === 'Ditolak' || previewActivity.status_validasi === 'Menunggu')" class="flex items-center gap-2">
                        <button @click="showDetailModal = false; openEditModal(previewActivity)" type="button"
                            class="px-4 py-2 text-xs font-bold text-white rounded-xl shadow-sm transition inline-flex items-center gap-1.5 cursor-pointer"
                            :class="previewActivity.status_validasi === 'Ditolak' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-amber-600 hover:bg-amber-700'">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span>{{ previewActivity.status_validasi === 'Ditolak' ? 'Perbaiki (Edit) Aktivitas' : 'Edit Aktivitas' }}</span>
                        </button>
                    </div>
                    <!-- Quick actions in detail modal for Admin/Kasi -->
                    <div v-else-if="!isOwner" class="flex items-center gap-2">
                        <template v-if="previewActivity.status_validasi === 'Menunggu'">
                            <button @click="openRejectPrompt(previewActivity)" type="button" class="px-3.5 py-2 text-xs font-bold bg-rose-600 text-white rounded-xl shadow-sm hover:bg-rose-500 cursor-pointer">
                                ✕ Tolak
                            </button>
                            <button @click="quickValidateSingle(previewActivity, 'Disetujui')" type="button" class="px-4 py-2 text-xs font-bold bg-emerald-600 text-white rounded-xl shadow-sm hover:bg-emerald-500 cursor-pointer">
                                ✓ Setujui
                            </button>
                        </template>
                        <span v-else-if="previewActivity.status_validasi === 'Disetujui'" class="text-xs font-bold text-emerald-600 flex items-center gap-1">
                            ✓ Telah Disetujui (Terkunci)
                        </span>
                        <span v-else-if="previewActivity.status_validasi === 'Ditolak'" class="text-xs font-bold text-rose-600 flex items-center gap-1">
                            ✕ Telah Ditolak (Menunggu Revisi PJLP)
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================================== -->
        <!-- MODAL REASON TOLAK (TASK 16) -->
        <!-- ===================================== -->
        <div v-if="showRejectModal && rejectingActivity" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between">
                    <h4 class="font-bold text-base text-rose-600 flex items-center gap-2">
                        <span>Alasan Penolakan Aktivitas</span>
                    </h4>
                    <button @click="showRejectModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>
                <p class="text-xs text-slate-500">
                    Berikan catatan perbaikan agar pegawai dapat melengkapi atau merevisi laporan pekerjaannya.
                </p>

                <!-- Quick Presets -->
                <div class="space-y-1.5 text-xs">
                    <label class="text-[10px] font-bold text-slate-400 uppercase">Pilih Template Alasan:</label>
                    <div class="space-y-1">
                        <button v-for="preset in [
                            'Dokumentasi bukti/lampiran foto belum dilampirkan',
                            'Uraian tugas tidak memenuhi standar KAK penugasan',
                            'Jam pelaksanaan tumpang tindih dengan agenda penugasan lain',
                            'Volume hasil pekerjaan tidak sesuai dengan realisasi lapangan',
                        ]" :key="preset"
                            @click="rejectPreset = preset; rejectionNote = preset"
                            type="button"
                            class="block w-full text-left px-3 py-1.5 rounded-xl border text-xs transition"
                            :class="rejectionNote === preset ? 'border-rose-500 bg-rose-50 text-rose-700 font-bold dark:bg-rose-950/60' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300'">
                            {{ preset }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Catatan Tambahan:</label>
                    <textarea v-model="rejectionNote" rows="3" placeholder="Tuliskan catatan detail penolakan..."
                        class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:ring-rose-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button @click="showRejectModal = false" type="button" class="px-4 py-2 text-xs font-bold text-slate-500 hover:bg-slate-100 rounded-xl">
                        Batal
                    </button>
                    <button @click="confirmReject" type="button" class="px-4 py-2 text-xs font-bold bg-rose-600 text-white hover:bg-rose-500 rounded-xl shadow-sm">
                        Konfirmasi Tolak
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- LIGHTBOX / MODAL PHOTO VIEWER -->
        <!-- ============================================================ -->
        <div v-if="showPhotoViewer"
            class="fixed inset-0 z-[100] flex flex-col items-center justify-center p-3 sm:p-6 bg-black/90 backdrop-blur-md animate-in fade-in duration-200"
            @click.self="closePhotoViewer">
            <!-- Header Lightbox -->
            <div class="w-full max-w-5xl flex items-center justify-between text-white pb-3 px-2">
                <div class="flex items-center gap-3">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-white/20 text-white">
                        Foto {{ (activePhotoViewer.index || 0) + 1 }} dari {{ activePhotoViewer.list?.length || 1 }}
                    </span>
                    <span class="text-xs text-slate-300 truncate max-w-xs sm:max-w-md">{{ activePhotoViewer.title }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <a :href="activePhotoViewer.url" target="_blank" download
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition">
                        <span>Unduh / Buka ↗</span>
                    </a>
                    <button @click="closePhotoViewer" type="button"
                        class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-sm font-bold transition">
                        ✕
                    </button>
                </div>
            </div>

            <!-- Container Foto & Navigasi -->
            <div class="relative w-full max-w-5xl flex-1 flex items-center justify-center min-h-0">
                <!-- Tombol Prev -->
                <button v-if="activePhotoViewer.list?.length > 1" @click.stop="prevPhotoViewer" type="button"
                    class="absolute left-2 sm:left-4 z-10 w-11 h-11 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center text-xl font-bold shadow-lg transition"
                    title="Foto Sebelumnya">
                    ‹
                </button>

                <!-- Foto Utama -->
                <img :src="activePhotoViewer.url" :alt="activePhotoViewer.title"
                    class="max-h-[80vh] max-w-full rounded-2xl object-contain shadow-2xl transition duration-200" />

                <!-- Tombol Next -->
                <button v-if="activePhotoViewer.list?.length > 1" @click.stop="nextPhotoViewer" type="button"
                    class="absolute right-2 sm:right-4 z-10 w-11 h-11 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center text-xl font-bold shadow-lg transition"
                    title="Foto Selanjutnya">
                    ›
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
