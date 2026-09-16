<?php

namespace App\Http\Controllers;

use App\Exports\V2SalesReportExport;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\SalesOrderCompletionService;
use App\Services\SalesOrderDraftItemService;
use App\Services\SalesOrderDraftService;
use App\Services\SalesOrderReversalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SalesOrderController extends Controller
{
    public function create()
    {
        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $recentDrafts = SalesOrder::query()
            ->with(['customer', 'creator'])
            ->withCount('items')
            ->where('status', SalesOrder::STATUS_DRAFT)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('sales-orders.create', compact('customers', 'recentDrafts'));
    }

    public function history(Request $request)
    {
        $filters = $request->validate([
            'reference' => ['nullable', 'string', 'max:150'],
            'sale_type' => ['nullable', Rule::in([
                SalesOrder::TYPE_WALK_IN,
                SalesOrder::TYPE_BUSINESS,
            ])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $completedSales = SalesOrder::query()
            ->with([
                'customer',
                'creator',
                'items.inventoryItem',
                'reversal.reversedBy',
            ])
            ->whereIn('status', [
                SalesOrder::STATUS_COMPLETED,
                SalesOrder::STATUS_REVERSED,
            ])
            ->when(
                filled($filters['reference'] ?? null),
                fn ($query) => $query->where(
                    'reference',
                    'like',
                    '%'.trim($filters['reference']).'%'
                )
            )
            ->when(
                filled($filters['sale_type'] ?? null),
                fn ($query) => $query->where('sale_type', $filters['sale_type'])
            )
            ->when(
                filled($filters['customer_id'] ?? null),
                fn ($query) => $query->where('customer_id', $filters['customer_id'])
            )
            ->when(
                filled($filters['date_from'] ?? null),
                fn ($query) => $query->whereDate('sale_at', '>=', $filters['date_from'])
            )
            ->when(
                filled($filters['date_to'] ?? null),
                fn ($query) => $query->whereDate('sale_at', '<=', $filters['date_to'])
            )
            ->orderByDesc('sale_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $customers = Customer::query()
            ->whereHas('salesOrders', function ($query) {
                $query->whereIn('status', [
                    SalesOrder::STATUS_COMPLETED,
                    SalesOrder::STATUS_REVERSED,
                ]);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('sales-orders.history', compact('completedSales', 'customers', 'filters'));
    }

    public function report(Request $request)
    {
        $filters = $this->validateReportFilters($request);

        return view('sales-orders.report', $this->salesReportData($filters));
    }

    public function reportPdf(Request $request)
    {
        $filters = $this->validateReportFilters($request);
        $data = $this->salesReportData($filters);

        $pdf = Pdf::loadView('sales-orders.report-pdf', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download('taala_v2_sales_report.pdf');
    }

    public function reportExcel(Request $request)
    {
        $filters = $this->validateReportFilters($request);
        $spreadsheet = (new V2SalesReportExport(
            $this->salesReportData($filters)
        ))->build();

        return response()->streamDownload(
            function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
                $spreadsheet->disconnectWorksheets();
            },
            'taala_v2_sales_report.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }

    public function show(SalesOrder $salesOrder)
    {
        if (! in_array($salesOrder->status, [
            SalesOrder::STATUS_COMPLETED,
            SalesOrder::STATUS_REVERSED,
        ], true)) {
            abort(404);
        }

        $salesOrder->load([
            'customer',
            'creator',
            'items.inventoryItem',
            'stockMovements.inventoryItem',
            'stockMovements.creator',
            'reversal.reversedBy',
            'reversal.stockMovements.inventoryItem',
            'reversal.stockMovements.creator',
        ]);

        return view('sales-orders.show', compact('salesOrder'));
    }

    public function reversal(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== SalesOrder::STATUS_COMPLETED) {
            abort(404);
        }

        $salesOrder->load([
            'customer',
            'creator',
            'items.inventoryItem',
        ]);

        return view('sales-orders.reversal', compact('salesOrder'));
    }

    public function reverse(
        Request $request,
        SalesOrder $salesOrder,
        SalesOrderReversalService $reversalService,
    ) {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $reversal = $reversalService->reverse(
            $salesOrder,
            $request->user(),
            $validated['reason'],
        );

        return redirect()
            ->route('sales-orders.show', $salesOrder)
            ->with(
                'success',
                'Sale '.$salesOrder->reference.' was reversed successfully as '.$reversal->reference.'. Finished stock was restored.'
            );
    }

    public function store(Request $request, SalesOrderDraftService $draftService)
    {
        $validated = $request->validate([
            'sale_type' => ['required', Rule::in([
                SalesOrder::TYPE_WALK_IN,
                SalesOrder::TYPE_BUSINESS,
            ])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sale_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = isset($validated['customer_id'])
            ? Customer::find($validated['customer_id'])
            : null;

        $order = $draftService->createDraft(
            $validated['sale_type'],
            $customer,
            $request->user(),
            Carbon::parse($validated['sale_at']),
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('sales-orders.create')
            ->with('success', 'Draft sales order #'.$order->id.' was created successfully.');
    }

    public function edit(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== SalesOrder::STATUS_DRAFT) {
            abort(404);
        }

        $salesOrder->load([
            'customer',
            'creator',
            'items.inventoryItem',
        ]);

        $priceColumn = $salesOrder->sale_type === SalesOrder::TYPE_BUSINESS
            ? 'wholesale_price'
            : 'retail_price';

        $finishedProducts = InventoryItem::query()
            ->where('category', 'finished_product')
            ->where('is_sellable', true)
            ->where('is_active', true)
            ->whereNotNull($priceColumn)
            ->orderBy('name')
            ->get();

        return view('sales-orders.edit', compact('salesOrder', 'finishedProducts', 'priceColumn'));
    }

    public function storeItem(
        Request $request,
        SalesOrder $salesOrder,
        SalesOrderDraftItemService $itemService,
    ) {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999999'],
        ]);

        $inventoryItem = InventoryItem::findOrFail($validated['inventory_item_id']);

        $itemService->addItem(
            $salesOrder,
            $inventoryItem,
            (int) $validated['quantity'],
        );

        return redirect()
            ->route('sales-orders.edit', $salesOrder)
            ->with('success', 'Product was added to draft sales order #'.$salesOrder->id.' using the configured sales price.');
    }

    public function destroyItem(
        SalesOrder $salesOrder,
        SalesOrderItem $salesOrderItem,
        SalesOrderDraftItemService $itemService,
    ) {
        $itemService->removeItem($salesOrder, $salesOrderItem);

        return redirect()
            ->route('sales-orders.edit', $salesOrder)
            ->with('success', 'Product was removed from draft sales order #'.$salesOrder->id.' and the draft total was recalculated.');
    }

    public function complete(
        Request $request,
        SalesOrder $salesOrder,
        SalesOrderCompletionService $completionService,
    ) {
        $completedOrder = $completionService->complete(
            $salesOrder,
            $request->user(),
        );

        return redirect()
            ->route('sales-orders.create')
            ->with(
                'success',
                'Sale '.$completedOrder->reference.' was completed successfully and inventory was deducted.'
            );
    }

    private function validateReportFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    private function salesReportData(array $filters): array
    {
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $salesQuery = SalesOrder::query()
            ->where('status', SalesOrder::STATUS_COMPLETED)
            ->when(
                filled($dateFrom),
                fn ($query) => $query->whereDate('sale_at', '>=', $dateFrom)
            )
            ->when(
                filled($dateTo),
                fn ($query) => $query->whereDate('sale_at', '<=', $dateTo)
            );

        $summary = (clone $salesQuery)
            ->selectRaw(
                'COUNT(*) as completed_sales_count,
                 COALESCE(SUM(total_amount), 0) as total_sales_value,
                 COALESCE(SUM(CASE WHEN sale_type = ? THEN total_amount ELSE 0 END), 0) as walk_in_total,
                 COALESCE(SUM(CASE WHEN sale_type = ? THEN total_amount ELSE 0 END), 0) as business_total',
                [SalesOrder::TYPE_WALK_IN, SalesOrder::TYPE_BUSINESS]
            )
            ->first();

        $itemQuery = SalesOrderItem::query()
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->where('sales_orders.status', SalesOrder::STATUS_COMPLETED)
            ->when(
                filled($dateFrom),
                fn ($query) => $query->whereDate('sales_orders.sale_at', '>=', $dateFrom)
            )
            ->when(
                filled($dateTo),
                fn ($query) => $query->whereDate('sales_orders.sale_at', '<=', $dateTo)
            );

        $totalUnitsSold = (float) (clone $itemQuery)
            ->sum('sales_order_items.quantity');

        $productBreakdown = (clone $itemQuery)
            ->join('inventory_items', 'sales_order_items.inventory_item_id', '=', 'inventory_items.id')
            ->select([
                'inventory_items.id',
                'inventory_items.sku',
                'inventory_items.name',
            ])
            ->selectRaw('SUM(sales_order_items.quantity) as units_sold')
            ->selectRaw('SUM(sales_order_items.line_total) as sales_value')
            ->groupBy(
                'inventory_items.id',
                'inventory_items.sku',
                'inventory_items.name'
            )
            ->orderByDesc('sales_value')
            ->orderBy('inventory_items.name')
            ->get();

        return compact(
            'filters',
            'summary',
            'totalUnitsSold',
            'productBreakdown'
        );
    }
}
