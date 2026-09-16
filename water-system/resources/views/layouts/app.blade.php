<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Taala Crystal</title>

    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --taala-deep-blue: #0b4f78;
            --taala-blue: #167ca5;
            --taala-teal: #2d8796;
            --taala-aqua: #dff4f7;
            --taala-ink: #18313f;
        }

        body {
            font-family: 'Figtree', sans-serif;
            background: #f6fafb;
            color: var(--taala-ink);
        }

        .taala-nav {
            background: rgba(255, 255, 255, 0.98);
            border-bottom: 1px solid #dbecef;
            box-shadow: 0 4px 18px rgba(12, 78, 108, 0.06);
        }

        .taala-brand-link {
            text-decoration: none;
            color: var(--taala-ink);
            min-width: max-content;
        }

        .taala-brand-logo {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .taala-brand-name {
            font-size: 1.18rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .taala-brand-name span {
            color: var(--taala-teal);
        }

        .taala-nav-link {
            color: #45616f;
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 600;
            padding: 0.48rem 0.65rem;
            border-radius: 0.65rem;
            transition: background-color .2s ease, color .2s ease;
        }

        .taala-nav-link:hover {
            background: var(--taala-aqua);
            color: var(--taala-deep-blue);
        }

        .taala-main {
            padding-top: 1.6rem;
            padding-bottom: 2.5rem;
        }
    </style>
</head>

<body class="font-sans antialiased">
<div class="min-h-screen">
    <nav class="taala-nav">
        <div class="container-fluid px-3 px-lg-4 py-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <a href="{{ route('dashboard') }}" class="taala-brand-link d-flex align-items-center gap-2 me-lg-3">
                    <img src="{{ asset('images/logo.png') }}" alt="Taala Crystal logo" class="taala-brand-logo">
                    <div>
                        <div class="taala-brand-name">Taala <span>Crystal</span></div>
                        <div class="small text-muted" style="font-size: .7rem; line-height: 1;">Uholo Fresh Springs Co. Ltd</div>
                    </div>
                </a>

                <div class="d-flex align-items-center gap-1 flex-wrap">
                    <a href="{{ route('dashboard') }}" class="taala-nav-link">Dashboard</a>
                    <a href="{{ route('inventory.index') }}" class="taala-nav-link">Inventory</a>
                    <a href="{{ route('production.runs.create') }}" class="taala-nav-link">Production</a>
                    <a href="{{ route('customers.index') }}" class="taala-nav-link">Business Customers</a>
                    <a href="{{ route('sales-orders.create') }}" class="taala-nav-link">V2 Sales</a>
                    <a href="{{ route('products.index') }}" class="taala-nav-link">Products</a>
                    <a href="{{ route('stocks.index') }}" class="taala-nav-link">Stocks</a>
                    <a href="{{ route('sales.index') }}" class="taala-nav-link">Sales</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="taala-main">
        @yield('content')
    </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>
