<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProductionCostAllocation extends Model
{
    protected $fillable = ['production_run_id', 'operating_expense_id', 'original_journal_id',
        'expense_account_id', 'amount_cents', 'basis', 'allocated_by'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Production cost allocations cannot be edited. Reverse the production run.'));
        static::deleting(fn () => throw new LogicException('Production cost allocations cannot be deleted. Reverse the production run.'));
    }

    public function productionRun(): BelongsTo
    {
        return $this->belongsTo(ProductionRun::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(OperatingExpense::class, 'operating_expense_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'expense_account_id');
    }
}
