@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Activity Log</h3>
    </div>

    <div class="alert alert-light border small py-2">
        <i class="bi bi-info-circle"></i> Important actions are recorded here: voided sales, product changes, staff accounts and backups.
        Each kind of action has its own color. Stock changes are in <a href="{{ route('inventory.history') }}">Stock History</a>.
    </div>

    {{-- The latest lines are loaded once; search, the Action filter and paging all work in the browser. --}}
    <div data-ctable data-per-page="10">
        <form class="card shadow-sm mb-3" data-cf-form onsubmit="return false;">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small mb-0">Search</label>
                        <input type="search" data-cf="q" class="form-control form-control-sm" placeholder="Invoice number, product, staff name...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-0">Action</label>
                        <select data-cf="action" class="form-select form-select-sm">
                            <option value="">All actions</option>
                            @foreach (\App\Models\ActivityLog::ACTION_LABELS as $key => $label)
                                <option value="{{ $key }}" @selected($action === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-secondary btn-icon" data-cf-clear title="Clear filters" aria-label="Clear filters"><i class="bi bi-x-circle"></i></button>
                    </div>
                </div>
            </div>
        </form>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr><th style="width:170px">When</th><th style="width:150px">Who</th><th style="width:190px">Action</th><th>Details</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php [$bg, $fg] = $log->action_color; @endphp
                            <tr data-row
                                data-search="{{ mb_strtolower(($log->user?->name ?? 'unknown') . ' ' . $log->action_label . ' ' . $log->description) }}"
                                data-action="{{ $log->action }}">
                                <td class="small">{{ $log->created_at->format('M d, Y h:i A') }}</td>
                                <td>{{ $log->user?->name ?? 'Unknown' }}</td>
                                <td><span class="badge" style="background:{{ $bg }};color:{{ $fg }}">{{ $log->action_label }}</span></td>
                                <td class="small">{{ $log->description }}</td>
                            </tr>
                        @endforeach
                        <tr data-empty class="d-none"><td colspan="4" class="text-center text-muted py-4">Nothing matches these filters.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div data-pager></div>
    </div>

    @if ($logs->count() >= 2000)
        <div class="small text-muted mt-2">Showing the latest 2,000 entries.</div>
    @endif
@endsection
