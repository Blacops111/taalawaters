@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">Chart of Accounts</h2>
            <p class="text-muted mb-0">
                Core accounting accounts used by Taala Crystal for double-entry posting.
            </p>
        </div>
    </div>

    @if($accounts->isEmpty())
        <div class="alert alert-warning">
            No accounting accounts are configured yet.
        </div>
    @else
        <div class="row g-4">
            @foreach($accounts as $account)
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center gap-3">
                            <div>
                                <div class="small text-muted">{{ strtoupper($account->type) }}</div>
                                <strong>{{ $account->code }} — {{ $account->name }}</strong>
                            </div>

                            @if($account->is_system)
                                <span class="badge text-bg-light border">System</span>
                            @endif
                        </div>

                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 110px;">Code</th>
                                            <th>Account</th>
                                            <th style="width: 120px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($account->children->sortBy('code') as $child)
                                            <tr>
                                                <td><strong>{{ $child->code }}</strong></td>
                                                <td>{{ $child->name }}</td>
                                                <td>
                                                    @if($child->is_active)
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-4">
                                                    No child accounts configured.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
