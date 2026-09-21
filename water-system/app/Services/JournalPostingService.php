<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class JournalPostingService
{
    public function post(
        User $user,
        CarbonInterface|string $entryDate,
        string $description,
        array $lines,
        ?Model $source = null,
    ): JournalEntry {
        if ($source && ! $source->exists) {
            throw ValidationException::withMessages([
                'source' => 'The journal source must already exist before posting.',
            ]);
        }

        $entryDateValue = $entryDate instanceof CarbonInterface
            ? $entryDate->toDateString()
            : $entryDate;

        $validated = Validator::make([
            'entry_date' => $entryDateValue,
            'description' => trim($description),
            'lines' => $lines,
        ], [
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'min:1'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $normalizedLines = [];
        $totalDebitCents = 0;
        $totalCreditCents = 0;

        foreach ($validated['lines'] as $index => $line) {
            $debitCents = $this->toCents($line['debit'] ?? 0);
            $creditCents = $this->toCents($line['credit'] ?? 0);

            if (
                ($debitCents > 0 && $creditCents > 0)
                || ($debitCents === 0 && $creditCents === 0)
            ) {
                throw ValidationException::withMessages([
                    'lines.'.$index => 'Each journal line must contain either a debit or a credit, but not both.',
                ]);
            }

            $totalDebitCents += $debitCents;
            $totalCreditCents += $creditCents;

            $normalizedLines[] = [
                'account_id' => (int) $line['account_id'],
                'debit' => $this->fromCents($debitCents),
                'credit' => $this->fromCents($creditCents),
                'memo' => isset($line['memo']) && trim((string) $line['memo']) !== ''
                    ? trim((string) $line['memo'])
                    : null,
            ];
        }

        if ($totalDebitCents <= 0 || $totalDebitCents !== $totalCreditCents) {
            throw ValidationException::withMessages([
                'lines' => 'Journal entries must have equal non-zero total debits and total credits.',
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $validated,
            $normalizedLines,
            $source,
        ) {
            $accountIds = collect($normalizedLines)
                ->pluck('account_id')
                ->unique()
                ->sort()
                ->values();

            $accounts = AccountingAccount::query()
                ->whereIn('id', $accountIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($accounts->count() !== $accountIds->count()) {
                throw ValidationException::withMessages([
                    'lines' => 'One or more accounting accounts do not exist.',
                ]);
            }

            foreach ($accountIds as $accountId) {
                if (! $accounts->get($accountId)->is_active) {
                    throw ValidationException::withMessages([
                        'lines' => 'Inactive accounting accounts cannot be used in new journal entries.',
                    ]);
                }
            }

            $journalEntry = JournalEntry::create([
                'reference' => null,
                'entry_date' => CarbonImmutable::parse($validated['entry_date'])->toDateString(),
                'status' => JournalEntry::STATUS_DRAFT,
                'description' => $validated['description'],
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'posted_by' => $user->id,
                'posted_at' => null,
            ]);

            foreach ($normalizedLines as $line) {
                $journalEntry->lines()->create([
                    'accounting_account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'memo' => $line['memo'],
                ]);
            }

            $journalEntry->update([
                'reference' => 'JRN-'.str_pad((string) $journalEntry->id, 8, '0', STR_PAD_LEFT),
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            return $journalEntry->fresh([
                'lines.account',
                'postedBy',
                'source',
            ]);
        }, 3);
    }

    private function toCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
