<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\WaterMeter;
use App\Models\WaterMeterReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WaterMeterReadingService
{
    /**
     * @return array{reading: WaterMeterReading, duplicate: bool}
     */
    public function ingest(WaterMeter $meter, array $payload): array
    {
        return DB::transaction(function () use ($meter, $payload) {
            $lockedMeter = WaterMeter::query()
                ->whereKey($meter->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = WaterMeterReading::query()
                ->where('water_meter_id', $lockedMeter->id)
                ->where('idempotency_key', $payload['idempotency_key'])
                ->first();

            if ($existing) {
                return [
                    'reading' => $existing,
                    'duplicate' => true,
                ];
            }

            // Normalize incoming timestamps to UTC before persistence/comparison.
            // Database datetime columns do not preserve timezone offsets, so storing
            // an offset timestamp directly can make a later reading appear older.
            $readingAt = CarbonImmutable::parse($payload['reading_at'])->utc();

            $normalizedLitres = $this->toLitres(
                (float) $payload['reading_value'],
                $lockedMeter->reading_unit
            );

            $previous = WaterMeterReading::query()
                ->where('water_meter_id', $lockedMeter->id)
                ->whereIn('status', ['baseline', 'accepted'])
                ->orderByDesc('reading_at')
                ->orderByDesc('id')
                ->first();

            $status = 'accepted';
            $reviewReason = null;
            $deltaLitres = 0.0;

            if (!$previous) {
                $status = 'baseline';
            } elseif ($readingAt->lessThanOrEqualTo($previous->reading_at)) {
                $status = 'needs_review';
                $reviewReason = 'Reading timestamp is not newer than the last accepted reading.';
                $deltaLitres = null;
            } else {
                $previousLitres = (float) $previous->normalized_litres;

                if ($normalizedLitres < $previousLitres) {
                    $status = 'needs_review';
                    $reviewReason = 'Meter value moved backwards. The meter may have reset or rolled over.';
                    $deltaLitres = null;
                } else {
                    $deltaLitres = round($normalizedLitres - $previousLitres, 3);

                    if ($lockedMeter->max_flow_litres_per_minute !== null) {
                        $elapsedSeconds = max(
                            1,
                            $previous->reading_at->diffInSeconds($readingAt)
                        );
                        $elapsedMinutes = $elapsedSeconds / 60;
                        $maximumExpected =
                            (float) $lockedMeter->max_flow_litres_per_minute
                            * $elapsedMinutes
                            * 1.15;

                        if ($deltaLitres > $maximumExpected) {
                            $status = 'needs_review';
                            $reviewReason = 'Reading exceeds the configured maximum flow rate.';
                            $deltaLitres = null;
                        }
                    }
                }
            }

            $reading = WaterMeterReading::create([
                'water_meter_id' => $lockedMeter->id,
                'idempotency_key' => $payload['idempotency_key'],
                'reading_value' => $payload['reading_value'],
                'normalized_litres' => $normalizedLitres,
                'delta_litres' => $deltaLitres,
                'status' => $status,
                'review_reason' => $reviewReason,
                'reading_at' => $readingAt,
                'received_at' => now()->utc(),
                'metadata' => $payload['metadata'] ?? null,
            ]);

            if ($status === 'accepted' && $deltaLitres > 0) {
                StockMovement::create([
                    'inventory_item_id' => $lockedMeter->inventory_item_id,
                    'movement_type' => 'borehole_extraction',
                    'quantity_delta' => $deltaLitres,
                    'source_type' => WaterMeterReading::class,
                    'source_id' => $reading->id,
                    'occurred_at' => $readingAt,
                    'reference' => $lockedMeter->public_id.':'.$payload['idempotency_key'],
                    'notes' => 'Automatically created from borehole water meter reading.',
                ]);
            }

            $lockedMeter->last_seen_at = now()->utc();

            if (in_array($status, ['baseline', 'accepted'], true)) {
                $lockedMeter->last_reading_litres = $normalizedLitres;
                $lockedMeter->last_reading_at = $readingAt;
            }

            $lockedMeter->save();

            return [
                'reading' => $reading,
                'duplicate' => false,
            ];
        }, 3);
    }

    private function toLitres(float $value, string $unit): float
    {
        $normalizedUnit = strtolower(trim($unit));

        return match ($normalizedUnit) {
            'litre', 'liter', 'litres', 'liters', 'l' => round($value, 3),
            'm3', 'm³', 'cubic_meter', 'cubic_metre' => round($value * 1000, 3),
            default => throw new InvalidArgumentException(
                "Unsupported water meter unit: {$unit}"
            ),
        };
    }
}
