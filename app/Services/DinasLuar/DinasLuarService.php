<?php

namespace App\Services\DinasLuar;

use App\Models\Attendance;
use App\Models\DinasLuar;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DinasLuarService
{
    /**
     * Simpan pengajuan dinas luar baru (termasuk penyimpanan foto selfie).
     */
    public function submitDinasLuar(User $user, array $data, $photo = null): DinasLuar
    {
        $photoPath = null;

        if ($photo instanceof UploadedFile) {
            $allowedExtensions = ['jpeg', 'jpg', 'png', 'webp'];
            $extension = strtolower($photo->getClientOriginalExtension());
            if (!in_array($extension, $allowedExtensions)) {
                $extension = 'jpg';
            }
            $filename = uniqid('dl_', true) . '.' . $extension;
            $photoPath = $photo->storeAs('dinas_luar', $filename, 'public');
        } elseif (is_string($photo) && str_starts_with($photo, 'data:image')) {
            // Handle base64 image dari web camera dengan whitelist ekstensi ketat
            $imageParts = explode(';base64,', $photo);
            if (count($imageParts) === 2) {
                $imageTypeAux = explode('image/', $imageParts[0]);
                $rawExt = strtolower($imageTypeAux[1] ?? 'jpeg');
                $allowedExtensions = ['jpeg', 'jpg', 'png', 'webp'];
                $imageType = in_array($rawExt, $allowedExtensions) ? $rawExt : 'jpeg';

                $imageBase64 = base64_decode($imageParts[1]);
                if ($imageBase64 !== false) {
                    $fileName = 'dinas_luar/' . uniqid('dl_', true) . '.' . $imageType;
                    Storage::disk('public')->put($fileName, $imageBase64);
                    $photoPath = $fileName;
                }
            }
        }

        if (!$photoPath) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'foto' => 'Swafoto kehadiran lapangan wajib diambil dan gagal diproses. Silakan ambil foto ulang.'
            ]);
        }

        return DinasLuar::create([
            'user_id' => $user->id,
            'tipe' => $data['tipe'],
            'keterangan' => $data['keterangan'] ?? null,
            'status_verifikasi' => 'Menunggu',
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'alamat' => $data['alamat'] ?? null,
            'foto' => $photoPath,
            'waktu' => $data['waktu'] ?? Carbon::now(),
        ]);
    }

    /**
     * Verifikasi pengajuan dinas luar oleh Kasi atau Admin.
     */
    public function verify(DinasLuar $dl, string $status, User $verifier): bool
    {
        DB::beginTransaction();
        try {
            $dl->update([
                'status_verifikasi' => $status,
                'verified_by' => $verifier->id,
                'verified_at' => Carbon::now(),
            ]);

            // Jika disetujui, update status kehadiran di tabel absensi
            if ($status === 'Disetujui' && $dl->user && $dl->user->pin) {
                $date = Carbon::parse($dl->waktu)->toDateString();

                $attendance = Attendance::firstOrNew([
                    'pin' => $dl->user->pin,
                    'tanggal' => $date,
                ]);

                // Map tipe DL ke keterangan absensi
                $attendance->keterangan = match ($dl->tipe) {
                    'DL AWAL' => 'DL AWAL',
                    'DL AKHIR' => 'DL AKHIR',
                    'DL PENUH' => 'DL PENUH',
                    default => 'DL PENUH',
                };

                $attendance->save();
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Verifikasi massal untuk beberapa pengajuan dinas luar sekaligus.
     */
    public function verifyBulk(array $ids, string $status, User $verifier): int
    {
        $dls = DinasLuar::whereIn('id', $ids)->get();
        $updated = 0;

        foreach ($dls as $dl) {
            $this->verify($dl, $status, $verifier);
            $updated++;
        }

        return $updated;
    }
}
