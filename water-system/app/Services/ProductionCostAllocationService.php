<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\InventoryCostingSetup;
use App\Models\JournalEntry;
use App\Models\OperatingExpense;
use App\Models\ProductionCostAllocation;
use App\Models\ProductionRun;
use App\Models\ProductionRunReversal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductionCostAllocationService
{
    // Allocation requires a production-related basis. Distribution/fuel account 5200 is excluded.
    public const EXPENSE_CODES = ['5300', '5400', '5500'];

    public function normalize(array $rows): array
    {
        $rows = array_values(array_filter($rows, function ($row) {
            return ! is_array($row) || collect(['expense_id', 'amount', 'basis'])
                ->contains(fn ($field) => isset($row[$field]) && (! is_scalar($row[$field]) || trim((string) $row[$field]) !== ''));
        }));
        $data = Validator::make(['allocations' => $rows], [
            'allocations' => ['array', 'max:20'],
            'allocations.*' => ['required', 'array'],
            'allocations.*.expense_id' => ['required', 'integer', 'min:1'],
            'allocations.*.amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/', 'numeric', 'gt:0'],
            'allocations.*.basis' => ['required', 'string', 'max:300'],
        ])->validate();
        $result = [];
        foreach ($data['allocations'] as $row) {
            $id = (int) $row['expense_id'];
            if (isset($result[$id]) || trim($row['basis']) === '') {
                $this->fail('Use each expense once per batch and describe the production allocation basis.');
            }
            $result[$id] = ['expense_id' => $id, 'amount_cents' => InventoryCostingService::scaled($row['amount'], 2), 'basis' => trim($row['basis'])];
        }
        ksort($result);
        if (array_sum(array_column($result, 'amount_cents')) > 999999999999999) {
            $this->fail('The total allocation exceeds the accounting amount limit.');
        }
        return array_values($result);
    }

    public function activeCents(OperatingExpense $expense): int
    {
        return (int) $expense->productionAllocations()->whereDoesntHave('productionRun.reversal')->sum('amount_cents');
    }

    public function availableExpenses(): Builder
    {
        $setup = InventoryCostingSetup::findOrFail(1);
        $used = ProductionCostAllocation::query()->whereDoesntHave('productionRun.reversal')
            ->selectRaw('operating_expense_id, SUM(amount_cents) as allocated_cents')->groupBy('operating_expense_id');
        return OperatingExpense::query()->leftJoinSub($used, 'used_costs', 'used_costs.operating_expense_id', '=', 'operating_expenses.id')
            ->select('operating_expenses.*')->selectRaw('COALESCE(used_costs.allocated_cents, 0) as allocated_cents')
            ->whereRaw('ROUND(operating_expenses.amount * 100, 0) > COALESCE(used_costs.allocated_cents, 0)')
            ->whereDoesntHave('reversal')->whereHas('expenseAccount', fn ($q) => $q->whereIn('code', self::EXPENSE_CODES)
                ->where('type', AccountingAccount::TYPE_EXPENSE)->where('is_active', true))
            ->when(! $setup->is_active, fn ($q) => $q->whereRaw('1 = 0'))
            ->where('operating_expenses.created_at', '>=', $setup->activated_at ?? now())
            ->whereDate('expense_date', '>=', $setup->activated_at?->toDateString() ?? now()->toDateString())
            ->whereDate('expense_date', '<=', now()->toDateString())
            ->with('expenseAccount')->orderByDesc('expense_date')->orderByDesc('operating_expenses.id');
    }

    /** Called only inside the production transaction, before its output movement. */
    public function allocate(ProductionRun $run, User $user, array $rows): void
    {
        abort_unless($user->role === 'admin', 403);
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Production allocations require the production transaction.');
        }
        if ($run->status !== 'completed' || $run->stockMovements()->exists() || $run->costAllocations()->exists() || $run->journalEntries()->exists()) {
            $this->fail('Allocate costs only while completing a new production run. Existing batches cannot be repriced.');
        }
        $setup = InventoryCostingSetup::findOrFail(1);
        if (! $setup->is_active) {
            $this->fail('Activate Inventory Costing before allocating production expenses.');
        }
        if ($run->occurred_at->gt(now()->utc())) {
            $this->fail('Expense allocations cannot be posted to future production runs.');
        }
        $expenses = OperatingExpense::whereIn('id', array_column($rows, 'expense_id'))->orderBy('id')
            ->lockForUpdate()->get()->keyBy('id');
        foreach ($rows as $row) {
            $expense = $expenses->get($row['expense_id']);
            if (! $expense || $expense->reversal()->exists()) {
                $this->fail('Choose an existing expense that has not been reversed.');
            }
            if ($expense->created_at->lt($setup->activated_at) || $expense->expense_date->toDateString() < $setup->activated_at->toDateString()) {
                $this->fail('Only expenses recorded after costing activation can be allocated. Older costs may already be in opening inventory.');
            }
            if ($expense->expense_date->toDateString() > $run->occurred_at->toDateString()) {
                $this->fail('Production cannot precede the expense date.');
            }
            $account = $expense->expenseAccount;
            if (! $account || ! $account->is_active || $account->type !== AccountingAccount::TYPE_EXPENSE
                || ! in_array($account->code, self::EXPENSE_CODES, true)) {
                $this->fail('Allocate only active Utilities, Repairs or General Operating expenses attributable to production.');
            }
            $journals = $expense->journalEntries()->with('lines')->lockForUpdate()->get();
            if ($journals->count() !== 1 || $journals->first()->status !== JournalEntry::STATUS_POSTED) {
                $this->fail('The expense must have exactly one posted original journal.');
            }
            $original = $journals->first();
            $debit = $original->lines->firstWhere('accounting_account_id', $expense->expense_account_id);
            $credit = $original->lines->firstWhere('accounting_account_id', $expense->payment_account_id);
            if ($original->reversals()->exists() || $original->lines->count() !== 2 || ! $debit || ! $credit
                || $debit->id === $credit->id || $debit->debit !== $expense->amount || $debit->credit !== '0.00'
                || $credit->credit !== $expense->amount || $credit->debit !== '0.00'
                || $original->entry_date->toDateString() !== $expense->expense_date->toDateString()) {
                $this->fail('The original expense journal is reversed or does not match the recorded expense.');
            }
            // Released amounts are available only from the actual batch reversal time.
            $releasedAt = DB::table('production_cost_allocations as a')
                ->join('production_run_reversals as r', 'r.production_run_id', '=', 'a.production_run_id')
                ->where('a.operating_expense_id', $expense->id)->max('r.reversed_at');
            if ($releasedAt && $run->occurred_at->lt(CarbonImmutable::parse($releasedAt, 'UTC'))) {
                $this->fail('Production cannot precede a prior allocation reversal for this expense.');
            }
            if ($row['amount_cents'] > InventoryCostingService::scaled($expense->amount, 2) - $this->activeCents($expense)) {
                $this->fail('Allocation exceeds the unallocated amount of '.$expense->reference.'.');
            }
            ProductionCostAllocation::create([
                'production_run_id' => $run->id, 'operating_expense_id' => $expense->id,
                'original_journal_id' => $original->id, 'expense_account_id' => $expense->expense_account_id,
                'amount_cents' => $row['amount_cents'], 'basis' => $row['basis'], 'allocated_by' => $user->id,
            ]);
        }
        $inventory = AccountingAccount::where('code', '1300')->first();
        if (! $inventory || ! $inventory->is_active || $inventory->type !== AccountingAccount::TYPE_ASSET) {
            $this->fail('An active Inventory asset account is required.');
        }
        $allocations = $run->costAllocations()->get();
        $lines = [['account_id' => $inventory->id, 'debit' => InventoryCostingService::money((int) $allocations->sum('amount_cents')), 'credit' => 0,
            'memo' => 'Production conversion costs for '.$run->reference]];
        foreach ($allocations->groupBy('expense_account_id') as $accountId => $group) {
            $lines[] = ['account_id' => (int) $accountId, 'debit' => 0, 'credit' => InventoryCostingService::money((int) $group->sum('amount_cents')),
                'memo' => 'Reclassified recorded expense to '.$run->reference];
        }
        app(JournalPostingService::class)->post($user, $run->occurred_at, 'Production cost allocation for '.$run->reference, $lines, $run);
    }

    public function reverse(ProductionRun $run, ProductionRunReversal $reversal, User $user): void
    {
        $allocations = $run->costAllocations()->get();
        if ($allocations->isEmpty()) {
            if ($run->journalEntries()->exists() || (int) $run->stockMovements()->sum('value_delta_cents') !== 0) {
                $this->fail('Production allocation history is missing or inconsistent.');
            }
            return;
        }
        abort_unless($user->role === 'admin', 403);
        // The common costing lock serializes this with new allocations and expense reversals.
        OperatingExpense::whereIn('id', $allocations->pluck('operating_expense_id'))->orderBy('id')->lockForUpdate()->get();
        $journals = $run->journalEntries()->with('lines.account')->lockForUpdate()->get();
        if ($journals->count() !== 1 || $journals->first()->status !== JournalEntry::STATUS_POSTED) {
            $this->fail('The batch must have exactly one posted allocation journal to reverse.');
        }
        $original = $journals->first();
        if ($original->reversals()->exists() || $reversal->reversed_at->lt($run->occurred_at)
            || $reversal->reversed_at->toDateString() < $original->entry_date->toDateString()) {
            $this->fail('Allocation journal is already reversed or the reversal date precedes production.');
        }
        $total = (int) $allocations->sum('amount_cents');
        $inventoryLine = $original->lines->first(fn ($line) => $line->account?->code === '1300');
        $groups = $allocations->groupBy('expense_account_id');
        if (! $inventoryLine || $original->lines->count() !== $groups->count() + 1
            || InventoryCostingService::scaled($inventoryLine->debit, 2) !== $total || $inventoryLine->credit !== '0.00') {
            $this->fail('The allocation journal does not match its recorded costs.');
        }
        foreach ($groups as $accountId => $group) {
            $line = $original->lines->firstWhere('accounting_account_id', $accountId);
            if (! $line || $line->debit !== '0.00' || InventoryCostingService::scaled($line->credit, 2) !== (int) $group->sum('amount_cents')) {
                $this->fail('The allocation journal does not match its recorded expense accounts.');
            }
        }
        $movements = $run->stockMovements()->get();
        if ($movements->contains(fn ($movement) => $movement->value_delta_cents === null)
            || (int) $movements->sum('value_delta_cents') !== $total) {
            $this->fail('Batch inventory costs do not match the allocation journal.');
        }
        app(JournalPostingService::class)->post($user, $reversal->reversed_at, 'Reverse production costs for '.$run->reference,
            $original->lines->map(fn ($line) => ['account_id' => $line->accounting_account_id,
                'debit' => $line->credit, 'credit' => $line->debit, 'memo' => 'Reversal of '.$original->reference])->all(), $reversal, $original);
    }

    public function guardExpenseReversal(OperatingExpense $expense, string $date): void
    {
        if ($this->activeCents($expense) > 0) {
            $this->fail('This expense is allocated to production. Reverse the affected production runs before reversing the expense.');
        }
        $latest = DB::table('production_cost_allocations as a')
            ->join('production_run_reversals as r', 'r.production_run_id', '=', 'a.production_run_id')
            ->where('a.operating_expense_id', $expense->id)->max('r.reversed_at');
        if ($latest && $date < CarbonImmutable::parse($latest, 'UTC')->toDateString()) {
            $this->fail('The expense reversal cannot precede its production allocation reversal.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['allocations' => $message]);
    }
}
