<?php

namespace App\Http\Controllers;

use App\Models\{Customer, Supplier};
use App\Services\PartyAgeingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartyAgeingController extends Controller
{
    public function customers(Request $request, PartyAgeingService $service): View
    {
        return $this->show($request, $service, true);
    }

    public function suppliers(Request $request, PartyAgeingService $service): View
    {
        return $this->show($request, $service, false);
    }

    private function show(Request $request, PartyAgeingService $service, bool $isCustomer): View
    {
        $data = $request->validate([
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'party_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $report = $service->ageing($isCustomer, $data['as_of'] ?? now()->utc()->toDateString(),
            isset($data['party_id']) ? (int) $data['party_id'] : null, (int) ($data['page'] ?? 1));
        $parties = ($isCustomer ? Customer::query() : Supplier::query())->orderBy('name')->orderBy('id')->get(['id', 'name', 'is_active']);

        return view('accounting.ageing', $report + compact('parties'));
    }
}
