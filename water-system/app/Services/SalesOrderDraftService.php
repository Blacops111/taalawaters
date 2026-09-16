<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class SalesOrderDraftService
{
    public function createDraft(
        string $saleType,
        ?Customer $customer,
        User $user,
        ?CarbonInterface $saleAt = null,
        ?string $notes = null,
    ): SalesOrder {
        $saleType = trim($saleType);

        if (! in_array($saleType, [SalesOrder::TYPE_WALK_IN, SalesOrder::TYPE_BUSINESS], true)) {
            throw ValidationException::withMessages([
                'sale_type' => 'Sale type must be walk-in or business customer.',
            ]);
        }

        if ($saleType === SalesOrder::TYPE_BUSINESS) {
            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'A business customer is required for a business customer sale.',
                ]);
            }

            if (! $customer->is_active) {
                throw ValidationException::withMessages([
                    'customer_id' => 'The selected business customer is inactive.',
                ]);
            }
        }

        if ($saleType === SalesOrder::TYPE_WALK_IN && $customer) {
            throw ValidationException::withMessages([
                'customer_id' => 'Walk-in sales must not be linked to a business customer account.',
            ]);
        }

        $saleAt = ($saleAt ?? now())->copy()->utc();
        $notes = is_string($notes) ? trim($notes) : null;

        return SalesOrder::create([
            'customer_id' => $saleType === SalesOrder::TYPE_BUSINESS ? $customer?->id : null,
            'sale_type' => $saleType,
            'reference' => null,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => $saleAt,
            'total_amount' => 0,
            'created_by' => $user->id,
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }
}
