<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Taala Crystal | Pure Water, Managed Better</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">

    <style>
        :root {
            --deep: #0b4f78;
            --blue: #167ca5;
            --teal: #2d8796;
            --aqua: #e7f7f9;
            --ink: #18313f;
            --muted: #617682;
            --line: #d9ebef;
            --white: #ffffff;
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: 'Figtree', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top right, rgba(45, 135, 150, .14), transparent 32rem),
                linear-gradient(180deg, #ffffff 0%, #f4fbfc 100%);
        }

        a { color: inherit; }

        .site-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .site-nav {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(255, 255, 255, .94);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--line);
        }

        .nav-inner,
        .hero,
        .section-inner,
        .footer-inner {
            width: min(1180px, calc(100% - 2rem));
            margin: 0 auto;
        }

        .nav-inner {
            min-height: 76px;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            text-decoration: none;
            margin-right: auto;
        }

        .brand img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .brand-name {
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .brand-name span { color: var(--teal); }

        .brand-company {
            margin-top: .1rem;
            color: var(--muted);
            font-size: .72rem;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: .65rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: .65rem 1rem;
            border-radius: .8rem;
            border: 1px solid transparent;
            text-decoration: none;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--deep), var(--teal));
            box-shadow: 0 10px 24px rgba(11, 79, 120, .16);
        }

        .btn-outline {
            color: var(--deep);
            background: #fff;
            border-color: #b9d9df;
        }

        .hero {
            flex: 1;
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            align-items: center;
            gap: 4rem;
            padding: 6.5rem 0 5rem;
        }

        .eyebrow {
            display: inline-flex;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: var(--aqua);
            color: var(--deep);
            font-size: .82rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        h1 {
            margin: 1.2rem 0 1rem;
            max-width: 780px;
            font-size: clamp(2.7rem, 6vw, 5.6rem);
            line-height: .98;
            letter-spacing: -.055em;
        }

        h1 span { color: var(--teal); }

        .hero-copy {
            max-width: 680px;
            color: var(--muted);
            font-size: 1.08rem;
            line-height: 1.75;
        }

        .hero-actions {
            display: flex;
            gap: .8rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .hero-card {
            position: relative;
            overflow: hidden;
            border-radius: 2rem;
            padding: 2.2rem;
            min-height: 420px;
            color: #fff;
            background:
                radial-gradient(circle at 70% 20%, rgba(255,255,255,.2), transparent 10rem),
                linear-gradient(145deg, #0b4f78, #167ca5 55%, #2d8796);
            box-shadow: 0 28px 70px rgba(11, 79, 120, .24);
        }

        .hero-card::before,
        .hero-card::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,.18);
        }

        .hero-card::before {
            width: 280px;
            height: 280px;
            right: -90px;
            top: -80px;
        }

        .hero-card::after {
            width: 360px;
            height: 360px;
            left: -190px;
            bottom: -220px;
        }

        .hero-logo {
            width: 90px;
            height: 90px;
            object-fit: contain;
            padding: .65rem;
            border-radius: 1.4rem;
            background: rgba(255,255,255,.94);
        }

        .hero-card h2 {
            margin: 2rem 0 .6rem;
            font-size: 2rem;
        }

        .hero-card p {
            margin: 0;
            max-width: 360px;
            line-height: 1.65;
            color: rgba(255,255,255,.82);
        }

        .company-details {
            position: absolute;
            left: 2.2rem;
            right: 2.2rem;
            bottom: 2.2rem;
            display: grid;
            gap: .35rem;
            font-size: .9rem;
            color: rgba(255,255,255,.9);
        }

        .features {
            padding: 1rem 0 5.5rem;
        }

        .section-heading {
            max-width: 680px;
            margin-bottom: 2rem;
        }

        .section-heading h2 {
            margin: 0 0 .6rem;
            font-size: clamp(2rem, 4vw, 3rem);
            letter-spacing: -.035em;
        }

        .section-heading p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .feature {
            background: rgba(255,255,255,.84);
            border: 1px solid var(--line);
            border-radius: 1.3rem;
            padding: 1.35rem;
            box-shadow: 0 12px 30px rgba(11,79,120,.06);
        }

        .feature strong {
            display: block;
            margin-bottom: .45rem;
            color: var(--deep);
            font-size: 1.04rem;
        }

        .feature p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
            font-size: .93rem;
        }

        footer {
            border-top: 1px solid var(--line);
            background: rgba(255,255,255,.72);
        }

        .footer-inner {
            padding: 1.5rem 0 2rem;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            color: var(--muted);
            font-size: .88rem;
        }

        .logout-form { margin: 0; }

        @media (max-width: 850px) {
            .hero {
                grid-template-columns: 1fr;
                gap: 2.2rem;
                padding-top: 4rem;
            }

            .hero-card { min-height: 360px; }

            .feature-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 600px) {
            .nav-inner {
                padding: .8rem 0;
                align-items: flex-start;
                flex-direction: column;
            }

            .brand { margin-right: 0; }

            .nav-actions { width: 100%; }

            .nav-actions .btn,
            .nav-actions form { flex: 1; }

            .nav-actions form .btn { width: 100%; }

            h1 { font-size: 3rem; }

            .hero { padding-top: 3rem; }

            .hero-card { padding: 1.6rem; }

            .company-details {
                left: 1.6rem;
                right: 1.6rem;
                bottom: 1.6rem;
            }
        }
    </style>
</head>
<body>
<div class="site-shell">
    <header class="site-nav">
        <div class="nav-inner">
            <a href="{{ url('/') }}" class="brand">
                <img src="{{ asset('images/logo.png') }}" alt="Taala Crystal logo">
                <div>
                    <div class="brand-name">Taala <span>Crystal</span></div>
                    <div class="brand-company">Uholo Fresh Springs Co. Ltd</div>
                </div>
            </a>

            <div class="nav-actions">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-outline">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}" class="logout-form">
                        @csrf
                        <button type="submit" class="btn btn-primary">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline">Login</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-primary">Create Account</a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div>
                <div class="eyebrow">Pure water. Clear operations.</div>
                <h1>Welcome to <span>Taala Crystal</span></h1>
                <p class="hero-copy">
                    A modern operating system for Uholo Fresh Springs Co. Ltd — bringing inventory,
                    production, sales, customers and purchasing together in one reliable workflow.
                </p>

                <div class="hero-actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">Open Management Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary">Login to the System</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-outline">Register</a>
                        @endif
                    @endauth
                </div>
            </div>

            <div class="hero-card">
                <img src="{{ asset('images/logo.png') }}" alt="Taala Crystal" class="hero-logo">
                <h2>Taala Crystal</h2>
                <p>
                    Bottled and bulk water operations managed with traceable stock movements,
                    controlled production and audit-safe business records.
                </p>

                <div class="company-details">
                    <strong>Uholo Fresh Springs Co. Ltd</strong>
                    <span>P.O. Box 277-40606, Ugunja</span>
                    <span>+254 724 293 226</span>
                    <span>uholofreshsprings@gmail.com</span>
                </div>
            </div>
        </section>

        <section class="features">
            <div class="section-inner">
                <div class="section-heading">
                    <h2>One system for the water business.</h2>
                    <p>
                        Taala Crystal connects the day-to-day operational records that matter while
                        keeping important stock and transaction history auditable.
                    </p>
                </div>

                <div class="feature-grid">
                    <div class="feature">
                        <strong>Inventory & Production</strong>
                        <p>Track materials, raw water, production recipes, finished products and live ledger balances.</p>
                    </div>
                    <div class="feature">
                        <strong>Sales & Customers</strong>
                        <p>Manage walk-in and business sales with controlled pricing, reports and safe transaction reversal.</p>
                    </div>
                    <div class="feature">
                        <strong>Purchasing & Growth</strong>
                        <p>Supplier and purchasing workflows are being added to connect replenishment directly to operations.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="footer-inner">
            <span>&copy; {{ now()->year }} Uholo Fresh Springs Co. Ltd</span>
            <span>Taala Crystal Management System</span>
        </div>
    </footer>
</div>
</body>
</html>
