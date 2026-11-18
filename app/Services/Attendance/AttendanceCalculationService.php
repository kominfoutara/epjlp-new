<?php

namespace App\Services\Attendance;

use Carbon\Carbon;

class AttendanceCalculationService
{
    /**
     * Hitung keterlambatan berdasarkan jam masuk aktual dan jadwal masuk.
     * Toleransi default sampai jam 07:30:00.
     */
    public function calculateLate(string $actualTime, string $scheduleTime = '07:30:00'): ?string
    {
        $actual = Carbon::createFromTimeString($actualTime);
        $schedule = Carbon::createFromTimeString($scheduleTime);

        if ($actual->greaterThan($schedule)) {
            $diffInSeconds = $actual->diffInSeconds($schedule);
            return gmdate('H:i:s', $diffInSeconds);
        }

        return '00:00:00';
    }

    /**
     * Hitung pulang cepat berdasarkan jam pulang aktual dan jadwal pulang.
     * Jadwal standar pulang: 16:00:00.
     */
    public function calculateEarlyLeave(string $actualTime, string $scheduleTime = '16:00:00'): ?string
    {
        $actual = Carbon::createFromTimeString($actualTime);
        $schedule = Carbon::createFromTimeString($scheduleTime);

        if ($actual->lessThan($schedule)) {
            $diffInSeconds = $schedule->diffInSeconds($actual);
            return gmdate('H:i:s', $diffInSeconds);
        }

        return '00:00:00';
    }

    /**
     * Hitung waktu kompensasi lembur / jam kerja ekstra.
     */
    public function calculateCompensation(string $actualOutTime, string $scheduleOutTime = '16:00:00'): ?string
    {
        $actual = Carbon::createFromTimeString($actualOutTime);
        $schedule = Carbon::createFromTimeString($scheduleOutTime);

        if ($actual->greaterThan($schedule)) {
            $diffInSeconds = $actual->diffInSeconds($schedule);
            return gmdate('H:i:s', $diffInSeconds);
        }

        return '00:00:00';
    }
}
