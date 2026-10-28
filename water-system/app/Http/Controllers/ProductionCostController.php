<?php

namespace App\Http\Controllers;

use App\Models\ProductionCostAllocation;
use Illuminate\Http\Request;

class ProductionCostController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150']]);
        $search = trim($data['search'] ?? '');
        $allocations = ProductionCostAllocation::with(['productionRun.reversal', 'productionRun.journalEntries', 'expense', 'expenseAccount'])
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->whereHas('expense', fn ($q) => $q->where('reference', 'like', '%'.$search.'%'))
                    ->orWhereHas('productionRun', fn ($q) => $q->where('reference', 'like', '%'.$search.'%'));
            }))->latest('id')->paginate(25)->withQueryString();
        return view('accounting.production-costs', compact('allocations', 'search'));
    }
}
