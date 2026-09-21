<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
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

    public function journals(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,posted'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $search = trim($validated['search'] ?? '');

        $journalEntries = JournalEntry::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhereHas(
                            'postedBy',
                            fn ($userQuery) => $userQuery->where('name', 'like', '%'.$search.'%')
                        );
                });
            })
            ->when(
                $validated['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $validated['date_from'] ?? null,
                fn ($query, $dateFrom) => $query->whereDate('entry_date', '>=', $dateFrom)
            )
            ->when(
                $validated['date_to'] ?? null,
                fn ($query, $dateTo) => $query->whereDate('entry_date', '<=', $dateTo)
            )
            ->with([
                'lines.account',
                'postedBy',
                'source',
            ])
            ->latest('entry_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('accounting.journals', compact('journalEntries'));
    }
}
