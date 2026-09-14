@extends('layouts.app')

@section('content')

<div class="container">

    <h2 class="mb-4">
        Dashboard Overview
    </h2>

    <div class="row">

        {{-- Products --}}
        <div class="col-md-3">

            <div class="card text-white bg-primary mb-3">

                <div class="card-body">

                    <h5>Total Products</h5>

                    <h3>
                        {{ $totalProducts }}
                    </h3>

                </div>

            </div>

        </div>

        {{-- Stock --}}
        <div class="col-md-3">

            <div class="card text-white bg-success mb-3">

                <div class="card-body">

                    <h5>Total Stock</h5>

                    <h3>
                        {{ $totalStock }}
                    </h3>

                </div>

            </div>

        </div>

        {{-- Sales --}}
        <div class="col-md-3">

            <div class="card text-white bg-warning mb-3">

                <div class="card-body">

                    <h5>Total Sales</h5>

                    <h3>
                        {{ $totalSales }}
                    </h3>

                </div>

            </div>

        </div>

        {{-- Revenue --}}
        <div class="col-md-3">

            <div class="card text-white bg-danger mb-3">

                <div class="card-body">

                    <h5>Total Revenue</h5>

                    <h3>
                        {{ number_format($totalRevenue,2) }}
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">
            <div class="card text-white bg-success mb-3">
                <div class="card-body">

                    <h5 class="card-title">
                        Total Profit
                    </h5>

                    <h3>
                        KES {{ number_format($totalProfit, 2) }}
                    </h3>

                </div>
            </div>
        </div>

    </div>

</div>

<div class="row mt-4">

    <!-- Line Chart -->
    <div class="col-md-8">

        <div class="card">

            <div class="card-body">

                <h5 class="mb-3">
                    Sales Trend
                </h5>

                <canvas id="salesChart"
                        style="height:300px;">
                </canvas>

            </div>

        </div>

    </div>

    <!-- Pie Chart -->
    <div class="col-md-4">

        <div class="card">

            <div class="card-body text-center">

                <h5 class="mb-3">
                    Product Sales Distribution
                </h5>

                <div style="height:300px;">

                    <canvas id="productPieChart"></canvas>

                </div>

            </div>

        </div>

    </div>

</div>

<div class="row mt-4">

    <div class="col-md-10 mx-auto">

        <div class="card">

            <div class="card-body">

                <h5 class="mb-3">
                    Stock Levels per Product
                </h5>

                <div style="height:250px;">

                    <canvas id="stockBarChart"></canvas>

                </div>

            </div>

        </div>

    </div>

</div>

<div class="row mt-4">

    <div class="col-md-6">

        <div class="card">

            <div class="card-header">
            📈 Monthly Profit Trend
            </div>

            <div class="card-body">

                <canvas id="profitChart"
                        height="120"></canvas>

            </div>

        </div>

    </div>

    <div class="col-md-6">

        <div class="card">

            <div class="card-body">

                <div style="width:100%; max-width:700px;">
                    <canvas id="monthlyProfitChart"></canvas>
                </div>

            </div>

        </div>

    </div>

</div>

<div class="row mt-4">

    <div class="col-md-10 mx-auto">

        <div class="card border-danger">

            <div class="card-body">

                {{-- Low Stock Alerts --}}

                @if($lowStockProducts->count() > 0)

                <div class="alert alert-danger mt-4">
                    <h5>?? Low Stock Alerts</h5>

                    @foreach($lowStockProducts as $stock)

                        <div class="d-flex justify-content-between align-items-center mb-2">

                            <span>
                                {{ $stock->product->name }}
                            </span>

                            <span class="badge bg-danger">
                                Only {{ $stock->quantity_remaining }} Remaining
                            </span>

                        </div>

                    @endforeach

                </div>

                @else

                <div class="alert alert-success mt-4">
                    ? All stock levels are good.
                </div>

                @endif


            </div>

        </div>

    </div>

</div>

<script>

document.addEventListener("DOMContentLoaded", function () {

    const ctx =
        document.getElementById('salesChart');

    if (ctx) {

        new Chart(ctx, {

            type: 'line',

            data: {

                labels:
                    {!! json_encode($dates->toArray()) !!},

                datasets: [{

                    label:
                        'Daily Revenue',

                    data:
                        {!! json_encode($totals->toArray()) !!},

                    borderWidth: 2,

                    tension: 0.3

                }]

            },

            options: {

                responsive: true,

                plugins: {

                    legend: {

                        display: true

                    }

                },

                scales: {

                    y: {

                        beginAtZero: true

                    }

                }

            }

        });

    }

});

</script>

<script>

document.addEventListener("DOMContentLoaded", function () {

const pieCtx =
document.getElementById('productPieChart');

if (pieCtx) {

new Chart(pieCtx, {

    type: 'pie',

    data: {

        labels:
        {!! json_encode($productNames->toArray() ?? []) !!},

        datasets: [{

            data:
            {!! json_encode($productQuantities->toArray() ?? []) !!}

        }]

    },

    options: {

        responsive: true

    }

});

}

});

</script>

<script>

document.addEventListener("DOMContentLoaded", function () {

const barCtx =
document.getElementById('stockBarChart');

if (barCtx) {

new Chart(barCtx, {

    type: 'bar',

    data: {

        labels:
        {!! json_encode($stockLabels ?? []) !!},

        datasets: [{

            label: 'Stock Quantity',

            data: @json($stockData),
            backgroundColor: @json($stockColors),
            borderWidth: 1

        }]

    },

    options: {

        responsive: true,
        maintainAspectRatio: false

    }

});

}

});

</script>

<script>

document.addEventListener("DOMContentLoaded", function () {

// Monthly Profit Chart

var ctxProfit =
document.getElementById('profitChart')
.getContext('2d');

new Chart(ctxProfit, {

type: 'line',

data: {

labels: @json($profitLabels),

datasets: [{

label: 'Monthly Profit (KES)',

data: @json($profitData),

borderWidth: 2,

tension: 0.3

}]

},

options: {

responsive: true,

plugins: {

legend: {

display: true

}

}

}

});

});

</script>

<script>

document.addEventListener("DOMContentLoaded", function () {

var ctx = document.getElementById('monthlyProfitChart').getContext('2d');

var monthlyProfitChart = new Chart(ctx, {

    type: 'bar',

    data: {

        labels: @json($months),

        datasets: [{
            label: 'Monthly Profit',

            data: @json($profits),

            backgroundColor: [
                '#4CAF50',
                '#2196F3',
                '#FFC107',
                '#9C27B0',
                '#FF5722'
            ]

        }]
    }

});

});

</script>

@endsection
