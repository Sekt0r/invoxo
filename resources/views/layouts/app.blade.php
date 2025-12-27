<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="application-name" content="Invoxo">
        <meta name="apple-mobile-web-app-title" content="Invoxo">
        <meta name="description" content="Create VAT-ready invoices fast. EU VAT rules, reverse charge, and cross-border logic built in.">

        <title>Invoxo | Invoicing & VAT compliance for EU freelancers</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50">
        <div class="flex h-screen">
            <!-- Sidebar -->
            @include('layouts.sidebar')

            <!-- Main Content Area -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Top Bar -->
                @include('layouts.topbar')

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto bg-gray-50">
                    <div class="p-6">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
