<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProfitLossService
{
    public function report(string $dateFrom, string $dateTo): array
    {
        Validator::make(['date_from' => $dateFrom, 'date_to' => $dateTo], [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ])->validate();

        $rows = DB::table('journal_lines as lines')
            ->join('journal_entries as entries', 'entries.id', '=', 'lines.journal_entry_id')
            ->join('accounting_accounts as accounts', 'accounts.id', '=', 'lines.accounting_account_id')
            ->where('entries.status', JournalEntry::STATUS_POSTED)
            ->whereIn('accounts.type', [AccountingAccount::TYPE_REVENUE, AccountingAccount::TYPE_EXPENSE])
            ->whereDate('entries.entry_date', '>=', $dateFrom)
            ->whereDate('entries.entry_date', '<=', $dateTo)
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.is_active')
            ->orderBy('accounts.code')
            ->select('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.is_active')
            ->selectRaw('SUM(CAST(ROUND(lines.debit * 100, 0) AS SIGNED)) as debit_cents')
            ->selectRaw('SUM(CAST(ROUND(lines.credit * 100, 0) AS SIGNED)) as credit_cents')
            ->get()->map(function ($row) {
                $row->amount_cents = $row->type === AccountingAccount::TYPE_REVENUE
                    ? (int) $row->credit_cents - (int) $row->debit_cents
                    : (int) $row->debit_cents - (int) $row->credit_cents;

                return $row;
            });
        $revenueRows = $rows->where('type', AccountingAccount::TYPE_REVENUE)->values();
        $expenseRows = $rows->where('type', AccountingAccount::TYPE_EXPENSE)->values();
        $revenueCents = (int) $revenueRows->sum('amount_cents');
        $expenseCents = (int) $expenseRows->sum('amount_cents');
        $netProfitCents = $revenueCents - $expenseCents;

        return compact('dateFrom', 'dateTo', 'revenueRows', 'expenseRows', 'revenueCents', 'expenseCents', 'netProfitCents')
            + ['hasActivity' => $rows->isNotEmpty()];
    }
}
