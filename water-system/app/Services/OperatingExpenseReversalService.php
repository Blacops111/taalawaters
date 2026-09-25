<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\OperatingExpense;
use App\Models\OperatingExpenseReversal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OperatingExpenseReversalService
{
    public function reverse(OperatingExpense $expense, User $user, array $input): OperatingExpenseReversal
    {
        abort_unless($user->role === 'admin', 403);
        $data = Validator::make($input, [
            'reason' => ['required', 'string', 'max:300'],
            'reversal_date' => ['required', 'date_format:Y-m-d'],
        ])->validate();
        $reason = trim($data['reason']);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to reverse an expense.']);
        }

        return DB::transaction(function () use ($expense, $user, $data, $reason) {
            // Serialize reversals for this expense; uniqueness also protects the audit record.
            $lockedExpense = OperatingExpense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($lockedExpense->reversal()->exists()) {
                throw ValidationException::withMessages(['expense' => 'This expense has already been reversed.']);
            }
            $journals = $lockedExpense->journalEntries()->where('status', JournalEntry::STATUS_POSTED)
                ->with('lines')->lockForUpdate()->get();
            if ($journals->count() !== 1) {
                throw ValidationException::withMessages(['accounting' => 'The expense must have exactly one posted original journal to reverse.']);
            }
            $original = $journals->first();
            if ($original->reversals()->exists()) {
                throw ValidationException::withMessages(['expense' => 'The original expense journal has already been reversed.']);
            }
            if ($data['reversal_date'] < $original->entry_date->toDateString()
                || $data['reversal_date'] < $lockedExpense->expense_date->toDateString()) {
                throw ValidationException::withMessages(['reversal_date' => 'The reversal date cannot be before the original expense or journal date.']);
            }
            $expenseLine = $original->lines->firstWhere('accounting_account_id', $lockedExpense->expense_account_id);
            $paymentLine = $original->lines->firstWhere('accounting_account_id', $lockedExpense->payment_account_id);
            if ($original->lines->count() !== 2 || ! $expenseLine || ! $paymentLine
                || $expenseLine->id === $paymentLine->id
                || $expenseLine->debit !== $lockedExpense->amount || $expenseLine->credit !== '0.00'
                || $paymentLine->credit !== $lockedExpense->amount || $paymentLine->debit !== '0.00') {
                throw ValidationException::withMessages(['accounting' => 'The original journal does not match the recorded expense and cannot be reversed safely.']);
            }

            $reversal = OperatingExpenseReversal::create([
                'reference' => 'REV-'.$lockedExpense->reference,
                'operating_expense_id' => $lockedExpense->id,
                'original_journal_id' => $original->id,
                'reversal_date' => $data['reversal_date'],
                'reason' => $reason,
                'reversed_by' => $user->id,
                'reversed_at' => now(),
            ]);
            $lines = $original->lines->map(fn ($line) => [
                'account_id' => $line->accounting_account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'memo' => 'Reversal of '.$original->reference,
            ])->all();
            app(JournalPostingService::class)->post(
                $user, $data['reversal_date'],
                'Expense reversal '.$reversal->reference.'. Reason: '.$reason,
                $lines, $reversal, $original,
            );

            return $reversal;
        }, 3);
    }
}
