<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Restoring missing PHP dependencies

If Artisan reports that `vendor/autoload.php` is missing, restore the versions in
`composer.lock` from the project directory before running Artisan:

```powershell
composer install --no-scripts --no-plugins --no-interaction --prefer-dist
```

This generates the Composer autoloader without running project hooks. Do not use
`composer update` for this error. The `composer setup` script also generates an
application key and runs migrations, so it is not a dependency-only repair.
Preserve the existing `.env` and database.

PHP must satisfy the locked packages' extension requirements, including ZIP for
PhpSpreadsheet. Use `php --ini` to locate the CLI configuration. If `php_zip.dll`
is installed but disabled, enable `extension=zip` in the appropriate PHP
configuration as a separate environment change. Alternatively, enable it for an
individual command without changing configuration:

```powershell
# Example for a Herd Lite Composer installation on Windows:
php -d extension=zip "$env:USERPROFILE\.config\herd-lite\bin\composer.phar" install --no-scripts --no-plugins --no-interaction --prefer-dist
```

The command-level setting does not enable ZIP for subsequent commands or the web
server. Ensure each PHP runtime used by the application has ZIP enabled before
using spreadsheet features. Installation with scripts disabled skips Laravel
package discovery hooks; application startup and business flows still require
manual verification. Dependency restoration does not run tests or migrations.

## Reminder aktivitas bulanan

Menu **Reminder Aktivitas** di navigasi desktop/mobile membuka `GET /reminder`
(`reminder.index`). Contoh: `/reminder?bulan=2026-11&tanggal=2026-11-18`.
Parameter opsional `bulan` harus berformat `Y-m`, `tanggal` harus `Y-m-d`
dan berada dalam bulan terpilih. Default bulan/tanggal mengikuti Asia/Jakarta;
bulan lain memilih tanggal pertama. Parameter tidak valid ditolak.

- Superadmin memantau aktivitas PJLP seluruh seksi dalam kalender baca-saja.
- Kasi hanya melihat PJLP dengan seksi yang sama menurut normalisasi nama
  seksi aplikasi. Seksi kosong tidak memberikan akses. Aktivitas Kasi sendiri
  tidak masuk kalender ini.
- PJLP hanya melihat aktivitas sendiri: hijau jika ada aktivitas, kuning jika
  belum ada sampai hari ini, abu-abu untuk tanggal mendatang yang masih kosong.
- Kasudin tetap mendapat akses pemantauan PJLP seluruh seksi.

Satu node mewakili satu PJLP per tanggal dengan jumlah aktivitasnya. Semua status
validasi dihitung berdasarkan kolom `aktivitas.tanggal`, bukan waktu pembuatan.
Kalender menampilkan maksimal tiga node per sel dan jumlah PJLP lainnya; klik
tanggal untuk melihat semua rincian melalui paginasi 20 aktivitas per halaman.
Pergantian bulan/tanggal mengembalikan paginasi ke halaman pertama.
Tanggal kosong termasuk akhir pekan/libur dan tanggal sebelum mulai bekerja;
ini indikator ketersediaan catatan, bukan penilaian kewajiban kerja/kehadiran.
Data mengikuti role dan seksi pemilik saat ini, karena tidak ada snapshot seksi
historis pada aktivitas. Akun PJLP nonaktif yang masih memiliki catatan tetap
tercakup. Tidak ada aksi edit, hapus, atau validasi pada halaman reminder.

Implementasi: `ActivityReminderController`, `Activity/ReminderCalendar.vue`,
route `reminder.index`, dan navigasi `AuthenticatedLayout.vue`. Kalender memakai
agregasi bulanan dan rincian harian; tidak membutuhkan migrasi, dependency,
atau perubahan `.env`. Build aset frontend seperti biasa saat deployment.
Verifikasi manual yang masih diperlukan: login tiap role, isolasi seksi/PJLP,
bulan kosong, pergantian tahun, node berlebih, paginasi, serta tampilan mobile.
Tes otomatis tidak dijalankan sesuai kebijakan proyek.

## Konsistensi absensi harian dan riwayat scan

Halaman `Attendance/Index.vue` dan endpoint `AttendanceController::realtimeFeed`
membaca scan dari tabel `absensi` berdasarkan PIN pegawai dan tanggal terpilih.
Polling 12 detik memuat ulang database lokal, bukan menarik log mesin.
Scan masuk/keluar kosong ditampilkan `--:--:--`; data tidak direkayasa ketika
belum tersedia. Pembuatan jam acak melalui `ensureTodayAttendance` dihapus dari
controller dan `AttendanceService`; membuka halaman/polling tidak lagi membuat
catatan absensi otomatis. Presensi mandiri dan sinkronisasi eksplisit tetap ada.

Polling dibatalkan saat navigasi/filter berubah dan komponen dilepas. Respons
lama diabaikan berdasarkan versi permintaan serta konteks filter/halaman;
permintaan memakai filter yang sudah diterapkan server, bukan pencarian yang
masih diketik. Kegagalan polling ditampilkan pada tampilan admin tanpa menghapus
data terakhir. Filter server juga disalin kembali ke kontrol setelah navigasi.
Tanggal harian divalidasi sebagai `Y-m-d`; bulan PJLP sebagai `Y-m`.

Contoh: pilih 2026-10-15 lalu buka **Tarik Mesin**. Dialog memilih Oktober 2026.
Jika sinkronisasi dijalankan pengguna untuk periode tersebut, tanggal 15 tetap
terpilih, termasuk bila tidak ada scan untuk tanggal itu. Parameter opsional
`tanggal` pada POST `/absensi/sync` mempertahankan tanggal dalam bulan yang
ditarik. Pemanggil lama tanpa parameter ini dan halaman rekap tetap memakai
perilaku sebelumnya. Tidak ada migrasi atau perubahan konfigurasi.

Perubahan kode tidak mengisi ulang atau menghapus data lama. Catatan yang mungkin
pernah dibuat generator acak tidak memiliki penanda sumber sehingga harus
diaudit terhadap log asli sebelum koreksi. Sinkronisasi yang ada menggabungkan
scan masuk paling awal dan pulang paling akhir; sinkronisasi ulang saja tidak
menjamin catatan yang sebelumnya keliru akan terkoreksi.
Scan historis kosong masih perlu dicocokkan dengan PIN terdaftar, tanggal,
data tersimpan, dan log asli mesin. Ketersediaan riwayat mesin belum diverifikasi.

Verifikasi manual: ganti tanggal saat pembaruan sedang berjalan, pindah halaman,
kembali lewat riwayat browser, pilih tanggal tanpa scan, dan periksa periode
dialog tarik mesin. Tes otomatis dan sinkronisasi/database writes tidak
dijalankan saat perbaikan ini; pemeriksaan dilakukan secara statis.

## Jumlah pegawai pada Master Seksi

`SeksiController::index` menghitung pengguna berdasarkan nama seksi yang
dinormalisasi dengan `User::normalizeSeksi`, tanpa membedakan huruf besar/kecil
dan dengan memangkas spasi luar. Contohnya `Astik` dan `Seksi ASTIK` masuk ke
master `Astik`; `TU`, `Tata Usaha`, dan `Subbagian Tata Usaha` masuk ke master
`Tata Usaha`. Pencocokan memakai nama normalisasi utuh, bukan substring.
Seksi null/kosong tidak dihitung. Kelompok tanpa master yang cocok tidak muncul.

Kolom Pegawai tetap menghitung semua role dan status pengguna, termasuk akun
nonaktif, sesuai cakupan sebelumnya. `users_count` adalah total pengguna;
`pjlp_count` adalah bagian dengan role `PJLP`. `Seksi/Index.vue` menampilkan
total serta rincian PJLP/lainnya: 2 PJLP dan 1 Kasi berarti 3 orang, 2 PJLP,
1 lainnya. Filter/paginasi master tidak membatasi populasi pengguna yang dihitung.

Perubahan hanya pada agregasi tampilan; nama seksi tersimpan dan relasi model
`Seksi::users()` tidak diubah. Tidak perlu migrasi atau koreksi data. Jika ada
dua master dengan nama normalisasi identik, keduanya akan menunjukkan hitungan
yang sama; penyatuan master tidak dilakukan otomatis. Review statis mencakup
alias, huruf besar/kecil, seksi kosong, jumlah nol, role, dan paginasi.
Tes otomatis tidak dijalankan; tampilan browser masih perlu verifikasi manual.

### Struktur seksi dan kehadiran pada dashboard Superadmin

`DashboardController::dashboardSuperadmin` mengelompokkan pengguna dengan aturan
normalisasi yang sama seperti Master Seksi, termasuk untuk ringkasan ID/Astik/KIP,
alokasi PJLP, dan pencarian Kasi. `Astik` dan `Seksi ASTIK` masuk kelompok yang
sama; nama seksi kosong tidak dipasangkan dengan master. Alokasi menghitung
semua akun PJLP terdaftar (termasuk nonaktif), sama dengan rincian PJLP pada
Master Seksi; total Pegawai di master juga mencakup role lain.

Contoh: 2 PJLP dan 1 Kasi pada variasi nama Astik menghasilkan alokasi 2 PJLP
dan nama Kasi yang terdaftar. Pengawas tetap berasal dari role Kasi; tidak
menganggap akun Superadmin/Kasudin sebagai Kasi. Bila lebih dari satu Kasi,
ditampilkan akun pertama berdasarkan ID. Seksi tanpa Kasi menampilkan
`Belum Ditugaskan`.

Hadir pada tabel struktur dihitung sekali per PJLP yang memiliki scan masuk
atau scan keluar pada tanggal hari ini dari database lokal. Catatan tanpa scan
(termasuk izin/cuti/DL tanpa scan) tidak otomatis dianggap hadir pada indikator
ini. Persentase memakai jumlah hadir dibagi alokasi PJLP; alokasi nol menjadi
0%. Indikator ini berbeda dari kategori keterangan pada kartu ringkasan presensi.
Status aktif/nonaktif pada tabel dan kartu mobile mengikuti `seksi.is_active`.
Data dimuat saat dashboard dibuka/dimuat ulang; tidak menarik mesin otomatis.
Tidak ada perubahan database atau migrasi. Pemeriksaan sintaks/kompilasi saja;
tes otomatis dan verifikasi tampilan browser tidak dijalankan.

### Dashboard eksekutif Superadmin

Dashboard Superadmin menerima filter opsional `periode=YYYY-MM`, contoh
`/dashboard?periode=2026-11`; format invalid ditolak. Default adalah bulan
berjalan. `ExecutiveAnalytics.vue` menyediakan empat kartu ringkasan, distribusi
kategori, tren 12 bulan pada tahun terpilih, diagram status validasi, Top 5
kategori, dan Top 5 seksi. Tiga tab lama (Struktur Satker & Kehadiran, Direktori
Akun & Hak Akses, Telemetri Server SPBE) tetap tersedia di bawah analitik.

Semua jumlah aktivitas menggunakan tanggal aktivitas dan seluruh role, sesuai
cakupan rekap Superadmin sebelumnya. Kategori kosong menjadi `Tanpa kategori`;
status di luar Disetujui/Ditolak/Menunggu masuk `Lainnya`. Tren adalah jumlah
masing-masing bulan, bukan kumulatif; bulan tanpa data bernilai nol. Distribusi
menampilkan seluruh kategori dengan scroll, peringkat hanya lima terbesar.
Peringkat seksi memakai seksi pemilik saat ini yang dinormalisasi; aktivitas
tanpa pemilik/master seksi yang cocok tetap masuk total/kategori/status tetapi
tidak masuk peringkat seksi. Jumlah aktivitas bukan penilaian kualitas kerja.

Filter periode hanya memengaruhi analitik aktivitas dan tautan Audit Aktivitas.
Akun/struktur mengikuti data pengguna saat ini, presensi tetap hari ini dan
menghitung PJLP dengan scan masuk atau keluar. Pembagi memakai seluruh PJLP
terdaftar, termasuk nonaktif, konsisten dengan tabel struktur sebelumnya.
Pembaruan 30 detik mengambil ulang `superadminData` untuk periode URL saat ini;
tidak menyinkronkan mesin. Data kosong memiliki keadaan kosong dan persentase
nol, tanpa data contoh atau klaim sertifikasi SPBE.

Tidak ada dependensi baru, migrasi, atau konfigurasi yang diperlukan. Implementasi
di `DashboardController.php`, `Dashboard.vue`, dan `ExecutiveAnalytics.vue`.
Verifikasi manual yang diperlukan: pilihan bulan, bulan/tahun kosong, grafik
pada layar kecil, tautan audit sesuai periode, dan fungsi tiga tab lama. Tes
otomatis tidak dijalankan sesuai kebijakan proyek.

### Diagram validasi dashboard PJLP

Diagram PJLP dan rincian 12 bulan menghitung `Disetujui` serta status lama
`Diterima` sebagai Setuju. `Menunggu` dan `Ditolak` tetap dihitung masing-masing.
Perubahan hanya pada pembacaan statistik dashboard PJLP, tidak mengganti status
di database atau mengubah keputusan validasi. Contoh: 35 aktivitas berstatus
`Diterima` menghasilkan total 35, Setuju 35 (100%), Menunggu/Ditolak 0.

Status tidak dikenal/null tetap masuk total dan jumlah `lainnya`, ditampilkan
sebagai keterangan serta batang mini abu-abu. Total adalah Setuju + Menunggu +
Ditolak + Lainnya. Persentase menggunakan total periode (pembulatan satu desimal),
sehingga tiga batang bisa kurang dari 100% jika ada status lainnya. Periode
kosong menampilkan pesan dan nilai nol. Query tetap dibatasi pengguna login.
Filter tahun dibatasi 1900–9999; bulan menerima 1–12 atau `all`. Tidak perlu
migrasi, perubahan konfigurasi, atau sinkronisasi data.

File terkait: `DashboardController.php` dan `Dashboard.vue`. Review mencakup
status lama/baru, total tahunan/bulanan, status kosong, dan pembagi nol. Tes
otomatis tidak dijalankan. Verifikasi browser yang diperlukan: Januari dengan
data lama, semua bulan, pergantian tahun, dan periode tanpa aktivitas.

### Dashboard Kepala Seksi: statistik dari data tersimpan

`dashboardAdmin` membatasi Kasi pada PJLP aktif dengan nama seksi yang sama
setelah normalisasi. `Astik`/`Seksi ASTIK` cocok, sedangkan seksi kosong tidak
memberi akses seluruh pegawai. Pembatasan ID diterapkan pada statistik, daftar,
pilihan kategori, presensi, dan pengajuan DL. Parameter pegawai dari luar seksi
tidak dapat memperluas cakupan. Kasudin mempertahankan cakupan PJLP aktif lintas
seksi yang sudah ada.

KPI/donut mengikuti tahun, bulan, pegawai, kategori, dan pencarian; tren mengikuti
tahun, pegawai, kategori, dan pencarian untuk seluruh 12 bulan. Filter status
hanya membatasi tabel supaya distribusi seluruh status tetap terlihat. Contoh:
`/dashboard?tahun=2026&bulan=1&status=Disetujui` menampilkan ringkasan Januari,
tren tahunan, dan tabel aktivitas disetujui, termasuk status lama `Diterima`.
Status lain/null tetap dihitung dalam total dan ditampilkan sebagai `lainnya`.
Default tahun memakai tahun berjalan; tahun/bulan/status divalidasi.

Nilai contoh 4.460/4.264/196, rasio contoh, dan target KAK tetap telah dihapus.
Tanpa konfigurasi target resmi, dashboard menampilkan jumlah catatan, bukan
persentase pencapaian target. Batang nol tetap nol. Grafik batang memuat total,
diterima, menunggu, ditolak, dan lainnya; donut memuat diterima, menunggu,
ditolak, dengan sisa abu-abu untuk lainnya. Periode kosong menampilkan pesan.

Presensi tetap hari ini untuk seluruh PJLP aktif dalam cakupan seksi; WFO
memerlukan scan masuk atau keluar. DL hanya mencakup DL AWAL/AKHIR/PENUH,
bukan DL DITOLAK. Jumlah pengajuan DL menunggu menghitung semua pengajuan,
meskipun daftar ringkas dibatasi 10. Pembaruan berkala memuat database lokal
beserta daftar pegawai/pengajuan, tanpa menarik log mesin atau memverifikasi
aktivitas otomatis.

Perubahan: `DashboardController.php`, `Dashboard.vue`, dan aset frontend hasil
build. Tidak ada migrasi atau perubahan konfigurasi/database. Review statis
mencakup isolasi seksi, status lama, pencarian, nol, dan paginasi; sintaks PHP
serta build frontend diperiksa. Tes otomatis tidak dijalankan. Verifikasi manual
masih diperlukan dengan akun Kasi untuk filter, diagram, dan tampilan mobile.

### Label status aktivitas pada dashboard

Badge tabel desktop, kartu mobile, feed aktivitas, dan modal detail di
`Dashboard.vue` mengenali `Diterima` dan `Disetujui` sebagai status diterima
(warna hijau), sambil mempertahankan teks status asli. Badge merah hanya
ditampilkan untuk nilai `Ditolak` secara eksplisit. Status lain ditampilkan
netral, dan status kosong diberi label `Status belum tersedia`; tidak lagi
jatuh ke fallback Ditolak. Tombol keputusan tetap hanya untuk `Menunggu`.
Perubahan ini hanya presentasi, tanpa menulis ulang status database.
Review mencakup desktop/mobile, detail, status lama/baru, dan null. Build
frontend diperiksa; tes otomatis tidak dijalankan dan verifikasi browser
masih diperlukan.

### Tarik Data Aktivitas PJLP

Menu **Tarik Data Aktivitas PJLP** membuka `GET /tarik-aktivitas` untuk Kasi,
Kasudin, dan Superadmin. Filter `mode=month&bulan=2026-11` memilih seluruh
November; `mode=range&start_date=2026-11-01&end_date=2026-11-30` memilih
rentang inklusif. Default adalah bulan berjalan menurut Asia/Jakarta. Filter
opsional: `seksi`, `user_id`, `search`, dan `per_page` (10/25/50/100).
Tanggal terbalik/format tidak valid ditolak. Ringkasan, pratinjau, dan Excel
memakai query serta cakupan akses yang sama.

Kasi hanya dapat melihat PJLP pada seksinya berdasarkan nama seksi yang
dinormalisasi (`Astik` sama dengan `Seksi ASTIK`). Kasi tanpa seksi tidak
mendapatkan data; parameter seksi/PJLP di luar cakupan ditolak 403. Kasudin dan
Superadmin dapat melihat seluruh PJLP semua seksi atau memilih seksi/PJLP.
PJLP tidak dapat membuka halaman maupun endpoint Excel. PJLP nonaktif tetap
disertakan agar aktivitas historisnya tidak hilang. Aktivitas Kasi dan role
lain tidak termasuk. Seluruh status validasi termasuk `Diterima` lama disertakan
dan ditulis sebagaimana tersimpan.

`GET /tarik-aktivitas/excel` mengunduh `.xlsx` berisi seluruh baris filter,
termasuk di luar halaman pratinjau. Kolom: nomor urut, tanggal, nomor aktivitas,
PJLP, seksi, kategori, uraian, lokasi, nama unit, jam mulai/selesai, status.
Pratinjau menampilkan tautan Lampiran 1–6 pada tiap aktivitas. Excel menambah
enam kolom lampiran; setiap sel berisi URL lengkap yang bisa diklik menuju
viewer lampiran aplikasi. Nama file diproses sebagai basename untuk mencegah
segmen path lain. Aktivitas tanpa lampiran menyisakan kolom kosong. Tautan
memerlukan login, dan file yang sudah tidak ada akan menghasilkan 404 seperti
viewer yang berlaku. Link yang dibagikan di luar sesi login tidak membuka file.
Teks ditulis sebagai string untuk menjaga nol awal dan mencegah rumus Excel
dari input pengguna. Di atas batas 1.048.575 data, permintaan ditolak agar
tidak melewati batas baris sheet; pilih rentang lebih kecil. Workbook dibangun
di memori sehingga rentang sangat besar masih perlu perhatian kapasitas server.

Implementasi: `ActivityDownloadController.php`, `Activity/Download.vue`,
`routes/web.php`, dan `AuthenticatedLayout.vue`. Memakai PhpSpreadsheet yang
sudah ada; tidak perlu migrasi, seeder, atau perubahan `.env`. Cek manual yang
masih diperlukan: login ketiga role, isolasi Kasi, rentang lintas bulan,
pratinjau/paginasi, dan unduhan Excel. Tes otomatis tidak dijalankan.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
