<?php

namespace App\Http\Controllers;

use App\Models\ProductionRecipe;
use App\Models\ProductionRun;
use App\Services\ProductionRunService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ProductionRunController extends Controller
{
    public function create()
    {
        $recipes = ProductionRecipe::query()
            ->where('is_active', true)
            ->whereHas('finishedProduct', function ($query) {
                $query->where('is_active', true)
                    ->where('category', 'finished_product');
            })
            ->with(['finishedProduct', 'components.inventoryItem'])
            ->get()
            ->sortBy(fn ($recipe) => $recipe->finishedProduct->name)
            ->values();

        $recentRuns = ProductionRun::query()
            ->with(['finishedProduct', 'creator'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('production.runs.create', compact('recipes', 'recentRuns'));
    }

    public function store(Request $request, ProductionRunService $productionRunService)
    {
        $validated = $request->validate([
            'production_recipe_id' => ['required', 'integer', 'exists:production_recipes,id'],
            'quantity_produced' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'occurred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $recipe = ProductionRecipe::query()->findOrFail($validated['production_recipe_id']);

        $occurredAt = CarbonImmutable::parse(
            $validated['occurred_at'],
            config('app.timezone')
        )->utc();

        $run = $productionRunService->complete(
            $recipe,
            (float) $validated['quantity_produced'],
            $request->user(),
            $occurredAt,
            $validated['notes'] ?? null
        );

        return redirect()
            ->route('production.runs.create')
            ->with(
                'success',
                $run->reference.' completed. '.number_format((float) $run->quantity_produced, 0).' '.$run->finishedProduct->unit.' of '.$run->finishedProduct->name.' added to finished stock.'
            );
    }
}
