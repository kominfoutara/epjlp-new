<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Aktivitas Harian Pegawai</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 1.0cm 1.2cm 1.4cm 1.2cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 8pt;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #0f172a;
            padding-bottom: 6px;
            margin-bottom: 10px;
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
            width: 52px;
            height: auto;
        }
        .header td.text {
            text-align: center;
            vertical-align: middle;
        }
        .header h3 {
            margin: 0;
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
        }
        .header h2 {
            margin: 1px 0;
            font-size: 12pt;
            font-weight: 900;
            text-transform: uppercase;
            color: #0f172a;
        }
        .header p {
            margin: 0;
            font-size: 7.5pt;
            color: #475569;
        }
        .title-box {
            text-align: center;
            margin-bottom: 10px;
        }
        .title-box h1 {
            font-size: 11pt;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 2px 0;
            color: #0f172a;
        }
        .title-box p {
            margin: 0;
            font-size: 8pt;
            color: #64748b;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 10px;
            font-size: 8pt;
        }
        .meta-table td {
            padding: 1.5px 0;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        table.data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7pt;
            border: 1px solid #0f172a;
            padding: 5px 4px;
            text-align: center;
            vertical-align: middle;
        }
        table.data-table td {
            border: 1px solid #cbd5e1;
            padding: 4px;
            vertical-align: middle;
        }
        table.data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .font-bold { font-weight: bold; }
        .text-muted { color: #94a3b8; font-style: italic; }
        
        /* Badges status */
        .status-badge {
            font-size: 6.5pt;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 3px;
            display: inline-block;
            text-transform: uppercase;
        }
        .status-disetujui {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }
        .status-menunggu {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .status-ditolak {
            background-color: #ffe4e6;
            color: #be123c;
            border: 1px solid #fecdd3;
        }

        /* Link Lampiran */
        .link-container {
            line-height: 1.4;
        }
        .link-photo {
            color: #1d4ed8;
            text-decoration: underline;
            font-weight: bold;
            display: inline-block;
            margin: 1px 2px 1px 0;
            font-size: 7pt;
        }
        .link-pdf {
            color: #b91c1c;
            text-decoration: underline;
            font-weight: bold;
            display: inline-block;
            font-size: 7pt;
        }
        .catatan-box {
            margin-top: 3px;
            font-size: 6.5pt;
            color: #be123c;
            background-color: #fff1f2;
            padding: 2px 4px;
            border-radius: 2px;
            border-left: 2px solid #f43f5e;
        }

        /* Tanda Tangan */
        .signature-section {
            margin-top: 20px;
            width: 100%;
            page-break-inside: avoid;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }
        .signature-box {
            text-align: center;
            width: 35%;
        }
    </style>
</head>
<body>
    <!-- KOP SURAT RESMI -->
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
                    <p>Jl. Laksamana Yos Sudarso No. 27-29, Kantor Walikota Jakarta Utara Blok P Lt. 2 Telp. 021-43935105, Jakarta Utara 14320</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- JUDUL LAPORAN -->
    <div class="title-box">
        <h1>REKAPITULASI AKTIVITAS HARIAN PEGAWAI</h1>
        <p>Laporan Pemantauan Kinerja & Realisasi Capaian Kerja Harian Pegawai</p>
    </div>

    <!-- INFORMASI METADATA LAPORAN -->
    <table class="meta-table">
        <tr>
            <td style="width: 12%; font-weight: bold;">Periode</td>
            <td style="width: 38%;">: {{ $periode }}</td>
            <td style="width: 14%; font-weight: bold;">Total Aktivitas</td>
            <td style="width: 36%;">: {{ count($records) }} Laporan</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Seksi / Satker</td>
            <td>: {{ $seksiLabel }}</td>
            <td style="font-weight: bold;">Status Validasi</td>
            <td>: {{ $statusLabel }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Dicetak Pada</td>
            <td>: {{ date('d/m/Y H:i:s') }} WIB</td>
            <td style="font-weight: bold;">Keterangan Link</td>
            <td>: Klik nama foto atau PDF untuk membuka berkas (gunakan Ctrl + Klik atau Klik-Tengah untuk Tab Baru)</td>
        </tr>
    </table>

    <!-- TABEL DATA AKTIVITAS -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 20px;">No</th>
                <th style="width: 65px;">Tanggal & Jam</th>
                <th style="width: 105px;">Nama Pegawai & PIN</th>
                <th style="width: 90px;">Seksi & Posisi</th>
                <th style="width: 95px;">Kategori & Lokasi</th>
                <th>Uraian Tugas & Capaian Kerja</th>
                <th style="width: 85px;">Lampiran Foto</th>
                <th style="width: 80px;">Lampiran PDF</th>
                <th style="width: 70px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $index => $row)
                <tr>
                    <td class="text-center font-bold">{{ $index + 1 }}</td>
                    <td class="text-center">
                        <div class="font-bold">{{ $row['tanggal'] }}</div>
                        <div class="text-muted" style="font-size: 6.5pt;">{{ $row['jam_kerja'] }}</div>
                    </td>
                    <td>
                        <div class="font-bold">{{ $row['nama_lengkap'] }}</div>
                        <div class="font-mono text-muted" style="font-size: 6.5pt;">PIN: {{ $row['pin'] }}</div>
                    </td>
                    <td>
                        <div>{{ $row['seksi'] }}</div>
                        <div class="text-muted" style="font-size: 6.5pt;">{{ $row['posisi'] }}</div>
                    </td>
                    <td>
                        <div class="font-bold">{{ $row['kategori'] }}</div>
                        <div class="text-muted" style="font-size: 6.5pt;">
                            {{ $row['lokasi'] }}
                            @if(!empty($row['nama_unit']))
                                <br>({{ $row['nama_unit'] }})
                            @endif
                        </div>
                    </td>
                    <td>
                        <div>{{ $row['uraian'] }}</div>
                        @if(!empty($row['catatan']))
                            <div class="catatan-box">
                                <strong>Catatan:</strong> {{ $row['catatan'] }}
                            </div>
                        @endif
                    </td>
                    <td class="text-center">
                        @if(!empty($row['photos']) && count($row['photos']) > 0)
                            <div class="link-container">
                                @foreach($row['photos'] as $pIdx => $photo)
                                    <a href="{{ $photo['viewer_url'] ?? $photo['url'] }}" target="_blank" rel="noopener noreferrer" class="link-photo">
                                        Foto {{ $pIdx + 1 }} ↗
                                    </a>
                                    @if(!$loop->last)<br>@endif
                                @endforeach
                            </div>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if(!empty($row['pdf']))
                            <a href="{{ $row['pdf']['viewer_url'] ?? $row['pdf']['url'] }}" target="_blank" rel="noopener noreferrer" class="link-pdf">
                                Dokumen PDF ↗
                            </a>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($row['status_validasi'] === 'Disetujui')
                            <span class="status-badge status-disetujui">Disetujui</span>
                        @elseif($row['status_validasi'] === 'Ditolak')
                            <span class="status-badge status-ditolak">Ditolak</span>
                        @else
                            <span class="status-badge status-menunggu">Menunggu</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 18px; color: #64748b; font-style: italic;">
                        Tidak ada catatan aktivitas yang sesuai dengan filter yang dipilih.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- LEMBAR PENGESAHAN / TANDA TANGAN -->
    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td style="width: 65%;"></td>
                <td class="signature-box">
                    <div>Jakarta, {{ date('d F Y') }}</div>
                    <div style="font-weight: bold; margin-top: 4px;">Mengetahui,</div>
                    <div style="font-size: 7.5pt; color: #475569;">Kepala Suku Dinas Kominfotik<br>Kota Administrasi Jakarta Utara</div>
                    <div style="height: 48px;"></div>
                    <div style="font-weight: bold; text-decoration: underline;">...................................................</div>
                    <div style="font-size: 7pt; color: #64748b;">NIP. .............................................</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

