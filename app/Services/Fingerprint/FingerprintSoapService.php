<?php

namespace App\Services\Fingerprint;

use Exception;
use Illuminate\Support\Facades\Log;

class FingerprintSoapService
{
    protected string $ip;
    protected int $port;
    protected int $key;

    public function __construct()
    {
        $this->ip = (string) config('services.fingerprint.ip', env('FINGERPRINT_IP', env('ekin_ip_wilayah', '10.130.56.7')));
        $this->port = (int) config('services.fingerprint.port', env('FINGERPRINT_PORT', 80));
        $this->key = (int) config('services.fingerprint.key', env('FINGERPRINT_KEY', 101));
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getKey(): int
    {
        return $this->key;
    }

    /**
     * Uji koneksi ke mesin fingerprint.
     */
    public function testConnection(?string $ip = null): array
    {
        $targetIp = $ip ?: $this->ip;

        if (empty($targetIp)) {
            return [
                'success' => false,
                'message' => 'IP Mesin fingerprint belum dikonfigurasi di .env',
            ];
        }

        if (!filter_var($targetIp, FILTER_VALIDATE_IP) || $targetIp === '169.254.169.254') {
            return [
                'success' => false,
                'message' => 'Alamat IP mesin fingerprint tidak valid atau dilarang.',
            ];
        }

        $connect = @fsockopen($targetIp, $this->port, $errno, $errstr, 3);
        if (!$connect) {
            $errorMsg = trim($errstr . ($errno ? " ({$errno})" : ''));
            return [
                'success' => false,
                'message' => "Gagal terhubung ke mesin absensi {$targetIp}:{$this->port}. " . ($errorMsg ?: 'Koneksi timeout.'),
            ];
        }

        // Kirim request tes GetDate ke mesin
        $testRequest = "<GetDate><ArgComKey xsi:type=\"xsd:integer\">{$this->key}</ArgComKey></GetDate>";
        $newLine = "\r\n";
        fputs($connect, "POST /iWsService HTTP/1.0" . $newLine);
        fputs($connect, "Content-Type: text/xml" . $newLine);
        fputs($connect, "Content-Length: " . strlen($testRequest) . $newLine . $newLine);
        fputs($connect, $testRequest . $newLine);

        $response = '';
        while (!feof($connect)) {
            $chunk = fgets($connect, 1024);
            if ($chunk === false) {
                break;
            }
            $response .= $chunk;
        }
        fclose($connect);

        return [
            'success' => true,
            'message' => "Koneksi ke mesin fingerprint ({$targetIp}:{$this->port}) berhasil!",
        ];
    }

    /**
     * Tarik log absensi mentah dari mesin via SOAP/XML iWsService.
     * Mengembalikan array associative: [['pin' => '...', 'datetime' => '...', 'status' => '...'], ...]
     */
    public function fetchAttendanceLogs(?string $ip = null): array
    {
        $targetIp = $ip ?: $this->ip;

        if (empty($targetIp) || !filter_var($targetIp, FILTER_VALIDATE_IP) || $targetIp === '169.254.169.254') {
            Log::warning("Fingerprint fetchAttendanceLogs rejected invalid target IP: {$targetIp}");
            return [];
        }

        // Format SOAP resmi ZKTeco/Solution untuk mengambil seluruh riwayat log
        $soapRequest = "<GetAttLog><ArgComKey xsi:type=\"xsd:integer\">{$this->key}</ArgComKey><Arg><PIN xsi:type=\"xsd:integer\">All</PIN></Arg></GetAttLog>";

        // 1. Coba koneksi via socket HTTP 1.0 (Metode SOAP ZKTeco/Solution utama dari epjlp-main)
        $buffer = $this->sendRawSocketRequest($targetIp, $soapRequest, 10);

        // 2. Fallback via cURL jika soket bermasalah
        if (empty($buffer)) {
            $buffer = $this->sendHttpRequest($targetIp, $soapRequest, 10);
        }

        $rows = !empty($buffer) ? $this->parseAttendanceRows($buffer) : [];

        // 3. Fallback ke endpoint Solution HTTP (/cdata & /attlog) persis seperti SolutionFingerprint di epjlp-main
        if (empty($rows)) {
            $rows = $this->fetchSolutionHttpLogs($targetIp);
        }

        return $rows;
    }

    /**
     * Fallback tarik log dari endpoint HTTP Solution (/cdata dan /attlog)
     * diadopsi langsung dari SolutionFingerprint::getAttendanceData() di epjlp-main.
     */
    public function fetchSolutionHttpLogs(string $targetIp): array
    {
        $endpoints = ['/cdata', '/attlog'];
        foreach ($endpoints as $endpoint) {
            $url = "http://{$targetIp}:{$this->port}{$endpoint}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            $response = curl_exec($ch);
            curl_close($ch);

            if (is_string($response) && trim($response) !== '') {
                $parsed = $this->parseSolutionHttpData($response);
                if (!empty($parsed)) {
                    return $parsed;
                }
            }
        }

        return [];
    }

    /**
     * Parsing format teks/tab-separated dari mesin Solution (diadopsi dari SolutionFingerprint.php di epjlp-main).
     */
    protected function parseSolutionHttpData(string $rawData): array
    {
        $records = [];
        $lines = explode("\n", trim($rawData));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, 'SN') || str_starts_with($line, 'User')) {
                continue;
            }

            // Tab delimiter
            $parts = explode("\t", $line);
            if (count($parts) >= 2) {
                $pin = trim($parts[0]);
                $dateTime = trim($parts[1]);
                $status = isset($parts[3]) ? trim($parts[3]) : '0';

                if (is_numeric($pin) && strtotime($dateTime) !== false) {
                    $records[] = [
                        'pin' => $pin,
                        'datetime' => $dateTime,
                        'status' => $status,
                    ];
                }
                continue;
            }

            // Space delimiter
            if (preg_match('/^(\d+)\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $records[] = [
                    'pin' => $matches[1],
                    'datetime' => $matches[2],
                    'status' => '0',
                ];
            }
        }

        return $records;
    }

    /**
     * Kirim permintaan langsung melalui raw socket HTTP 1.0.
     */
    protected function sendRawSocketRequest(string $targetIp, string $payload, int $timeout = 10): ?string
    {
        $connect = @fsockopen($targetIp, $this->port, $errno, $errstr, $timeout);
        if (!$connect) {
            Log::warning("fsockopen failed to {$targetIp}:{$this->port} - {$errstr} ({$errno})");
            return null;
        }

        stream_set_timeout($connect, $timeout);

        $newLine = "\r\n";
        fputs($connect, "POST /iWsService HTTP/1.0" . $newLine);
        fputs($connect, "Content-Type: text/xml" . $newLine);
        fputs($connect, "Content-Length: " . strlen($payload) . $newLine . $newLine);
        fputs($connect, $payload . $newLine);

        $buffer = '';
        while (!feof($connect)) {
            $chunk = fgets($connect, 2048);
            if ($chunk === false) {
                break;
            }
            $buffer .= $chunk;
        }
        fclose($connect);

        return $buffer;
    }

    /**
     * Fallback pengiriman melalui cURL HTTP POST.
     */
    protected function sendHttpRequest(string $targetIp, string $xmlPayload, int $timeout = 10): ?string
    {
        $url = "http://{$targetIp}:{$this->port}/iWsService";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'Content-Length: ' . strlen($xmlPayload),
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error("cURL error ({$targetIp}): {$error}");
            return null;
        }

        return is_string($response) ? $response : null;
    }

    /**
     * Ekstrak data log dari respon XML buffer mesin.
     */
    public function parseAttendanceRows(string $buffer): array
    {
        $start = strpos($buffer, '<GetAttLogResponse>');
        $end = strpos($buffer, '</GetAttLogResponse>');
        if ($start !== false && $end !== false) {
            $buffer = substr($buffer, $start, ($end - $start) + strlen('</GetAttLogResponse>'));
        }

        $rows = [];
        if (preg_match_all('/<Row>(.*?)<\/Row>/s', $buffer, $matches)) {
            foreach ($matches[1] as $chunk) {
                preg_match('/<PIN>(.*?)<\/PIN>/', $chunk, $pMatch);
                preg_match('/<DateTime>(.*?)<\/DateTime>/', $chunk, $dMatch);
                preg_match('/<Status>(.*?)<\/Status>/', $chunk, $sMatch);
                preg_match('/<Verified>(.*?)<\/Verified>/', $chunk, $vMatch);

                $pin = isset($pMatch[1]) ? trim($pMatch[1]) : '';
                $dateTime = isset($dMatch[1]) ? trim($dMatch[1]) : '';
                $status = isset($sMatch[1]) && $sMatch[1] !== '' ? trim($sMatch[1]) : (isset($vMatch[1]) ? trim($vMatch[1]) : '0');

                if ($pin !== '' && $dateTime !== '') {
                    $rows[] = [
                        'pin' => $pin,
                        'datetime' => $dateTime,
                        'status' => $status,
                    ];
                }
            }
        }

        return $rows;
    }
}
