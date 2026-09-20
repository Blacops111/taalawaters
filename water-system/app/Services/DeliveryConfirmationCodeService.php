<?php

namespace App\Services;

use App\Models\DeliveryNote;
use Illuminate\Support\Facades\Hash;

class DeliveryConfirmationCodeService
{
    public function generateFor(DeliveryNote $deliveryNote): string
    {
        $code = (string) random_int(100000, 999999);
        $generatedAt = now();
        $ttlMinutes = max(
            1,
            (int) config('delivery.confirmation_code_ttl_minutes', 180)
        );

        $deliveryNote->forceFill([
            'confirmation_code_hash' => Hash::make($code),
            'confirmation_code_generated_at' => $generatedAt,
            'confirmation_code_expires_at' => $generatedAt->copy()->addMinutes($ttlMinutes),
            'confirmation_code_last_sent_at' => null,
            'confirmation_code_failed_attempts' => 0,
            'confirmation_code_locked_at' => null,
            'confirmation_code_verified_at' => null,
            'confirmation_code_verified_by' => null,
        ])->save();

        return $code;
    }
}
