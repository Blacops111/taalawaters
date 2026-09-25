<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\OperatingExpense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OperatingExpenseService
{
    public const EXPENSE_CODES = ['5200', '5300', '5400', '5500'];
    public const PAYMENT_CODES = ['1100', '1110'];

    public function record(User $user, array $input): OperatingExpense
    {
        $data = Validator::make($input, [
            'expense_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'payment_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:300'],
            'payee' => ['nullable', 'string', 'max:150'],
            'external_reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ])->validate();

        $description = trim($data['description']);
        if ($description === '') {
            throw ValidationException::withMessages(['description' => 'Describe what the expense was for.']);
        }
        // Normalize decimal strings without silently rounding fractional cents.
        [$whole, $fraction] = array_pad(explode('.', (string) $data['amount'], 2), 2, '');
        $amount = (string) ((int) $whole).'.'.str_pad($fraction, 2, '0');
        $attributes = [
            'expense_account_id' => (int) $data['expense_account_id'],
            'payment_account_id' => (int) $data['payment_account_id'],
            'expense_date' => $data['expense_date'],
            'amount' => $amount,
            'description' => $description,
            'payee' => $this->clean($data['payee'] ?? null),
            'external_reference' => $this->clean($data['external_reference'] ?? null),
            'notes' => $this->clean($data['notes'] ?? null),
            'created_by' => $user->id,
            'idempotency_key' => strtolower($data['idempotency_key']),
        ];

        return DB::transaction(function () use ($user, $attributes) {
            // Serialize this user's submissions, even when two requests choose different accounts.
            $actor = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->role === 'admin', 403);

            $existing = OperatingExpense::query()->where('created_by', $actor->id)
                ->where('idempotency_key', $attributes['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                foreach ($attributes as $field => $value) {
                    $actual = $field === 'expense_date'
                        ? $existing->expense_date->toDateString()
                        : $existing->getAttribute($field);
                    if ((string) $actual !== (string) $value) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This form already recorded a different expense. Reload the page to record another.',
                        ]);
                    }
                }

                return $existing;
            }

            $accounts = AccountingAccount::query()
                ->whereIn('id', [$attributes['expense_account_id'], $attributes['payment_account_id']])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $expenseAccount = $accounts->get($attributes['expense_account_id']);
            $paymentAccount = $accounts->get($attributes['payment_account_id']);

            if (! $expenseAccount || ! $expenseAccount->is_active
                || $expenseAccount->type !== AccountingAccount::TYPE_EXPENSE
                || ! in_array($expenseAccount->code, self::EXPENSE_CODES, true)) {
                throw ValidationException::withMessages([
                    'expense_account_id' => 'Choose an active fuel, utilities, repairs or general operating expense account.',
                ]);
            }
            if (! $paymentAccount || ! $paymentAccount->is_active
                || $paymentAccount->type !== AccountingAccount::TYPE_ASSET
                || ! in_array($paymentAccount->code, self::PAYMENT_CODES, true)) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'Pay expenses from an active Cash on Hand or Bank Account.',
                ]);
            }

            $expense = OperatingExpense::create($attributes);
            $expense->update(['reference' => 'EXP-'.str_pad((string) $expense->id, 8, '0', STR_PAD_LEFT)]);
            app(JournalPostingService::class)->post(
                $actor,
                $attributes['expense_date'],
                'Expense '.$expense->reference.': '.$attributes['description'],
                [
                    ['account_id' => $expenseAccount->id, 'debit' => $expense->amount, 'credit' => 0,
                        'memo' => $attributes['description']],
                    ['account_id' => $paymentAccount->id, 'debit' => 0, 'credit' => $expense->amount,
                        'memo' => 'Paid from '.$paymentAccount->name],
                ],
                $expense,
            );

            return $expense;
        }, 3);
    }

    private function clean(?string $value): ?string
    {
        return trim((string) $value) !== '' ? trim($value) : null;
    }
}
