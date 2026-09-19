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
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .055em;
            color: #71828a;
            font-weight: 700;
        }

        .taala-stat-value {
            color: #18313f;
            font-size: 1.55rem;
            font-weight: 700;
            margin-top: .35rem;
        }

        .taala-stat-help {
            margin-top: .25rem;
            color: #7b8d95;
            font-size: .78rem;
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

    <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-3">
        <div>
            <h4 class="taala-section-title mb-1">V2 Business Overview</h4>
            <div class="text-muted small">Live figures from the V2 inventory ledger and completed V2 sales only.</div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#167ca5;">
                <div class="taala-stat-label">Active Inventory Items</div>
                <div class="taala-stat-value">{{ number_format($activeInventoryItems) }}</div>
                <div class="taala-stat-help">V2 SKU master</div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#2d8796;">
                <div class="taala-stat-label">Raw Water Balance</div>
                <div class="taala-stat-value">{{ number_format($rawWaterBalance, 3) }} L</div>
                <div class="taala-stat-help">Meter-backed ledger balance</div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#4ca7ba;">
                <div class="taala-stat-label">Finished Units On Hand</div>
                <div class="taala-stat-value">{{ number_format($finishedUnitsOnHand, 0) }}</div>
                <div class="taala-stat-help">Finished-product ledger stock</div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#0b4f78;">
                <div class="taala-stat-label">Completed V2 Sales</div>
                <div class="taala-stat-value">{{ number_format($completedSales) }}</div>
                <div class="taala-stat-help">Reversed sales excluded</div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#3d9a89;">
                <div class="taala-stat-label">V2 Sales Revenue</div>
                <div class="taala-stat-value">KES {{ number_format($salesRevenue, 2) }}</div>
                <div class="taala-stat-help">Completed non-reversed sales</div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="taala-stat-card p-3" style="--stat-accent:#c17a35;">
                <div class="taala-stat-label">Low Stock Items</div>
                <div class="taala-stat-value">{{ number_format($lowStockItems->count()) }}</div>
                <div class="taala-stat-help">Based on V2 reorder levels</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            <div class="card taala-panel h-100">
                <div class="card-header">V2 Sales Revenue Trend</div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card taala-panel h-100">
                <div class="card-header">V2 Units Sold by Product</div>
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
                <div class="card-header">Finished Product Stock</div>
                <div class="card-body">
                    <div style="height: 320px;">
                        <canvas id="finishedStockChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card taala-panel h-100">
                <div class="card-header">Inventory Attention</div>
                <div class="card-body">
                    @if($lowStockItems->count() > 0)
                        <div class="alert alert-warning mb-0">
                            <div class="fw-bold mb-3">Items at or below reorder level</div>
                            @foreach($lowStockItems as $item)
                                <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom">
                                    <div>
                                        <div class="fw-semibold">{{ $item->name }}</div>
                                        <div class="small text-muted">{{ $item->sku }}</div>
                                    </div>
                                    <span class="badge bg-warning text-dark">
                                        {{ number_format((float) ($item->stock_balance ?? 0), 3) }} {{ $item->unit }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-success mb-0">
                            All V2 inventory items with configured reorder levels are currently above their thresholds.
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
                labels: @json($salesDates),
                datasets: [{
                    label: 'Completed V2 Revenue (KES)',
                    data: @json($salesTotals),
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
                labels: @json($productNames),
                datasets: [{
                    data: @json($productQuantities),
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

    const stockCtx = document.getElementById('finishedStockChart');
    if (stockCtx) {
        new Chart(stockCtx, {
            type: 'bar',
            data: {
                labels: @json($finishedStockLabels),
                datasets: [{
                    label: 'Finished Units On Hand',
                    data: @json($finishedStockData),
                    backgroundColor: [brandBlue, brandTeal, deepBlue, aqua],
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
});
</script>
@endsection
