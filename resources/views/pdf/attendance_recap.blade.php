<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Presensi Bulanan Pegawai PJLP</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 1.2cm 1.2cm 1.5cm 1.2cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 9pt;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .header td.logo {
            width: 60px;
            vertical-align: middle;
            text-align: center;
        }
        .header td.logo img {
            width: 55px;
            height: auto;
        }
        .header td.text {
            text-align: center;
            vertical-align: middle;
        }
        .header h3 {
            margin: 0;
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .header h2 {
            margin: 1px 0;
            font-size: 12pt;
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
            margin-bottom: 12px;
        }
        .title-box h1 {
            font-size: 11pt;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 2px 0;
            color: #0f172a;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 10px;
            font-size: 8.5pt;
        }
        .meta-table td {
            padding: 2px 0;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }
        table.data-table th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7pt;
            border: 1px solid #cbd5e1;
            padding: 5px 4px;
            text-align: center;
        }
        table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .font-bold { font-weight: bold; }
        .signature-section {
            margin-top: 25px;
            width: 100%;
            page-break-inside: avoid;
        }
        .signature-section table {
            width: 100%;
        }
        .signature-box {
            text-align: center;
            width: 33%;
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
                    <h2>Suku Dinas Komunikasi, Informatika dan Statistik Kota Administrasi Jakarta Utara</h2>
                    <p>Jl. Laksamana Yos Sudarso No. 27-29, Kantor Walikota Jakarta Utara Blok P Lt. 2 Telp. 021-43935105</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="title-box">
        <h1>Rekapitulasi Presensi Bulanan Pegawai PJLP</h1>
        <p style="margin: 0; font-size: 8pt; color: #64748b;">
            Akumulasi Hari Kerja, Dinas Luar, Cuti, Sakit, dan Izin sesuai Standar Pergub DKI No. 125/2019
        </p>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 12%; font-weight: bold;">Periode Bulan</td>
            <td style="width: 38%;">: {{ $periodeIndo }}</td>
            <td style="width: 15%; font-weight: bold;">Total Pegawai</td>
            <td style="width: 35%;">: {{ count($recap) }} Orang</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Seksi / Bidang</td>
            <td>: {{ $seksiLabel }}</td>
            <td style="font-weight: bold;">Dicetak Pada</td>
            <td>: {{ date('d/m/Y H:i:s') }} WIB</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 25px;">No</th>
                <th rowspan="2" style="width: 70px;">PIN Mesin</th>
                <th rowspan="2">Nama Lengkap Pegawai</th>
                <th rowspan="2" style="width: 110px;">Seksi</th>
                <th rowspan="2" style="width: 130px;">Posisi Pekerjaan</th>
                <th colspan="6">Rincian Hari Kehadiran</th>
                <th rowspan="2" style="width: 60px;">Total Hadir (WFO+DL)</th>
            </tr>
            <tr>
                <th style="width: 45px;">WFO</th>
                <th style="width: 45px;">DL</th>
                <th style="width: 45px;">Cuti</th>
                <th style="width: 45px;">Sakit</th>
                <th style="width: 45px;">Izin</th>
                <th style="width: 45px;">Alpa</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totWfo = 0; $totDl = 0; $totCuti = 0; $totSakit = 0; $totIzin = 0; $totAlpa = 0; $totGrand = 0;
            @endphp
            @forelse($recap as $index => $row)
                @php
                    $hadirTotal = ($row['total_hadir'] ?? 0) + ($row['total_dl'] ?? 0);
                    $totWfo += ($row['total_hadir'] ?? 0);
                    $totDl += ($row['total_dl'] ?? 0);
                    $totCuti += ($row['total_cuti'] ?? 0);
                    $totSakit += ($row['total_sakit'] ?? 0);
                    $totIzin += ($row['total_izin'] ?? 0);
                    $totAlpa += ($row['total_alpa'] ?? 0);
                    $totGrand += $hadirTotal;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center font-mono">{{ $row['pin'] ?: '-' }}</td>
                    <td class="font-bold">{{ $row['nama_lengkap'] }}</td>
                    <td>{{ $row['seksi'] ?: '-' }}</td>
                    <td style="font-size: 7.5pt; color: #475569;">{{ $row['posisi'] ?: 'PJLP' }}</td>
                    <td class="text-center font-mono font-bold" style="color: #059669;">{{ $row['total_hadir'] ?? 0 }}</td>
                    <td class="text-center font-mono font-bold" style="color: #2563eb;">{{ $row['total_dl'] ?? 0 }}</td>
                    <td class="text-center font-mono">{{ $row['total_cuti'] ?? 0 }}</td>
                    <td class="text-center font-mono">{{ $row['total_sakit'] ?? 0 }}</td>
                    <td class="text-center font-mono">{{ $row['total_izin'] ?? 0 }}</td>
                    <td class="text-center font-mono font-bold" style="color: {{ ($row['total_alpa'] ?? 0) > 0 ? '#e11d48' : '#94a3b8' }};">
                        {{ $row['total_alpa'] ?? 0 }}
                    </td>
                    <td class="text-center font-mono font-bold" style="background-color: #f8fafc; color: #0f172a;">
                        {{ $hadirTotal }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center" style="padding: 20px; color: #94a3b8;">
                        Tidak ada data rekap presensi pada periode bulan ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f1f5f9; font-weight: bold;">
                <td colspan="5" class="text-right" style="padding: 6px;">TOTAL KESELURUHAN</td>
                <td class="text-center font-mono" style="color: #059669;">{{ $totWfo }}</td>
                <td class="text-center font-mono" style="color: #2563eb;">{{ $totDl }}</td>
                <td class="text-center font-mono">{{ $totCuti }}</td>
                <td class="text-center font-mono">{{ $totSakit }}</td>
                <td class="text-center font-mono">{{ $totIzin }}</td>
                <td class="text-center font-mono" style="color: {{ $totAlpa > 0 ? '#e11d48' : '#64748b' }};">{{ $totAlpa }}</td>
                <td class="text-center font-mono" style="background-color: #e2e8f0;">{{ $totGrand }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="signature-section">
        <table>
            <tr>
                <td class="signature-box" style="text-align: left;">
                    <p style="margin: 0 0 45px 0;">Mengetahui,<br>Kepala Suku Dinas Kominfotik<br>Kota Administrasi Jakarta Utara</p>
                    <p style="margin: 0; font-weight: bold; text-decoration: underline;">Robi Sumarna, S.Kom, M.Si</p>
                    <p style="margin: 0; font-size: 8pt; color: #475569;">NIP. 197805122006041005</p>
                </td>
                <td class="signature-box"></td>
                <td class="signature-box" style="text-align: right;">
                    <p style="margin: 0 0 45px 0;">Jakarta, {{ date('d F Y') }}<br>Pejabat Pembuat Komitmen (PPK)<br>Suku Dinas Kominfotik</p>
                    <p style="margin: 0; font-weight: bold; text-decoration: underline;">Pejabat Pembuat Komitmen</p>
                    <p style="margin: 0; font-size: 8pt; color: #475569;">NIP. ........................................</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

