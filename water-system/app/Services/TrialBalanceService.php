<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    public function report(string $asOf): array
    {
        // Aggregate each account once, including direct postings to parent or inactive accounts.
        // Convert individual fixed-point amounts to cents before summing.
        $rows = DB::table('journal_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->join('accounting_accounts as accounts', 'accounts.id', '=', 'lines.accounting_account_id')
            ->where('entries.status', JournalEntry::STATUS_POSTED)
            ->where('entries.entry_date', '<=', $asOf)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.is_active')
            ->orderBy('accounts.code')
            ->select('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.is_active')
            ->selectRaw('SUM(CAST(ROUND(lines.debit * 100, 0) AS SIGNED)) as posted_debit_cents')
            ->selectRaw('SUM(CAST(ROUND(lines.credit * 100, 0) AS SIGNED)) as posted_credit_cents')
            ->get()
            ->map(function ($account) {
                $net = (int) $account->posted_debit_cents - (int) $account->posted_credit_cents;
                $account->debit_cents = max(0, $net);
                $account->credit_cents = max(0, -$net);

                return $account;
            });

        $totalDebitCents = (int) $rows->sum('debit_cents');
        $totalCreditCents = (int) $rows->sum('credit_cents');

        return [
            'asOf' => $asOf,
            'rows' => $rows,
            'totalDebitCents' => $totalDebitCents,
            'totalCreditCents' => $totalCreditCents,
            'differenceCents' => abs($totalDebitCents - $totalCreditCents),
            'isBalanced' => $totalDebitCents === $totalCreditCents,
        ];
    }
}
