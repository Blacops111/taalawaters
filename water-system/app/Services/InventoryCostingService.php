<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\InventoryCostingSetup;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InventoryCostingService
{
    // Decimal operations never multiply PHP floats. Quantities use thousandths; money uses cents.
    public static function scaled(mixed $value, int $scale): int
    {
        try {
            return BigDecimal::of((string) $value)->toScale($scale, RoundingMode::UNNECESSARY)->getUnscaledValue()->toInt();
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['costing' => 'Invalid quantity or amount precision.']);
        }
    }

    public static function money(int $cents): string
    {
        return (string) BigDecimal::of($cents)->dividedBy(100, 2);
    }

    public static function receiptValue(mixed $quantity, mixed $unitCost): int
    {
        return BigDecimal::of((string) $quantity)->multipliedBy((string) $unitCost)
            ->multipliedBy(100)->toScale(0, RoundingMode::HALF_UP)->toInt();
    }

    public static function issueValue(int $quantityMilli, int $balanceMilli, int $balanceCents): int
    {
        if ($quantityMilli <= 0 || $quantityMilli > $balanceMilli || $balanceCents < 0) {
            throw ValidationException::withMessages(['costing' => 'Insufficient or invalid valued inventory.']);
        }
        // Issuing the final unit takes all residual cents: zero quantity always has zero value.
        return BigDecimal::of($balanceCents)->multipliedBy($quantityMilli)
            ->dividedBy($balanceMilli, 0, RoundingMode::HALF_UP)->toInt();
    }

    public static function quantityMilli(int $itemId): int
    {
        return (int) StockMovement::where('inventory_item_id', $itemId)
            ->selectRaw('COALESCE(SUM(CAST(ROUND(quantity_delta * 1000, 0) AS SIGNED)), 0) as quantity_milli')
            ->first()->quantity_milli;
    }

    public static function lock(): void
    {
        InventoryCostingSetup::query()->lockForUpdate()->findOrFail(1);
    }

    public function ledgerCents(): int
    {
        $lines = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounting_accounts as a', 'a.id', '=', 'l.accounting_account_id')
            ->where('e.status', JournalEntry::STATUS_POSTED)->where('a.code', '1300')->get(['l.debit', 'l.credit']);
        return $lines->sum(fn ($line) => self::scaled($line->debit, 2) - self::scaled($line->credit, 2));
    }

    public function validateSetup(array $input): array
    {
        return Validator::make($input, [
            'values' => ['required', 'array'],
            'values.*' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'regex:/^\d{1,12}(\.\d{1,3})?$/'],
            'ledger_cents' => ['required', 'integer'],
            'offset_code' => ['required', 'in:3100,3200'],
            'notes' => ['required', 'string', 'max:1000'],
            'acknowledgement' => ['accepted'],
        ])->validate();
    }

    public function activate(User $user, array $input): void
    {
        $data = $this->validateSetup($input);
        DB::transaction(function () use ($user, $data) {
            $setup = InventoryCostingSetup::query()->lockForUpdate()->findOrFail(1);
            if ($setup->is_active) {
                $this->fail('Inventory costing has already been activated. Opening values cannot be overwritten.');
            }
            $at = now()->utc();
            $items = InventoryItem::query()->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty() || count($data['values']) !== $items->count() || count($data['quantities']) !== $items->count()) {
                $this->fail('The item list changed. Reload setup and review all opening values.');
            }
            if (StockMovement::where('occurred_at', '>', $at)->exists()
                || DB::table('journal_entries as e')->join('journal_lines as l', 'e.id', '=', 'l.journal_entry_id')
                    ->join('accounting_accounts as a', 'a.id', '=', 'l.accounting_account_id')
                    ->where('a.code', '1300')->where('e.status', 'posted')->whereDate('e.entry_date', '>', $at->toDateString())->exists()) {
                $this->fail('Future-dated stock or inventory journals must be resolved before activation.');
            }
            $total = 0;
            foreach ($items as $item) {
                $quantity = self::quantityMilli($item->id);
                if (! array_key_exists($item->id, $data['values']) || ! array_key_exists($item->id, $data['quantities'])
                    || $quantity < 0 || $quantity !== self::scaled($data['quantities'][$item->id], 3)) {
                    $this->fail('Stock changed or is negative for '.$item->sku.'. Reload and review setup.');
                }
                $value = self::scaled($data['values'][$item->id], 2);
                if (($quantity === 0 && $value !== 0) || ($item->category === 'raw_water' && $value !== 0)) {
                    $this->fail('Empty stock and meter-managed borehole water must have zero opening acquisition value.');
                }
                DB::table('inventory_cost_balances')->insert([
                    'inventory_item_id' => $item->id, 'opening_quantity_milli' => $quantity, 'opening_value_cents' => $value,
                    'quantity_milli' => $quantity, 'value_cents' => $value, 'last_occurred_at' => $at,
                ]);
                $total += $value;
            }
            $ledger = $this->ledgerCents();
            if ($ledger !== (int) $data['ledger_cents']) {
                $this->fail('Inventory journals changed. Reload and review the opening reconciliation.');
            }
            $difference = $total - $ledger;
            if ($total > 999999999999999 || abs($difference) > 999999999999999) {
                $this->fail('The opening valuation exceeds the accounting amount limit.');
            }
            if ($difference !== 0) {
                $this->postPair($user, $at, 'Opening inventory valuation: '.$data['notes'],
                    $difference > 0 ? '1300' : $data['offset_code'],
                    $difference > 0 ? $data['offset_code'] : '1300', abs($difference), $setup);
            }
            $setup->update(['is_active' => true, 'activated_at' => $at, 'approved_by' => $user->id, 'notes' => $data['notes']]);
        }, 3);
    }

    public function postPair(User $user, mixed $date, string $description, string $debit, string $credit, int $cents, \Illuminate\Database\Eloquent\Model $source): void
    {
        $accounts = AccountingAccount::whereIn('code', [$debit, $credit])->get()->keyBy('code');
        $types = ['1300' => 'asset', '5100' => 'expense', '5500' => 'expense', '3100' => 'equity', '3200' => 'equity'];
        foreach ([$debit, $credit] as $code) {
            if (! isset($accounts[$code]) || ! $accounts[$code]->is_active || $accounts[$code]->type !== $types[$code]) {
                $this->fail('Required inventory accounting account '.$code.' is missing, inactive or has the wrong type.');
            }
        }
        app(JournalPostingService::class)->post($user, $date, mb_substr($description, 0, 500), [
            ['account_id' => $accounts[$debit]->id, 'debit' => self::money($cents), 'credit' => 0],
            ['account_id' => $accounts[$credit]->id, 'debit' => 0, 'credit' => self::money($cents)],
        ], $source);
    }

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['costing' => $message]);
    }
}
