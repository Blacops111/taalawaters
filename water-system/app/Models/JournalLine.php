<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JournalLine extends Model
{
    protected $fillable = [
        'journal_entry_id',
        'accounting_account_id',
        'debit',
        'credit',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (JournalLine $line) {
            if ($line->journalEntry()->where('status', JournalEntry::STATUS_POSTED)->exists()) {
                throw new LogicException('Lines belonging to a posted journal entry are immutable.');
            }
        });

        static::deleting(function (JournalLine $line) {
            if ($line->journalEntry()->where('status', JournalEntry::STATUS_POSTED)->exists()) {
                throw new LogicException('Lines belonging to a posted journal entry cannot be deleted.');
            }
        });
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }
}
