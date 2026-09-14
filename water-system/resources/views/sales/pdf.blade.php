<!DOCTYPE html>
<html>
<head>

    <title>Sales Report</title>

    <style>

        body {
            font-family: Arial, sans-serif;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table, th, td {
            border: 1px solid black;
        }

        th, td {
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

    </style>

</head>

<body>

<h2>Taala Water Sales Report</h2>

<table>

    <thead>

        <tr>

            <th>Product</th>
            <th>Quantity Sold</th>
            <th>Total Price</th>
            <th>Date</th>

        </tr>

    </thead>

    <tbody>

        @foreach($sales as $sale)

        <tr>

            <td>{{ $sale->product->name ?? 'N/A' }}</td>
            <td>{{ $sale->quantity_sold }}</td>
            <td>{{ $sale->total_amount }}</td>
            <td>{{ $sale->created_at->format('Y-m-d') }}</td>

        </tr>

        @endforeach

    </tbody>

</table>

</body>
</html>
