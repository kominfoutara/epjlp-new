<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Hadir Harian Pegawai PJLP</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.5cm 1.5cm 2cm 1.5cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 10pt;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 8px;
            margin-bottom: 15px;
            position: relative;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .header td.logo {
            width: 70px;
            vertical-align: middle;
            text-align: center;
        }
        .header td.logo img {
            width: 65px;
            height: auto;
        }
        .header td.text {
            text-align: center;
            vertical-align: middle;
        }
        .header h3 {
            margin: 0;
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header h2 {
            margin: 2px 0;
            font-size: 13pt;
            font-weight: 900;
            text-transform: uppercase;
        }
        .header p {
            margin: 0;
            font-size: 8pt;
            color: #475569;
        }
        .title-box {
            text-align: center;
            margin-bottom: 15px;
        }
        .title-box h1 {
            font-size: 12pt;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 4px 0;
            color: #0f172a;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 12px;
            font-size: 9pt;
        }
        .meta-table td {
            padding: 2px 0;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7.5pt;
            border: 1px solid #cbd5e1;
            padding: 6px 5px;
            text-align: center;
        }
        table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 5px;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .font-bold { font-weight: bold; }
        .status-badge {
            font-size: 7.5pt;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-wfo { color: #047857; }
        .status-dl { color: #1d4ed8; }
        .status-cuti { color: #b45309; }
        .status-alpa { color: #b91c1c; }
        .signature-section {
            margin-top: 30px;
            width: 100%;
            page-break-inside: avoid;
        }
        .signature-section table {
            width: 100%;
        }
        .signature-box {
            text-align: center;
            width: 45%;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td class="logo">
                    @if(!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" alt="Logo DKI">
                    @endif
                </td>
                <td class="text">
                    <h3>Pemerintah Provinsi Daerah Khusus Ibukota Jakarta</h3>
                    <h2>Suku Dinas Komunikasi, Informatika dan Statistik<br>Kota Administrasi Jakarta Utara</h2>
                    <p>Jl. Laksamana Yos Sudarso No. 27-29, Kantor Walikota Jakarta Utara Blok P Lt. 2 Telp. 021-43935105</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="title-box">
        <h1>Daftar Hadir Harian Pegawai PJLP</h1>
        <p style="margin: 0; font-size: 9pt; color: #64748b;">
            Standar Pemantauan Biometrik Mesin Fingerprint & Presensi Realtime
        </p>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 15%; font-weight: bold;">Hari / Tanggal</td>
            <td style="width: 45%;">: {{ $tanggalIndo }}</td>
            <td style="width: 15%; font-weight: bold;">Total Pegawai</td>
            <td style="width: 25%;">: {{ count($records) }} Orang</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Seksi / Satker</td>
            <td>: {{ $seksiLabel }}</td>
            <td style="font-weight: bold;">Dicetak Pada</td>
            <td>: {{ date('d/m/Y H:i:s') }} WIB</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;">No</th>
                <th style="width: 65px;">PIN</th>
                <th>Nama Pegawai & Posisi</th>
                <th style="width: 90px;">Seksi</th>
                <th style="width: 65px;">Masuk</th>
                <th style="width: 65px;">Pulang</th>
                <th style="width: 60px;">Terlambat</th>
                <th style="width: 90px;">Status Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center font-mono">{{ $row['pin'] ?: '-' }}</td>
                    <td>
                        <div class="font-bold">{{ $row['nama_lengkap'] }}</div>
                        <div style="font-size: 7.5pt; color: #64748b;">{{ $row['posisi'] ?: 'PJLP' }}</div>
                    </td>
                    <td>{{ $row['seksi'] ?: '-' }}</td>
                    <td class="text-center font-mono font-bold" style="color: {{ !empty($row['absen_masuk']) ? '#059669' : '#94a3b8' }};">
                        {{ $row['absen_masuk'] ?: '--:--:--' }}
                    </td>
                    <td class="text-center font-mono font-bold" style="color: {{ !empty($row['absen_pulang']) ? '#2563eb' : '#94a3b8' }};">
                        {{ $row['absen_pulang'] ?: '--:--:--' }}
                    </td>
                    <td class="text-center font-mono" style="color: {{ (!empty($row['terlambat']) && $row['terlambat'] !== '00:00:00') ? '#e11d48' : '#64748b' }};">
                        {{ (!empty($row['terlambat']) && $row['terlambat'] !== '00:00:00') ? $row['terlambat'] : '-' }}
                    </td>
                    <td class="text-center font-bold">
                        <span class="status-badge {{ str_contains($row['keterangan'] ?? '', 'WFO') ? 'status-wfo' : (str_contains($row['keterangan'] ?? '', 'DL') ? 'status-dl' : (str_contains($row['keterangan'] ?? '', 'ALPA') ? 'status-alpa' : 'status-cuti')) }}">
                            {{ $row['keterangan'] ?: ($row['live_status'] ?? 'Belum Hadir') }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 20px; color: #94a3b8;">
                        Tidak ada data presensi pada tanggal ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature-section">
        <table>
            <tr>
                <td class="signature-box" style="text-align: left; padding-left: 20px;">
                    <p style="margin: 0 0 50px 0;">Mengetahui,<br>Kepala Suku Dinas Kominfotik<br>Kota Administrasi Jakarta Utara</p>
                    <p style="margin: 0; font-weight: bold; text-decoration: underline;">Robi Sumarna, S.Kom, M.Si</p>
                    <p style="margin: 0; font-size: 8pt; color: #475569;">NIP. 197805122006041005</p>
                </td>
                <td style="width: 10%;"></td>
                <td class="signature-box" style="text-align: right; padding-right: 20px;">
                    <p style="margin: 0 0 50px 0;">Jakarta, {{ $tanggalIndo }}<br>Pejabat Pengadaan / Pengawas Lapangan</p>
                    <p style="margin: 0; font-weight: bold; text-decoration: underline;">Petugas Pemantau Presensi</p>
                    <p style="margin: 0; font-size: 8pt; color: #475569;">Suku Dinas Kominfotik Jakut</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

