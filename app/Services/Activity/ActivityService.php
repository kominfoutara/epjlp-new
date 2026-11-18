<?php

namespace App\Services\Activity;

use App\Models\Activity;
use App\Models\MonthlyActivityReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActivityService
{
    /**
     * Simpan aktivitas harian baru beserta lampirannya.
     */
    public function createActivity(User $user, array $data, array $lampiranFiles = []): Activity
    {
        $storedLampiran = $this->handleUploads($lampiranFiles);

        return Activity::create([
            'user_id' => $user->id,
            'no' => $data['no'] ?? null,
            'tanggal' => $data['tanggal'] ?? Carbon::today()->toDateString(),
            'jam_mulai' => $data['jam_mulai'],
            'jam_selesai' => $data['jam_selesai'],
            'kategori' => $data['kategori'],
            'lokasi' => $data['lokasi'] ?? null,
            'nama_unit' => $data['nama_unit'] ?? null,
            'uraian' => $data['uraian'],
            'lampiran' => !empty($storedLampiran) ? implode(',', $storedLampiran) : null,
            'status_validasi' => 'Menunggu',
            'catatan' => null,
        ]);
    }

    /**
     * Perbarui data aktivitas yang ada.
     */
    public function updateActivity(Activity $activity, array $data, array $newFiles = [], array $deletedFiles = []): Activity
    {
        // Handle lampiran lama
        $currentLampiran = !empty($activity->lampiran) ? explode(',', $activity->lampiran) : [];

        // Hapus file yang dipilih untuk dibuang
        if (!empty($deletedFiles)) {
            $currentLampiran = array_filter($currentLampiran, function ($file) use ($deletedFiles) {
                if (in_array($file, $deletedFiles)) {
                    Storage::disk('public')->delete('lampiran_aktivitas/' . $file);
                    return false;
                }
                return true;
            });
        }

        // Upload file baru jika ada
        if (!empty($newFiles)) {
            $uploaded = $this->handleUploads($newFiles);
            $currentLampiran = array_merge($currentLampiran, $uploaded);
        }

        // Ambil maksimal 6 lampiran (maks. 5 foto dan 1 file PDF)
        $currentLampiran = array_slice($currentLampiran, 0, 6);

        $activity->update([
            'no' => $data['no'] ?? $activity->no,
            'tanggal' => $data['tanggal'] ?? $activity->tanggal,
            'jam_mulai' => $data['jam_mulai'] ?? $activity->jam_mulai,
            'jam_selesai' => $data['jam_selesai'] ?? $activity->jam_selesai,
            'kategori' => $data['kategori'] ?? $activity->kategori,
            'lokasi' => array_key_exists('lokasi', $data) ? $data['lokasi'] : $activity->lokasi,
            'nama_unit' => $data['nama_unit'] ?? $activity->nama_unit,
            'uraian' => $data['uraian'] ?? $activity->uraian,
            'lampiran' => !empty($currentLampiran) ? implode(',', $currentLampiran) : null,
            // Jika diedit oleh user dan sebelumnya ditolak, kembalikan ke Menunggu
            'status_validasi' => 'Menunggu',
            'catatan' => null,
        ]);

        return $activity;
    }

    /**
     * Hapus aktivitas beserta seluruh lampirannya.
     */
    public function deleteActivity(Activity $activity): bool
    {
        if (!empty($activity->lampiran)) {
            $files = explode(',', $activity->lampiran);
            foreach ($files as $file) {
                Storage::disk('public')->delete('lampiran_aktivitas/' . trim($file));
            }
        }

        return $activity->delete();
    }

    /**
     * Validasi aktivitas satuan oleh Kasi/Admin.
     */
    public function validateActivity(Activity $activity, string $status, ?string $catatan = null): bool
    {
        return $activity->update([
            'status_validasi' => $status,
            'catatan' => $catatan,
        ]);
    }

    /**
     * Validasi massal (bulk approval) aktivitas oleh Kasi/Admin.
     */
    public function validateBulk(array $activityIds, string $status, ?string $catatan = null): int
    {
        return Activity::whereIn('id', $activityIds)->update([
            'status_validasi' => $status,
            'catatan' => $catatan,
        ]);
    }

    /**
     * Simpan analisa kesimpulan & rekomendasi laporan bulanan PJLP.
     */
    public function saveMonthlyReport(User $user, string $periode, ?string $analisa, ?string $rekomendasi): MonthlyActivityReport
    {
        return MonthlyActivityReport::updateOrCreate(
            [
                'user_id' => $user->id,
                'periode' => $periode,
            ],
            [
                'analisa_kesimpulan' => $analisa,
                'rekomendasi' => $rekomendasi,
            ]
        );
    }

    /**
     * Simpan file lampiran ke disk storage public (auto-kompres foto jika > 5MB).
     */
    protected function handleUploads(array $files): array
    {
        $saved = [];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'avif', 'heic', 'heif', 'tiff', 'jfif', 'ico'];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $ext = strtolower($file->getClientOriginalExtension());
                $filename = uniqid('akt_', true) . '.' . $ext;
                $mime = $file->getMimeType() ?? '';
                $isImage = in_array($ext, $imageExtensions) || str_starts_with($mime, 'image/');

                // Jika file gambar berukuran > 5MB, kompresi otomatis di server
                if ($isImage && $file->getSize() > 5 * 1024 * 1024 && extension_loaded('gd')) {
                    $this->compressAndStoreImage($file, $filename);
                } else {
                    $file->storeAs('lampiran_aktivitas', $filename, 'public');
                }

                $saved[] = $filename;
            }
        }
        return $saved;
    }

    /**
     * Kompresi gambar besar (> 5MB) dan simpan ke disk public.
     */
    protected function compressAndStoreImage(UploadedFile $file, string $filename): void
    {
        $sourcePath = $file->getRealPath();
        $ext = strtolower($file->getClientOriginalExtension());

        $image = match ($ext) {
            'jpg', 'jpeg', 'jfif' => @imagecreatefromjpeg($sourcePath),
            'png' => @imagecreatefrompng($sourcePath),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            'bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($sourcePath) : false,
            'gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($sourcePath) : false,
            default => false,
        };

        if (!$image) {
            $file->storeAs('lampiran_aktivitas', $filename, 'public');
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Resize jika dimensi sangat besar (> 1920px)
        $maxDimension = 1920;
        if ($width > $maxDimension || $height > $maxDimension) {
            if ($width > $height) {
                $newWidth = $maxDimension;
                $newHeight = (int) round(($height * $maxDimension) / $width);
            } else {
                $newHeight = $maxDimension;
                $newWidth = (int) round(($width * $maxDimension) / $height);
            }

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($ext === 'png' || $ext === 'webp') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        $targetDir = Storage::disk('public')->path('lampiran_aktivitas');
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        // Simpan dengan kualitas kompresi optimal
        if ($ext === 'png') {
            imagepng($image, $targetPath, 8);
        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
            imagewebp($image, $targetPath, 75);
        } else {
            imagejpeg($image, $targetPath, 75);
        }

        imagedestroy($image);
    }
}
