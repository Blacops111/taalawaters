<?php

namespace Database\Seeders;

use App\Models\AccountingAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingAccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $groups = [
                [
                    'code' => '1000',
                    'name' => 'Assets',
                    'type' => AccountingAccount::TYPE_ASSET,
                    'children' => [
                        ['code' => '1100', 'name' => 'Cash on Hand'],
                        ['code' => '1110', 'name' => 'Bank Account'],
                        ['code' => '1200', 'name' => 'Accounts Receivable'],
                        ['code' => '1300', 'name' => 'Inventory'],
                    ],
                ],
                [
                    'code' => '2000',
                    'name' => 'Liabilities',
                    'type' => AccountingAccount::TYPE_LIABILITY,
                    'children' => [
                        ['code' => '2100', 'name' => 'Accounts Payable'],
                    ],
                ],
                [
                    'code' => '3000',
                    'name' => 'Equity',
                    'type' => AccountingAccount::TYPE_EQUITY,
                    'children' => [
                        ['code' => '3100', 'name' => 'Owner Capital'],
                        ['code' => '3200', 'name' => 'Retained Earnings'],
                    ],
                ],
                [
                    'code' => '4000',
                    'name' => 'Revenue',
                    'type' => AccountingAccount::TYPE_REVENUE,
                    'children' => [
                        ['code' => '4100', 'name' => 'Water Sales Revenue'],
                        ['code' => '4200', 'name' => 'Delivery Revenue'],
                    ],
                ],
                [
                    'code' => '5000',
                    'name' => 'Expenses',
                    'type' => AccountingAccount::TYPE_EXPENSE,
                    'children' => [
                        ['code' => '5100', 'name' => 'Cost of Goods Sold'],
                        ['code' => '5200', 'name' => 'Fuel and Transport Expense'],
                        ['code' => '5300', 'name' => 'Utilities Expense'],
                        ['code' => '5400', 'name' => 'Repairs and Maintenance Expense'],
                        ['code' => '5500', 'name' => 'General Operating Expense'],
                    ],
                ],
            ];

            foreach ($groups as $group) {
                $parent = AccountingAccount::updateOrCreate(
                    ['code' => $group['code']],
                    [
                        'name' => $group['name'],
                        'type' => $group['type'],
                        'parent_id' => null,
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );

                foreach ($group['children'] as $child) {
                    AccountingAccount::updateOrCreate(
                        ['code' => $child['code']],
                        [
                            'name' => $child['name'],
                            'type' => $group['type'],
                            'parent_id' => $parent->id,
                            'is_system' => true,
                            'is_active' => true,
                        ],
                    );
                }
            }
        }, 3);
    }
}
