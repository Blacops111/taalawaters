<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

class OperatingExpenseReversal extends Model
{
    protected $fillable = [
        'reference', 'operating_expense_id', 'original_journal_id', 'reversal_date',
        'reason', 'reversed_by', 'reversed_at',
    ];

    protected function casts(): array
    {
        return ['reversal_date' => 'date', 'reversed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Expense reversal records cannot be edited.');
        });
        static::deleting(function () {
            throw new LogicException('Expense reversal records cannot be deleted.');
        });
    }

    public function operatingExpense(): BelongsTo
    {
        return $this->belongsTo(OperatingExpense::class);
    }

    public function originalJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'original_journal_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }
}
