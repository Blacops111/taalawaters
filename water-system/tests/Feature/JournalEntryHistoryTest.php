<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalPostingService;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_read_only_journal_history_with_lines_and_totals(): void
    {
        $admin = User::factory()->create([
            'name' => 'Accounting Admin',
            'role' => 'admin',
        ]);

        app(AccountingAccountSeeder::class)->run();

        $cash = AccountingAccount::query()->where('code', '1100')->firstOrFail();
        $revenue = AccountingAccount::query()->where('code', '4100')->firstOrFail();

        app(JournalPostingService::class)->post(
            $admin,
            '2026-09-21',
            'Journal history test sale.',
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
                    'memo' => 'Water sold',
                ],
            ],
        );

        $this->actingAs($admin)
            ->get(route('accounting.journals'))
            ->assertOk()
            ->assertSee('Journal Entries')
            ->assertSee('JRN-00000001')
            ->assertSee('Journal history test sale.')
            ->assertSee('Cash on Hand')
            ->assertSee('Water Sales Revenue')
            ->assertSee('Cash received')
            ->assertSee('Accounting Admin')
            ->assertSee('KES 1,500.00');
    }

    public function test_journal_history_can_filter_by_status_search_and_date(): void
    {
        $admin = User::factory()->create([
            'name' => 'Finance User',
            'role' => 'admin',
        ]);

        app(AccountingAccountSeeder::class)->run();

        $cash = AccountingAccount::query()->where('code', '1100')->firstOrFail();
        $revenue = AccountingAccount::query()->where('code', '4100')->firstOrFail();

        app(JournalPostingService::class)->post(
            $admin,
            '2026-09-20',
            'Earlier water sale.',
            [
                ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 500],
            ],
        );

        app(JournalPostingService::class)->post(
            $admin,
            '2026-09-21',
            'Special filtered water sale.',
            [
                ['account_id' => $cash->id, 'debit' => 750, 'credit' => 0],
                ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 750],
            ],
        );

        JournalEntry::create([
            'reference' => null,
            'entry_date' => '2026-09-21',
            'status' => JournalEntry::STATUS_DRAFT,
            'description' => 'Draft accounting adjustment.',
            'posted_by' => $admin->id,
            'posted_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('accounting.journals', [
                'search' => 'Special filtered',
                'status' => JournalEntry::STATUS_POSTED,
                'date_from' => '2026-09-21',
                'date_to' => '2026-09-21',
            ]))
            ->assertOk()
            ->assertSee('Special filtered water sale.')
            ->assertDontSee('Earlier water sale.')
            ->assertDontSee('Draft accounting adjustment.');
    }

    public function test_non_admin_cannot_view_journal_history(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('accounting.journals'))
            ->assertRedirect('/dashboard');
    }
}
