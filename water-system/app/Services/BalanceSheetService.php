<?php

namespace App\Services;

use App\Models\AccountingAccount;

class BalanceSheetService
{
    public function __construct(private TrialBalanceService $trialBalance) {}

    public function report(string $asOf): array
    {
        $trial = $this->trialBalance->report($asOf);
        $rows = $trial['rows'];
        $sections = [];

        foreach ([AccountingAccount::TYPE_ASSET, AccountingAccount::TYPE_LIABILITY, AccountingAccount::TYPE_EQUITY] as $type) {
            $sections[$type] = $rows->where('type', $type)->values()->map(function ($row) use ($type) {
                $account = clone $row;
                $account->balance_cents = $type === AccountingAccount::TYPE_ASSET
                    ? $row->debit_cents - $row->credit_cents
                    : $row->credit_cents - $row->debit_cents;

                return $account;
            });
        }

        $revenueCents = (int) $rows->where('type', AccountingAccount::TYPE_REVENUE)
            ->sum(fn ($row) => $row->credit_cents - $row->debit_cents);
        $expenseCents = (int) $rows->where('type', AccountingAccount::TYPE_EXPENSE)
            ->sum(fn ($row) => $row->debit_cents - $row->credit_cents);
        // Use remaining revenue/expense balances, including closing journals, to avoid
        // counting earnings already transferred to retained earnings a second time.
        $earningsCents = $revenueCents - $expenseCents;
        $assetCents = (int) $sections[AccountingAccount::TYPE_ASSET]->sum('balance_cents');
        $liabilityCents = (int) $sections[AccountingAccount::TYPE_LIABILITY]->sum('balance_cents');
        $recordedEquityCents = (int) $sections[AccountingAccount::TYPE_EQUITY]->sum('balance_cents');
        $equityCents = $recordedEquityCents + $earningsCents;
        $liabilitiesAndEquityCents = $liabilityCents + $equityCents;
        $unclassifiedRows = $rows->whereNotIn('type', [
            AccountingAccount::TYPE_ASSET, AccountingAccount::TYPE_LIABILITY, AccountingAccount::TYPE_EQUITY,
            AccountingAccount::TYPE_REVENUE, AccountingAccount::TYPE_EXPENSE,
        ])->values();

        return compact('asOf', 'sections', 'revenueCents', 'expenseCents', 'earningsCents', 'assetCents',
            'liabilityCents', 'recordedEquityCents', 'equityCents', 'liabilitiesAndEquityCents', 'unclassifiedRows') + [
            'hasActivity' => $rows->isNotEmpty(),
            'differenceCents' => abs($assetCents - $liabilitiesAndEquityCents),
            'isBalanced' => $assetCents === $liabilitiesAndEquityCents && $trial['isBalanced'] && $unclassifiedRows->isEmpty(),
        ];
    }
}
