<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lampiran: {{ $filename }} - E-PJLP Jakarta Utara</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
    </style>
</head>
<body class="bg-gray-700 text-gray-100 min-h-screen flex flex-col antialiased">
    <!-- Top Navigation Bar -->
    <header class="bg-gray-800/95 backdrop-blur-md border-b border-gray-600/80 shadow-md sticky top-0 z-50 px-4 sm:px-6 py-3">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <!-- Left: Back Button & Title -->
            <div class="flex items-center gap-3 min-w-0">
                <button type="button"
                    onclick="if (window.history.length > 1) { window.history.back(); } else { window.close(); }"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-gray-700 hover:bg-gray-600 active:bg-gray-800 text-gray-100 border border-gray-500/80 shadow-xs transition cursor-pointer flex-shrink-0"
                    title="Kembali ke Dokumen Rekap PDF">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>← Kembali ke Rekap PDF</span>
                </button>

                <div class="truncate">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wide {{ $isPdf ? 'bg-rose-900/60 text-rose-200 border border-rose-700/60' : 'bg-emerald-900/60 text-emerald-200 border border-emerald-700/60' }}">
                            {{ $extension }}
                        </span>
                        <h1 class="text-xs sm:text-sm font-bold text-white truncate" title="{{ $filename }}">
                            {{ $filename }}
                        </h1>
                    </div>
                    <p class="text-[11px] text-gray-300 mt-0.5">
                        Ukuran Berkas: {{ $fileSize }} • Lampiran Aktivitas Pegawai
                    </p>
                </div>
            </div>

            <!-- Right: Actions -->
            <div class="flex items-center gap-2 self-end sm:self-auto flex-shrink-0">
                <!-- Unduh File -->
                <a href="{{ $fileUrl }}" download="{{ $filename }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold bg-gray-900 hover:bg-black active:bg-gray-950 text-white border border-gray-600 shadow-xs transition cursor-pointer"
                    title="Unduh Berkas Lampiran">
                    <svg class="w-3.5 h-3.5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Unduh ⤓</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 flex flex-col items-center justify-center">
        @if($isImage)
            <div class="w-full flex flex-col items-center justify-center my-auto">
                <div class="max-w-5xl w-full bg-gray-800/90 p-2 sm:p-4 rounded-2xl border border-gray-600/70 shadow-xl flex items-center justify-center overflow-hidden">
                    <img src="{{ $fileUrl }}" alt="{{ $filename }}"
                        class="max-h-[82vh] w-auto max-w-full object-contain rounded-xl transition duration-200 select-none shadow-md" />
                </div>
            </div>
        @elseif($isPdf)
            <div class="w-full h-[85vh] bg-gray-800 rounded-2xl border border-gray-600 shadow-xl overflow-hidden">
                <iframe src="{{ $fileUrl }}" class="w-full h-full border-0 rounded-2xl" title="{{ $filename }}"></iframe>
            </div>
        @else
            <div class="text-center p-8 bg-gray-800 rounded-2xl border border-gray-600 shadow-xl max-w-md">
                <p class="text-sm text-gray-200 mb-4">Format berkas ini tidak dapat dipratinjau secara langsung.</p>
                <a href="{{ $fileUrl }}" download="{{ $filename }}" class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition">
                    Unduh Berkas Sekarang
                </a>
            </div>
        @endif
    </main>
</body>
</html>
