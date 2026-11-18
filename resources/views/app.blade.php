<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#ffffff">
        <meta name="description" content="Sistem Informasi Manajemen e-Kinerja dan Presensi Pegawai Jasa Lainnya Perorangan (PJLP) Suku Dinas Kominfotik Kota Administrasi Jakarta Utara.">

        <title inertia>{{ config('app.name', 'e-Kinerja PJLP Jakarta Utara') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="/images/logo-jakarta-utara.png">

        <!-- Fonts (Plus Jakarta Sans & Figtree) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-800">
        @inertia
    </body>
</html>
