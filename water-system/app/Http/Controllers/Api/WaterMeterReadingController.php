<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaterMeter;
use App\Services\WaterMeterReadingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaterMeterReadingController extends Controller
{
    public function store(
        Request $request,
        WaterMeterReadingService $service
    ): JsonResponse {
        $validated = $request->validate([
            'meter_id' => ['required', 'uuid'],
            'reading_value' => ['required', 'numeric', 'min:0'],
            'reading_at' => ['required', 'date'],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        $meter = WaterMeter::query()
            ->where('public_id', $validated['meter_id'])
            ->where('is_active', true)
            ->first();

        if (!$meter) {
            return response()->json([
                'message' => 'Water meter not found or inactive.',
            ], 404);
        }

        $token = (string) $request->header('X-Meter-Token', '');
        $providedHash = hash('sha256', $token);

        if ($token === '' || !hash_equals($meter->token_hash, $providedHash)) {
            return response()->json([
                'message' => 'Invalid meter credentials.',
            ], 401);
        }

        $result = $service->ingest($meter, $validated);
        $reading = $result['reading'];

        if ($result['duplicate']) {
            $httpStatus = 200;
            $message = 'Reading already received. No duplicate stock movement was created.';
        } elseif ($reading->status === 'needs_review') {
            $httpStatus = 202;
            $message = 'Reading stored for review. Raw-water stock was not changed.';
        } elseif ($reading->status === 'baseline') {
            $httpStatus = 201;
            $message = 'Baseline reading recorded. Future meter increases will update raw-water stock automatically.';
        } else {
            $httpStatus = 201;
            $message = 'Reading accepted and raw-water stock updated automatically.';
        }

        return response()->json([
            'message' => $message,
            'reading_id' => $reading->id,
            'status' => $reading->status,
            'reading_litres' => (float) $reading->normalized_litres,
            'delta_litres' => $reading->delta_litres === null
                ? null
                : (float) $reading->delta_litres,
            'review_reason' => $reading->review_reason,
        ], $httpStatus);
    }
}
