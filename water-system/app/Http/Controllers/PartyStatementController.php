<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Supplier;
use App\Services\PartyStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartyStatementController extends Controller
{
    public function customers(Request $request): View
    {
        return $this->directory($request, true);
    }

    public function suppliers(Request $request): View
    {
        return $this->directory($request, false);
    }

    public function customer(Request $request, Customer $customer, PartyStatementService $service): View
    {
        return $this->statement($request, $customer, $service);
    }

    public function supplier(Request $request, Supplier $supplier, PartyStatementService $service): View
    {
        return $this->statement($request, $supplier, $service);
    }

    private function directory(Request $request, bool $isCustomer): View
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $search = trim($data['search'] ?? '');
        $query = $isCustomer ? Customer::query() : Supplier::query();
        $parties = $query->when($search !== '', function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        })->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();

        return view('accounting.statements.index', compact('isCustomer', 'parties', 'search'));
    }

    private function statement(Request $request, Customer|Supplier $party, PartyStatementService $service): View
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $dateTo = $data['date_to'] ?? now()->utc()->toDateString();
        $dateFrom = $data['date_from'] ?? CarbonImmutable::parse($dateTo, 'UTC')->startOfMonth()->toDateString();
        return view('accounting.statements.show', $service->report($party, $dateFrom, $dateTo, (int) ($data['page'] ?? 1)));
    }
}
