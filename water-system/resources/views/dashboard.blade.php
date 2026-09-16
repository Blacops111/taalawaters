@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4">
    <style>
        .taala-hero {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at 12% 20%, rgba(77, 180, 210, .18), transparent 28%),
                linear-gradient(135deg, #ffffff 0%, #f4fbfc 58%, #e9f7fa 100%);
            border: 1px solid #d5eaef;
            border-radius: 1.25rem;
            box-shadow: 0 12px 34px rgba(15, 89, 118, .08);
        }

        .taala-hero::after {
            content: '';
            position: absolute;
            right: -70px;
            bottom: -95px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            border: 36px solid rgba(36, 139, 171, .07);
        }

        .taala-hero-logo {
            width: 118px;
            height: 118px;
            object-fit: contain;
            filter: drop-shadow(0 8px 14px rgba(15, 93, 127, .16));
        }

        .taala-script {
            font-family: 'Brush Script MT', 'Segoe Script', cursive;
            font-size: clamp(2.6rem, 6vw, 4.7rem);
            line-height: .88;
            color: #171717;
            font-weight: 500;
        }

        .taala-crystal-word {
            color: #2f8392;
            font-family: Georgia, 'Times New Roman', serif;
            font-style: italic;
            font-weight: 500;
        }

        .taala-contact-line {
            color: #5b6970;
            font-size: .92rem;
        }

        .taala-contact-line span {
            display: inline-block;
            margin-right: 1rem;
            margin-bottom: .25rem;
        }

        .taala-section-title {
            color: #18313f;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .taala-stat-card,
        .taala-panel {
            border: 1px solid #e1ecef;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 7px 22px rgba(16, 79, 103, .055);
        }

        .taala-stat-card {
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .taala-stat-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: var(--stat-accent, #167ca5);
        }

        .taala-stat-label {
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .055em;
            color: #71828a;
            font-weight: 700;
        }

        .taala-stat-value {
            color: #18313f;
            font-size: 1.65rem;
            font-weight: 700;
            margin-top: .35rem;
        }

        .taala-panel .card-header {
            background: transparent;
            border-bottom: 1px solid #edf3f5;
            font-weight: 700;
            color: #234b5c;
            padding: 1rem 1.1rem;
        }

        .taala-panel .card-body {
            padding: 1.1rem;
        }

        .taala-badge {
            display: inline-block;
            padding: .4rem .7rem;
            border-radius: 999px;
            background: #dff4f7;
            color: #176f87;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .035em;
            text-transform: uppercase;
        }
    </style>

    <section class="taala-hero p-4 p-lg-5 mb-4">
        <div class="row align-items-center g-4 position-relative" style="z-index: 1;">
            <div class="col-auto">
                <img src="{{ asset('images/logo.png') }}" alt="Taala Crystal water drop logo" class="taala-hero-logo">
            </div>

            <div class="col">
                <div class="taala-badge mb-3">Management Dashboard</div>
                <div class="taala-script">Taala <span class="taala-crystal-word">Crystal</span></div>
                <div class="fw-semibold mt-3" style="color:#485a63;">Uholo Fresh Springs Co. Ltd</div>
                <div class="taala-contact-line mt-2">
                    <span>P.O. Box 277-40606, Ugunja</span>
                    <span>+254 724 293 226</span>
                    <span>uholofreshsprings@gmail.com</span>
                </div>
            </div>
        </div>
    </section>

    <h4 class="taala-section-title">Business Overview</h4>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#167ca5;">
                <div class="taala-stat-label">Total Products</div>
                <div class="taala-stat-value">{{ number_format($totalProducts) }}</div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#2d8796;">
                <div class="taala-stat-label">Total Stock</div>
                <div class="taala-stat-value">{{ number_format($totalStock) }}</div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#4ca7ba;">
                <div class="taala-stat-label">Total Sales</div>
                <div class="taala-stat-value">{{ number_format($totalSales) }}</div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#0b4f78;">
                <div class="taala-stat-label">Total Revenue</div>
                <div class="taala-stat-value">KES {{ number_format($totalRevenue, 2) }}</div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#3d9a89;">
                <div class="taala-stat-label">Total Profit</div>
                <div class="taala-stat-value">KES {{ number_format($totalProfit, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card taala-panel h-100">
                <div class="card-header">Sales Trend</div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card taala-panel h-100">
                <div class="card-header">Product Sales Distribution</div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="productPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="card taala-panel h-100">
                <div class="card-header">Stock Levels per Product</div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="stockBarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card taala-panel h-100">
                <div class="card-header">Monthly Profit Trend</div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="profitChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <div class="card taala-panel">
                <div class="card-header">Monthly Profit Overview</div>
                <div class="card-body">
                    <div style="height: 290px;">
                        <canvas id="monthlyProfitChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card taala-panel h-100">
                <div class="card-header">Inventory Attention</div>
                <div class="card-body">
                    @if($lowStockProducts->count() > 0)
                        <div class="alert alert-danger mb-0">
                            <div class="fw-bold mb-3">Low Stock Alerts</div>
                            @foreach($lowStockProducts as $stock)
                                <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
                                    <span>{{ $stock->product->name }}</span>
                                    <span class="badge bg-danger">{{ $stock->quantity_remaining }} remaining</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-success mb-0">
                            All monitored stock levels are currently above the low-stock threshold.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const gridColor = 'rgba(38, 102, 125, 0.09)';
    const brandBlue = '#167ca5';
    const brandTeal = '#2d8796';
    const deepBlue = '#0b4f78';
    const aqua = '#69b8c6';

    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($dates->toArray()) !!},
                datasets: [{
                    label: 'Daily Revenue',
                    data: {!! json_encode($totals->toArray()) !!},
                    borderColor: brandBlue,
                    backgroundColor: 'rgba(22, 124, 165, 0.10)',
                    fill: true,
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    const pieCtx = document.getElementById('productPieChart');
    if (pieCtx) {
        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($productNames->toArray() ?? []) !!},
                datasets: [{
                    data: {!! json_encode($productQuantities->toArray() ?? []) !!},
                    backgroundColor: [brandBlue, brandTeal, deepBlue, aqua, '#91cdd5', '#4d98ad']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    const barCtx = document.getElementById('stockBarChart');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($stockLabels ?? []) !!},
                datasets: [{
                    label: 'Stock Quantity',
                    data: @json($stockData),
                    backgroundColor: @json($stockColors),
                    borderWidth: 0,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    const profitCtx = document.getElementById('profitChart');
    if (profitCtx) {
        new Chart(profitCtx, {
            type: 'line',
            data: {
                labels: @json($profitLabels),
                datasets: [{
                    label: 'Monthly Profit (KES)',
                    data: @json($profitData),
                    borderColor: brandTeal,
                    backgroundColor: 'rgba(45, 135, 150, 0.10)',
                    fill: true,
                    borderWidth: 2,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    const monthlyCtx = document.getElementById('monthlyProfitChart');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: @json($months),
                datasets: [{
                    label: 'Monthly Profit',
                    data: @json($profits),
                    backgroundColor: [brandBlue, brandTeal, deepBlue, aqua, '#91cdd5', '#4d98ad'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>
@endsection
