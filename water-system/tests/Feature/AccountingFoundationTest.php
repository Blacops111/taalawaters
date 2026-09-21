<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\JournalPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class AccountingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_of_accounts_supports_core_types_and_parent_child_structure(): void
    {
        $assets = AccountingAccount::create([
            'code' => '1000',
            'name' => 'Assets',
            'type' => AccountingAccount::TYPE_ASSET,
            'is_system' => true,
            'is_active' => true,
        ]);

        $cash = AccountingAccount::create([
            'code' => '1100',
            'name' => 'Cash',
            'type' => AccountingAccount::TYPE_ASSET,
            'parent_id' => $assets->id,
            'is_system' => true,
            'is_active' => true,
        ]);

        $this->assertSame($assets->id, $cash->parent->id);
        $this->assertTrue($assets->children->contains($cash));
        $this->assertTrue($cash->is_system);
        $this->assertTrue($cash->is_active);
    }

    public function test_balanced_journal_can_be_posted_with_source_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cash = $this->account('1100', 'Cash', AccountingAccount::TYPE_ASSET);
        $revenue = $this->account('4000', 'Sales Revenue', AccountingAccount::TYPE_REVENUE);

        $sale = SalesOrder::create([
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-ACCOUNTING-001',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-21 09:00:00',
            'total_amount' => 1500,
            'created_by' => $admin->id,
        ]);

        $entry = app(JournalPostingService::class)->post(
            $admin,
            '2026-09-21',
            'Cash sale accounting test.',
            [
                [
                    'account_id' => $cash->id,
                    'debit' => 1500,
                    'credit' => 0,
                    'memo' => 'Cash received',
                ],
                [
                    'account_id' => $revenue->id,
                    'debit' => 0,
                    'credit' => 1500,
                    'memo' => 'Sales revenue',
                ],
            ],
            $sale,
        );

        $this->assertSame('JRN-00000001', $entry->reference);
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertNotNull($entry->posted_at);
        $this->assertSame($admin->id, $entry->posted_by);
        $this->assertSame(SalesOrder::class, $entry->source_type);
        $this->assertSame($sale->id, $entry->source_id);
        $this->assertSame($sale->id, $entry->source->id);
        $this->assertSame('1500.00', $entry->lines->sum('debit'));
        $this->assertSame('1500.00', $entry->lines->sum('credit'));

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $cash->id,
            'debit' => 1500,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $revenue->id,
            'debit' => 0,
            'credit' => 1500,
        ]);
    }

    public function test_unbalanced_journal_is_rejected_without_partial_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cash = $this->account('1100', 'Cash', AccountingAccount::TYPE_ASSET);
        $revenue = $this->account('4000', 'Sales Revenue', AccountingAccount::TYPE_REVENUE);

        try {
            app(JournalPostingService::class)->post(
                $admin,
                '2026-09-21',
                'Unbalanced entry.',
                [
                    ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
                    ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 900],
                ],
            );

            $this->fail('Expected unbalanced journal validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
    }

    public function test_each_journal_line_must_be_one_sided(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cash = $this->account('1100', 'Cash', AccountingAccount::TYPE_ASSET);
        $revenue = $this->account('4000', 'Sales Revenue', AccountingAccount::TYPE_REVENUE);

        try {
            app(JournalPostingService::class)->post(
                $admin,
                '2026-09-21',
                'Invalid line.',
                [
                    ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 1000],
                    ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
                ],
            );

            $this->fail('Expected one-sided journal line validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines.0', $exception->errors());
        }

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_inactive_account_cannot_receive_new_journal_lines(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cash = $this->account('1100', 'Cash', AccountingAccount::TYPE_ASSET);
        $revenue = $this->account(
            '4000',
            'Inactive Revenue',
            AccountingAccount::TYPE_REVENUE,
            false,
        );

        try {
            app(JournalPostingService::class)->post(
                $admin,
                '2026-09-21',
                'Inactive account test.',
                [
                    ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
                    ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 500],
                ],
            );

            $this->fail('Expected inactive-account validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines', $exception->errors());
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
    }

    public function test_posted_journal_and_lines_are_immutable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cash = $this->account('1100', 'Cash', AccountingAccount::TYPE_ASSET);
        $equity = $this->account('3000', 'Opening Equity', AccountingAccount::TYPE_EQUITY);

        $entry = app(JournalPostingService::class)->post(
            $admin,
            '2026-09-21',
            'Opening journal.',
            [
                ['account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $equity->id, 'debit' => 0, 'credit' => 100],
            ],
        );

        try {
            $entry->update(['description' => 'Edited after posting']);
            $this->fail('Expected posted journal immutability exception was not thrown.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $line = $entry->lines()->firstOrFail();

        try {
            $line->update(['memo' => 'Edited line']);
            $this->fail('Expected posted journal line immutability exception was not thrown.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $this->assertSame('Opening journal.', $entry->fresh()->description);
        $this->assertNull($line->fresh()->memo);
    }

    private function account(
        string $code,
        string $name,
        string $type,
        bool $active = true,
    ): AccountingAccount {
        return AccountingAccount::create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_system' => true,
            'is_active' => $active,
        ]);
    }
}
