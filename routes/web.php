<?php

use App\Http\Controllers\ActivityCategoryController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityDownloadController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ActivityReminderController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DinasLuarController;
use App\Http\Controllers\IssueReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PjlpManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SeksiController;
use App\Http\Controllers\TemporaryUserController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profil Pengguna
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Modul Absensi
    Route::get('/absensi', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/absensi/rekap', [AttendanceController::class, 'recap'])->name('attendance.recap');
    Route::get('/absensi/realtime-feed', [AttendanceController::class, 'realtimeFeed'])->name('attendance.realtime-feed');
    Route::post('/absensi/presensi-mandiri', [AttendanceController::class, 'presensiMandiri'])->name('attendance.presensi-mandiri');
    Route::post('/absensi/sync', [AttendanceController::class, 'sync'])->middleware('throttle:10,1')->name('attendance.sync');
    Route::post('/absensi/test-connection', [AttendanceController::class, 'testConnection'])->middleware('throttle:10,1')->name('attendance.test-connection');
    Route::patch('/absensi/{attendance}/keterangan', [AttendanceController::class, 'updateKeterangan'])->name('attendance.update-keterangan');
    Route::get('/absensi/export/excel', [AttendanceController::class, 'exportDailyExcel'])->name('attendance.export.excel');
    Route::get('/absensi/export/pdf', [AttendanceController::class, 'exportDailyPdf'])->name('attendance.export.pdf');
    Route::get('/absensi/rekap/export/excel', [AttendanceController::class, 'exportRecapExcel'])->name('attendance.rekap.export.excel');
    Route::get('/absensi/rekap/export/pdf', [AttendanceController::class, 'exportRecapPdf'])->name('attendance.rekap.export.pdf');

    // Modul Aktivitas Harian
    Route::get('/tarik-aktivitas', [ActivityDownloadController::class, 'index'])->name('activity-download.index');
    Route::get('/tarik-aktivitas/excel', [ActivityDownloadController::class, 'excel'])->name('activity-download.excel');
    Route::get('/reminder', [ActivityReminderController::class, 'index'])->name('reminder.index');
    Route::get('/aktivitas/export/excel', [ActivityController::class, 'exportExcel'])->name('activity.export.excel');
    Route::get('/aktivitas/export/pdf', [ActivityController::class, 'exportPdf'])->name('activity.export.pdf');
    Route::get('/aktivitas/lampiran/view/{filename}', [ActivityController::class, 'viewAttachment'])->name('activity.lampiran.view');
    Route::get('/aktivitas/kategori-list', [ActivityController::class, 'categoryList'])->name('activity.category-list');
    Route::get('/aktivitas/saya', [ActivityController::class, 'myActivities'])->name('activity.my');
    Route::get('/aktivitas', [ActivityController::class, 'index'])->name('activity.index');
    Route::post('/aktivitas', [ActivityController::class, 'store'])->name('activity.store');
    Route::post('/aktivitas/{activity}', [ActivityController::class, 'update'])->name('activity.update');
    Route::delete('/aktivitas/{activity}', [ActivityController::class, 'destroy'])->name('activity.destroy');
    Route::post('/aktivitas/{activity}/quick-validate', [ActivityController::class, 'quickValidate'])->name('activity.quick-validate');
    Route::post('/aktivitas-bulk/validate', [ActivityController::class, 'validateCheckbox'])->name('activity.validate-checkbox');
    Route::post('/aktivitas/monthly-report', [ActivityController::class, 'saveMonthlyReport'])->name('activity.save-monthly-report');

    // Modul Dinas Luar
    Route::get('/dinas-luar', [DinasLuarController::class, 'index'])->name('dinas-luar.index');
    Route::post('/dinas-luar', [DinasLuarController::class, 'store'])->name('dinas-luar.store');
    Route::patch('/dinas-luar/{dinasLuar}/verify', [DinasLuarController::class, 'verify'])->name('dinas-luar.verify');
    Route::post('/dinas-luar/verify-bulk', [DinasLuarController::class, 'verifyBulk'])->name('dinas-luar.verify-bulk');

    // Manajemen PJLP & Kategori (Admin & Kasi)
    Route::resource('pjlp', PjlpManagementController::class)->except(['create', 'edit', 'show']);
    Route::resource('categories', ActivityCategoryController::class)->except(['create', 'edit', 'show']);

    // Master Seksi (Superadmin)
    Route::resource('seksi', SeksiController::class)->except(['create', 'edit', 'show']);
    Route::patch('seksi/{seksi}/toggle', [SeksiController::class, 'toggleStatus'])->name('seksi.toggle');

    // Manajemen Pengguna Lengkap (Superadmin)
    Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);
    Route::match(['post', 'patch'], 'users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    // Notifikasi Sistem (Real-time Polling & Dropdown)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Audit Trail / Log Aktivitas
    Route::get('/log-aktivitas', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    // Modul Laporan Kendala / Aduan IT (Semua Role: PJLP, Kasi, Kasudin, Superadmin)
    Route::get('/laporan-kendala', [IssueReportController::class, 'index'])->name('issue-reports.index');
    Route::post('/laporan-kendala', [IssueReportController::class, 'store'])->name('issue-reports.store');
    Route::patch('/laporan-kendala/{issueReport}', [IssueReportController::class, 'update'])->name('issue-reports.update');
    Route::delete('/laporan-kendala/{issueReport}', [IssueReportController::class, 'destroy'])->name('issue-reports.destroy');

    // Modul User Sementara & Pengajuan Pendaftaran (Superadmin)
    Route::get('/user-sementara', [TemporaryUserController::class, 'index'])->name('temporary-users.index');
    Route::post('/user-sementara', [TemporaryUserController::class, 'store'])->name('temporary-users.store');
    Route::delete('/user-sementara/{user}', [TemporaryUserController::class, 'destroy'])->name('temporary-users.destroy');
    Route::post('/user-sementara/submissions/{submission}/approve', [TemporaryUserController::class, 'approveSubmission'])->name('temporary-users.approve');
    Route::post('/user-sementara/submissions/{submission}/reject', [TemporaryUserController::class, 'rejectSubmission'])->name('temporary-users.reject');

    // Formulir Pendaftaran Calon Pegawai (Khusus User Sementara yang Login)
    Route::get('/pendaftaran', [RegistrationController::class, 'index'])->name('pendaftaran.index');
    Route::post('/pendaftaran', [RegistrationController::class, 'store'])->name('pendaftaran.store');
});

// Halaman Sukses Pendaftaran (Dapat diakses publik setelah akun sementara dihapus)
Route::get('/pendaftaran/sukses', [RegistrationController::class, 'sukses'])->name('pendaftaran.sukses');

// Halaman Cek Status Pendaftaran Publik (Dapat diakses kapan saja oleh calon pegawai)
Route::get('/pendaftaran/status', [RegistrationController::class, 'status'])->name('pendaftaran.status');

require __DIR__.'/auth.php';
