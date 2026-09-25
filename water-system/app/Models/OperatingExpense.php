<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class OperatingExpense extends Model
{
    protected $fillable = [
        'reference', 'expense_account_id', 'payment_account_id', 'expense_date', 'amount',
        'description', 'payee', 'external_reference', 'notes', 'created_by', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (OperatingExpense $expense) {
            if ($expense->getOriginal('reference') !== null) {
                throw new LogicException('Recorded expenses cannot be edited. An audited reversal is required.');
            }
        });
        static::deleting(function () {
            throw new LogicException('Recorded expenses cannot be deleted. An audited reversal is required.');
        });
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'expense_account_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'payment_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(OperatingExpenseReversal::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }
}
