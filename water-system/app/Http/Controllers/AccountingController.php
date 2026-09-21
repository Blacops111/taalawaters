<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccount;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function accounts(): View
    {
        $accounts = AccountingAccount::query()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        return view('accounting.accounts', compact('accounts'));
    }
}
