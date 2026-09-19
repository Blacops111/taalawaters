<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
}
