<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Taala Water System</title>

    <link rel="icon"
          type="image/png"
          href="{{ asset('images/logo.png') }}">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <!-- Fonts -->
    <link rel="preconnect"
          href="https://fonts.bunny.net">

    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap"
          rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css',
            'resources/js/app.js'])

</head>

<body class="font-sans antialiased">

<div class="min-h-screen bg-gray-100">

    <!-- Top Navigation -->

    <nav class="bg-white border-b border-gray-100">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex justify-between h-16">

                <!-- Logo / Title -->

                <div class="flex items-center space-x-6">

                    <span class="text-lg font-bold">

                        Taala Water System

                    </span>

                    <!-- Menu Links -->

                    <a href="{{ route('dashboard') }}"
                       class="text-gray-700 hover:text-blue-500">

                        Dashboard

                    </a>

                    <a href="{{ route('inventory.index') }}"
                       class="text-gray-700 hover:text-blue-500">

                        Inventory

                    </a>

                    <a href="{{ route('production.runs.create') }}"
                       class="text-gray-700 hover:text-blue-500">

                        Production

                    </a>

                    <a href="{{ route('products.index') }}"
                       class="text-gray-700 hover:text-blue-500">

                        Products

                    </a>

                    <a href="{{ route('stocks.index') }}"
                       class="text-gray-700 hover:text-blue-500">

                        Stocks

                    </a>

                    <a href="{{ route('sales.index') }}"
                       class="text-gray-700 hover:text-blue-500">

                        Sales

                    </a>

                </div>

            </div>

        </div>

    </nav>

    <!-- Page Content -->

    <main class="p-6">

        @yield('content')

    </main>

</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</body>

</html>
