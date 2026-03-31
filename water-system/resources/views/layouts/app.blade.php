<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Taala Crystal Water System</title>
    <link rel="icon"
          type="image/png"
          href="{{ asset('images/logo.png') }}">

    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>

        body {
            background-color: #f5f9ff;
        }

        /* Sidebar */
        .sidebar {
            height: 100vh;
            background: linear-gradient(to bottom, #1E73BE, #4DA6FF);
            color: white;
            padding-top: 20px;
        }

        .sidebar a {
            color: white;
            display: block;
            padding: 12px;
            text-decoration: none;
        }

        .sidebar a:hover {
            background-color: #0d47a1;
        }

        /* Header */
        .header {
            background-color: white;
            padding: 10px;
            border-bottom: 2px solid #1E73BE;
        }

        .footer {
            background-color: white;
            padding: 10px;
            border-top: 2px solid #1E73BE;
            text-align: center;
        }

    </style>

</head>

<body>

<div class="container-fluid">
<div class="row">

    <!-- Sidebar -->
    <div class="col-md-2 sidebar">

        <div class="text-center mb-3">

            <img src="{{ asset('images/logo.png') }}"
                 width="60"
                 height="60"
                 alt="Taala Logo">

            <h5 class="mt-2">
                Taala Crystal
            </h5>

        </div>

        <a href="#">Dashboard</a>
        <a href="#">Products</a>
        <a href="#">Stock</a>
        <a href="#">Sales</a>
        <a href="#">Customers</a>
        <a href="#">Trucks</a>
        <a href="#">Expenses</a>
        <a href="#">Reports</a>

    </div>

    <!-- Main Content -->
    <div class="col-md-10">

        <!-- Header -->
        <div class="header">

            <div class="d-flex justify-content-between">

                <h5>
                    Water Management System
                </h5>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-danger btn-sm">
                        Logout
                    </button>
                </form>

            </div>

        </div>

        <nav class="navbar navbar-expand-lg navbar-light bg-light px-3">

            <ul class="navbar-nav me-auto">

                <li class="nav-item">
                    <a class="nav-link"
                       href="{{ route('products.index') }}">
                        Products
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link"
                       href="{{ route('stocks.index') }}">
                        Stocks
                    </a>
                </li>

            </ul>

        </nav>

        <!-- Page Content -->
        <div class="p-4">

            @yield('content')

        </div>

        <!-- Footer -->
        <div class="footer">

            Naturally Pure, Perfectly Refreshing 💧

        </div>

    </div>

</div>
</div>

</body>
</html>
