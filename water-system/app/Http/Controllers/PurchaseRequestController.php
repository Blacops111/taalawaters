<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseRequestController extends Controller
{
    public function index()
    {
        $purchaseRequests = PurchaseRequest::query()
            ->with([
                'supplier:id,name,is_active',
                'requester:id,name',
            ])
            ->withCount('items')
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('purchase-requests.index', compact('purchaseRequests'));
    }

    public function create()
    {
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('purchase-requests.create', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
            'requested_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $purchaseRequest = DB::transaction(function () use ($validated) {
            $purchaseRequest = PurchaseRequest::create([
                'reference' => null,
                'status' => PurchaseRequest::STATUS_DRAFT,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'requested_by' => auth()->id(),
                'requested_at' => $validated['requested_at'],
                'notes' => isset($validated['notes']) && trim($validated['notes']) !== ''
                    ? trim($validated['notes'])
                    : null,
            ]);

            $purchaseRequest->update([
                'reference' => 'PREQ-'.str_pad(
                    (string) $purchaseRequest->id,
                    8,
                    '0',
                    STR_PAD_LEFT
                ),
            ]);

            return $purchaseRequest;
        }, 3);

        return redirect()
            ->route('purchase-requests.index')
            ->with(
                'success',
                $purchaseRequest->reference.' was created as a draft purchase request.'
            );
    }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load([
            'supplier:id,name,is_active',
            'requester:id,name',
            'submitter:id,name',
            'approver:id,name',
            'rejector:id,name',
            'items.inventoryItem:id,sku,name,category,unit',
        ]);

        $purchasableItems = collect();
        $reviewSuppliers = collect();

        if ($purchaseRequest->status === PurchaseRequest::STATUS_DRAFT) {
            $purchasableItems = InventoryItem::query()
                ->where('is_active', true)
                ->whereNotIn('category', ['raw_water', 'finished_product'])
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'category', 'unit']);
        }

        if ($purchaseRequest->status === PurchaseRequest::STATUS_SUBMITTED) {
            $reviewSuppliers = Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view(
            'purchase-requests.edit',
            compact('purchaseRequest', 'purchasableItems', 'reviewSuppliers')
        );
    }

    public function storeItem(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->ensureDraft($purchaseRequest);

        $validated = $request->validate([
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->whereNotIn('category', ['raw_water', 'finished_product'])
                ),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
        ]);

        DB::transaction(function () use ($purchaseRequest, $validated) {
            $lockedRequest = PurchaseRequest::query()
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDraft($lockedRequest);

            $alreadyExists = PurchaseRequestItem::query()
                ->where('purchase_request_id', $lockedRequest->id)
                ->where('inventory_item_id', $validated['inventory_item_id'])
                ->exists();

            if ($alreadyExists) {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'This material is already on the purchase request.',
                ]);
            }

            $lockedRequest->items()->create([
                'inventory_item_id' => $validated['inventory_item_id'],
                'quantity' => $validated['quantity'],
            ]);
        }, 3);

        return redirect()
            ->route('purchase-requests.edit', $purchaseRequest)
            ->with('success', 'Requested material was added.');
    }

    public function submit(PurchaseRequest $purchaseRequest)
    {
        DB::transaction(function () use ($purchaseRequest) {
            $lockedRequest = PurchaseRequest::query()
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDraft($lockedRequest);

            $hasItems = PurchaseRequestItem::query()
                ->where('purchase_request_id', $lockedRequest->id)
                ->exists();

            if (!$hasItems) {
                throw ValidationException::withMessages([
                    'purchase_request' => 'Add at least one requested material before submitting for approval.',
                ]);
            }

            $lockedRequest->update([
                'status' => PurchaseRequest::STATUS_SUBMITTED,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);
        }, 3);

        return redirect()
            ->route('purchase-requests.edit', $purchaseRequest)
            ->with('success', 'Purchase request was submitted for approval.');
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->ensureSubmitted($purchaseRequest);

        $validated = $request->validate([
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
            'approved_unit_costs' => ['required', 'array'],
            'approved_unit_costs.*' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999999',
            ],
        ]);

        DB::transaction(function () use ($purchaseRequest, $validated) {
            $lockedRequest = PurchaseRequest::query()
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureSubmitted($lockedRequest);

            $requestItems = PurchaseRequestItem::query()
                ->where('purchase_request_id', $lockedRequest->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($requestItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_request' => 'A purchase request must contain at least one material before approval.',
                ]);
            }

            foreach ($requestItems as $requestItem) {
                $rawCost = $validated['approved_unit_costs'][$requestItem->id]
                    ?? $validated['approved_unit_costs'][(string) $requestItem->id]
                    ?? null;

                if ($rawCost === null || (float) $rawCost <= 0) {
                    throw ValidationException::withMessages([
                        'approved_unit_costs.'.$requestItem->id
                            => 'Enter an approved unit cost greater than zero for every material.',
                    ]);
                }

                $requestItem->update([
                    'approved_unit_cost' => number_format(
                        round((float) $rawCost, 2),
                        2,
                        '.',
                        ''
                    ),
                ]);
            }

            $lockedRequest->update([
                'status' => PurchaseRequest::STATUS_APPROVED,
                'supplier_id' => $validated['supplier_id'],
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
        }, 3);

        return redirect()
            ->route('purchase-requests.edit', $purchaseRequest)
            ->with('success', 'Purchase request was approved. Inventory will change only when goods are received.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->ensureSubmitted($purchaseRequest);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($purchaseRequest, $validated) {
            $lockedRequest = PurchaseRequest::query()
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureSubmitted($lockedRequest);

            $lockedRequest->update([
                'status' => PurchaseRequest::STATUS_REJECTED,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => trim($validated['rejection_reason']),
            ]);
        }, 3);

        return redirect()
            ->route('purchase-requests.edit', $purchaseRequest)
            ->with('success', 'Purchase request was rejected.');
    }

    public function destroyItem(
        PurchaseRequest $purchaseRequest,
        PurchaseRequestItem $purchaseRequestItem
    ) {
        DB::transaction(function () use ($purchaseRequest, $purchaseRequestItem) {
            $lockedRequest = PurchaseRequest::query()
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureDraft($lockedRequest);

            $item = PurchaseRequestItem::query()
                ->whereKey($purchaseRequestItem->id)
                ->where('purchase_request_id', $lockedRequest->id)
                ->firstOrFail();

            $item->delete();
        }, 3);

        return redirect()
            ->route('purchase-requests.edit', $purchaseRequest)
            ->with('success', 'Requested material was removed.');
    }

    private function ensureDraft(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->status !== PurchaseRequest::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'purchase_request' => 'Only draft purchase requests can be changed.',
            ]);
        }
    }

    private function ensureSubmitted(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->status !== PurchaseRequest::STATUS_SUBMITTED) {
            throw ValidationException::withMessages([
                'purchase_request' => 'Only submitted purchase requests can be approved or rejected.',
            ]);
        }
    }
}
