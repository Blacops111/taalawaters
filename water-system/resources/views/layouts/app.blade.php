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
            min-width: 0;
        }

        .taala-brand-logo {
            width: 44px;
            height: 44px;
            object-fit: contain;
            flex: 0 0 auto;
        }

        .taala-brand-name {
            font-size: 1.18rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .taala-brand-name span {
            color: var(--taala-teal);
        }

        .taala-user {
            color: #617682;
            font-size: .82rem;
            font-weight: 600;
        }

        .taala-menu-button {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #c9e0e5;
            border-radius: .75rem;
            background: #fff;
            color: var(--taala-deep-blue);
            transition: background-color .2s ease, border-color .2s ease;
        }

        .taala-menu-button:hover,
        .taala-menu-button:focus {
            background: var(--taala-aqua);
            border-color: #9fcbd3;
        }

        .taala-menu-icon {
            width: 20px;
            height: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .taala-menu-icon span {
            display: block;
            height: 2px;
            width: 100%;
            border-radius: 999px;
            background: currentColor;
        }

        .taala-offcanvas {
            width: min(360px, 92vw) !important;
            border-left: 1px solid #dbecef;
        }

        .taala-menu-section {
            margin-top: 1.35rem;
        }

        .taala-menu-section:first-child {
            margin-top: 0;
        }

        .taala-menu-heading {
            margin-bottom: .45rem;
            padding: 0 .75rem;
            color: #8297a2;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .taala-menu-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .7rem .8rem;
            margin-bottom: .2rem;
            border-radius: .75rem;
            color: #365461;
            text-decoration: none;
            font-weight: 600;
            transition: background-color .2s ease, color .2s ease;
        }

        .taala-menu-link:hover,
        .taala-menu-link.active {
            background: var(--taala-aqua);
            color: var(--taala-deep-blue);
        }

        .taala-role-badge {
            display: inline-block;
            padding: .28rem .55rem;
            border-radius: 999px;
            background: var(--taala-aqua);
            color: var(--taala-deep-blue);
            font-size: .72rem;
            font-weight: 700;
            text-transform: capitalize;
        }

        .taala-logout {
            width: 100%;
            border: 1px solid #b9d9df;
            background: #fff;
            color: var(--taala-deep-blue);
            font-size: .9rem;
            font-weight: 700;
            padding: .7rem .8rem;
            border-radius: .75rem;
            text-align: left;
        }

        .taala-logout:hover {
            background: var(--taala-deep-blue);
            border-color: var(--taala-deep-blue);
            color: #fff;
        }

        .taala-main {
            padding-top: 1.6rem;
            padding-bottom: 2.5rem;
        }

        @media (max-width: 575.98px) {
            .taala-brand-logo {
                width: 38px;
                height: 38px;
            }

            .taala-brand-name {
                font-size: 1.02rem;
            }

            .taala-company-name {
                display: none;
            }

            .taala-user {
                display: none;
            }
        }
    </style>
</head>

<body class="font-sans antialiased">
<div class="min-h-screen">
    <nav class="taala-nav">
        <div class="container-fluid px-3 px-lg-4 py-2">
            <div class="d-flex align-items-center justify-content-between gap-3">
                <a
                    href="{{ auth()->user()?->role === 'driver' ? route('driver.deliveries.index') : route('dashboard') }}"
                    class="taala-brand-link d-flex align-items-center gap-2"
                >
                    <img src="{{ asset('images/logo.png') }}" alt="Taala Crystal logo" class="taala-brand-logo">
                    <div>
                        <div class="taala-brand-name">Taala <span>Crystal</span></div>
                        <div class="taala-company-name small text-muted" style="font-size: .7rem; line-height: 1;">
                            Uholo Fresh Springs Co. Ltd
                        </div>
                    </div>
                </a>

                @auth
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end">
                            <div class="taala-user">{{ auth()->user()->name }}</div>
                            <span class="taala-role-badge">{{ auth()->user()->role }}</span>
                        </div>

                        <button
                            class="taala-menu-button"
                            type="button"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#taalaMainMenu"
                            aria-controls="taalaMainMenu"
                            aria-label="Open navigation menu"
                        >
                            <span class="taala-menu-icon" aria-hidden="true">
                                <span></span>
                                <span></span>
                                <span></span>
                            </span>
                        </button>
                    </div>
                @endauth
            </div>
        </div>
    </nav>

    @auth
        <div
            class="offcanvas offcanvas-end taala-offcanvas"
            tabindex="-1"
            id="taalaMainMenu"
            aria-labelledby="taalaMainMenuLabel"
        >
            <div class="offcanvas-header border-bottom">
                <div>
                    <h5 class="offcanvas-title mb-1" id="taalaMainMenuLabel">Navigation</h5>
                    <div class="small text-muted">{{ auth()->user()->name }}</div>
                </div>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                    aria-label="Close navigation menu"
                ></button>
            </div>

            <div class="offcanvas-body">
                @if(auth()->user()->role === 'driver')
                    <div class="taala-menu-section">
                        <div class="taala-menu-heading">Driver Portal</div>
                        <a
                            href="{{ route('driver.deliveries.index') }}"
                            class="taala-menu-link {{ request()->routeIs('driver.deliveries.*') ? 'active' : '' }}"
                        >
                            <span>My Deliveries</span>
                        </a>
                    </div>
                @elseif(auth()->user()->role === 'admin')
                    <div class="taala-menu-section">
                        <div class="taala-menu-heading">Overview</div>
                        <a
                            href="{{ route('dashboard') }}"
                            class="taala-menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                        >
                            <span>Dashboard</span>
                        </a>
                    </div>

                    <div class="taala-menu-section">
                        <div class="taala-menu-heading">Operations</div>
                        <a
                            href="{{ route('inventory.index') }}"
                            class="taala-menu-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}"
                        >
                            <span>Inventory</span>
                        </a>
                        <a
                            href="{{ route('production.runs.create') }}"
                            class="taala-menu-link {{ request()->routeIs('production.*') ? 'active' : '' }}"
                        >
                            <span>Production</span>
                        </a>
                        <a
                            href="{{ route('sales-orders.create') }}"
                            class="taala-menu-link {{ request()->routeIs('sales-orders.*') ? 'active' : '' }}"
                        >
                            <span>Sales</span>
                        </a>
                        <a
                            href="{{ route('customers.index') }}"
                            class="taala-menu-link {{ request()->routeIs('customers.*') ? 'active' : '' }}"
                        >
                            <span>Customers</span>
                        </a>
                    </div>

                    <div class="taala-menu-section">
                        <div class="taala-menu-heading">Supply</div>
                        <a
                            href="{{ route('purchase-requests.index') }}"
                            class="taala-menu-link {{ request()->routeIs('purchase-requests.*') ? 'active' : '' }}"
                        >
                            <span>Purchasing</span>
                        </a>
                        <a
                            href="{{ route('suppliers.index') }}"
                            class="taala-menu-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}"
                        >
                            <span>Suppliers</span>
                        </a>
                    </div>

                    <div class="taala-menu-section">
                        <div class="taala-menu-heading">Accounting</div>
                        <a
                            href="{{ route('accounting.accounts') }}"
                            class="taala-menu-link {{ request()->routeIs('accounting.accounts') ? 'active' : '' }}"
                        >
                            <span>Chart of Accounts</span>
                        </a>
                        <a
                            href="{{ route('accounting.journals') }}"
                            class="taala-menu-link {{ request()->routeIs('accounting.journals') ? 'active' : '' }}"
                        >
                            <span>Journal Entries</span>
                        </a>
                        <a
                            href="{{ route('accounting.trial-balance') }}"
                            class="taala-menu-link {{ request()->routeIs('accounting.trial-balance') ? 'active' : '' }}"
                        >
                            <span>Trial Balance</span>
                        </a>
                        <a
                            href="{{ route('accounting.balance-sheet') }}"
                            class="taala-menu-link {{ request()->routeIs('accounting.balance-sheet') ? 'active' : '' }}"
                        >
                            <span>Balance Sheet</span>
                        </a>
                        <a
                            href="{{ route('accounting.supplier-payments') }}"
                            class="taala-menu-link {{ request()->routeIs('accounting.supplier-payments*') ? 'active' : '' }}"
                        >
                            <span>Supplier Payments</span>
                        </a>
                        <a
                            href="{{ route('accounting.customer-payments') }}"
                            class="taala-menu-link {{ request()->routeIs('accounting.customer-payments*') ? 'active' : '' }}"
                        >
                            <span>Customer Payments</span>
                        </a>
                    </div>

                    <div class="taala-menu-section">
                        <div class="taala-menu-heading">Logistics</div>
                        <a
                            href="{{ route('delivery-notes.index') }}"
                            class="taala-menu-link {{ request()->routeIs('delivery-notes.*') ? 'active' : '' }}"
                        >
                            <span>Deliveries</span>
                        </a>
                        <a
                            href="{{ route('drivers.index') }}"
                            class="taala-menu-link {{ request()->routeIs('drivers.*') ? 'active' : '' }}"
                        >
                            <span>Drivers</span>
                        </a>
                        <a
                            href="{{ route('vehicles.index') }}"
                            class="taala-menu-link {{ request()->routeIs('vehicles.*') ? 'active' : '' }}"
                        >
                            <span>Vehicles</span>
                        </a>
                        <a
                            href="{{ route('vehicle-assignments.index') }}"
                            class="taala-menu-link {{ request()->routeIs('vehicle-assignments.*') ? 'active' : '' }}"
                        >
                            <span>Assignments</span>
                        </a>
                    </div>
                @else
                    <div class="taala-menu-section">
                        <div class="taala-menu-heading">Overview</div>
                        <a
                            href="{{ route('dashboard') }}"
                            class="taala-menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                        >
                            <span>Dashboard</span>
                        </a>
                    </div>
                @endif

                <div class="taala-menu-section">
                    <div class="taala-menu-heading">Account</div>
                    <a
                        href="{{ route('profile.edit') }}"
                        class="taala-menu-link {{ request()->routeIs('profile.*') ? 'active' : '' }}"
                    >
                        <span>Profile</span>
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="taala-logout">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    @endauth

    <main class="taala-main">
        @yield('content')
    </main>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>
