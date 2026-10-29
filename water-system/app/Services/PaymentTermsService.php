<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;

class PaymentTermsService
{
    public function dueDate(CarbonInterface $transactionAt, ?int $days, ?string $override): ?string
    {
        $date = $transactionAt->copy()->utc()->toDateString();
        Validator::make(['due_date' => $override, 'payment_terms_days' => $days], [
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$date],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ])->validate();

        return $override ?? ($days === null ? null : CarbonImmutable::parse($date, 'UTC')->addDays($days)->toDateString());
    }
}
