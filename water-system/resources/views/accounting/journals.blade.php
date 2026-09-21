@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 1200px;">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">Journal Entries</h2>
            <p class="text-muted mb-0">
                Read-only accounting audit history for posted and draft journal entries.
            </p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('accounting.journals') }}" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label for="search" class="form-label fw-semibold">Search</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control"
                        maxlength="100"
                        placeholder="Reference, description, or user"
                    >
                </div>

                <div class="col-md-4 col-lg-2">
                    <label for="status" class="form-label fw-semibold">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        <option value="posted" @selected(request('status') === 'posted')>Posted</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label for="date_from" class="form-label fw-semibold">From</label>
                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="{{ request('date_from') }}"
                        class="form-control"
                    >
                </div>

                <div class="col-md-4 col-lg-2">
                    <label for="date_to" class="form-label fw-semibold">To</label>
                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="{{ request('date_to') }}"
                        class="form-control"
                    >
                </div>

                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                    <a href="{{ route('accounting.journals') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>

            @if($errors->any())
                <div class="alert alert-danger mt-3 mb-0">
                    {{ $errors->first() }}
                </div>
            @endif
        </div>
    </div>

    @forelse($journalEntries as $entry)
        @php
            $totalDebit = $entry->lines->sum(fn ($line) => (float) $line->debit);
            $totalCredit = $entry->lines->sum(fn ($line) => (float) $line->credit);
            $sourceLabel = null;

            if ($entry->source) {
                $sourceLabel = class_basename($entry->source);

                if (! empty($entry->source->reference)) {
                    $sourceLabel .= ' · '.$entry->source->reference;
                } else {
                    $sourceLabel .= ' #'.$entry->source_id;
                }
            }
        @endphp

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                            <h5 class="mb-0">{{ $entry->reference ?? 'Unposted Journal #'.$entry->id }}</h5>

                            @if($entry->status === 'posted')
                                <span class="badge bg-success">Posted</span>
                            @else
                                <span class="badge bg-warning text-dark">Draft</span>
                            @endif
                        </div>

                        <div class="text-muted small mb-1">
                            Entry date: {{ $entry->entry_date->format('d M Y') }}
                        </div>

                        <div>{{ $entry->description }}</div>
                    </div>

                    <div class="text-end">
                        <div class="small text-muted">Debit / Credit</div>
                        <div class="fw-bold">
                            KES {{ number_format($totalDebit, 2) }}
                            /
                            KES {{ number_format($totalCredit, 2) }}
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-2 small">
                    <div class="col-md-4">
                        <div class="text-muted">Source</div>
                        <div>{{ $sourceLabel ?? 'Manual / no source' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted">Posted by</div>
                        <div>{{ $entry->postedBy?->name ?? '—' }}</div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted">Posted at</div>
                        <div>{{ $entry->posted_at?->format('d M Y H:i') ?? 'Not posted' }}</div>
                    </div>

                    @if($entry->reversalOf)
                        <div class="col-12">
                            <div class="alert alert-warning py-2 mb-0">
                                Reversal of journal <strong>{{ $entry->reversalOf->reference }}</strong>
                            </div>
                        </div>
                    @endif
                </div>

                <details class="mt-3">
                    <summary class="fw-semibold" style="cursor: pointer;">
                        View journal lines ({{ $entry->lines->count() }})
                    </summary>

                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Account</th>
                                    <th>Memo</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entry->lines as $line)
                                    <tr>
                                        <td>
                                            <strong>{{ $line->account?->code }}</strong>
                                            — {{ $line->account?->name }}
                                        </td>
                                        <td>{{ $line->memo ?? '—' }}</td>
                                        <td class="text-end">
                                            {{ (float) $line->debit > 0 ? 'KES '.number_format((float) $line->debit, 2) : '—' }}
                                        </td>
                                        <td class="text-end">
                                            {{ (float) $line->credit > 0 ? 'KES '.number_format((float) $line->credit, 2) : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="2">Totals</td>
                                    <td class="text-end">KES {{ number_format($totalDebit, 2) }}</td>
                                    <td class="text-end">KES {{ number_format($totalCredit, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">
                No journal entries match the selected filters.
            </div>
        </div>
    @endforelse

    @if($journalEntries->hasPages())
        <div class="mt-4">
            {{ $journalEntries->links() }}
        </div>
    @endif
</div>
@endsection
