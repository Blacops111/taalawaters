<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccount;
use App\Models\OperatingExpense;
use App\Services\OperatingExpenseService;
use App\Services\OperatingExpenseReversalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperatingExpenseController extends Controller
{
    public function index(): View
    {
        $expenseAccounts = AccountingAccount::query()->whereIn('code', OperatingExpenseService::EXPENSE_CODES)
            ->where('type', AccountingAccount::TYPE_EXPENSE)->where('is_active', true)
            ->orderBy('code')->get(['id', 'code', 'name']);
        $paymentAccounts = AccountingAccount::query()->whereIn('code', OperatingExpenseService::PAYMENT_CODES)
            ->where('type', AccountingAccount::TYPE_ASSET)->where('is_active', true)
            ->orderBy('code')->get(['id', 'code', 'name']);
        $expenses = OperatingExpense::query()
            ->with(['expenseAccount:id,code,name', 'paymentAccount:id,code,name', 'creator:id,name', 'reversal.reversedBy:id,name'])
            ->latest('expense_date')->latest('id')->paginate(25);

        return view('accounting.expenses', compact('expenseAccounts', 'paymentAccounts', 'expenses'));
    }

    public function reversal(OperatingExpense $operatingExpense): View|RedirectResponse
    {
        $operatingExpense->load(['expenseAccount', 'paymentAccount', 'reversal']);
        if ($operatingExpense->reversal) {
            return redirect()->route('accounting.expenses')->withErrors(['expense' => 'This expense has already been reversed.']);
        }

        return view('accounting.expense-reversal', ['expense' => $operatingExpense]);
    }

    public function reverse(Request $request, OperatingExpense $operatingExpense, OperatingExpenseReversalService $service): RedirectResponse
    {
        $reversal = $service->reverse($operatingExpense, $request->user(), $request->only(['reason', 'reversal_date']));

        return redirect()->route('accounting.expenses')
            ->with('success', $reversal->reference.' posted. The original expense remains in history.');
    }

    public function store(Request $request, OperatingExpenseService $service): RedirectResponse
    {
        $expense = $service->record($request->user(), $request->only([
            'expense_account_id', 'payment_account_id', 'expense_date', 'amount', 'description',
            'payee', 'external_reference', 'notes', 'idempotency_key',
        ]));

        return redirect()->route('accounting.expenses')
            ->with('success', $expense->reference.' recorded and posted to accounting.');
    }
}
