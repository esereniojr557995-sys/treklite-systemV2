@extends('layouts.app')

@section('title', 'Database Backup and Restore')

@section('content')
    <h3 class="mb-3">Database Backup and Restore</h3>

    <div class="row g-3">
        <div class="col-lg-7">
            {{-- Create --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-bold"><i class="bi bi-cloud-arrow-down"></i> Create a backup</div>
                <div class="card-body">
                    <p class="mb-2">A backup saves all users, products (with their photos), brands, categories, sizes, colors, stock history, sales, voids and the activity log in one file.
                        Create one at the end of each business day, then <strong>download it and keep a copy on an external drive</strong>.</p>
                    <form method="POST" action="{{ route('backup.store') }}">
                        @csrf
                        <button class="btn btn-success btn-icon" title="Create backup now" aria-label="Create backup now"><i class="bi bi-database-add"></i></button>
                    </form>
                    <div class="small text-muted mt-2">
                        Product and payment photos are saved inside the database, so they are inside the backup file too (the file can be large when there are many photos).
                        Copies stored on the server may be removed when the system is redeployed, so always download one.
                    </div>
                </div>
            </div>

            {{-- Saved backups --}}
            <div class="card shadow-sm">
                <div class="card-header fw-bold">Saved backups on the server</div>
                <div data-ctable data-per-page="10" data-compact>
                <table class="table mb-0 align-middle">
                    <thead><tr><th>File</th><th>Created</th><th class="text-end">Size</th><th class="text-center" style="width:110px">Action</th></tr></thead>
                    <tbody>
                        @forelse ($backups as $b)
                            <tr data-row>
                                <td class="small">{{ $b['name'] }}</td>
                                <td class="small">{{ $b['modified']->format('M d, Y h:i A') }}</td>
                                <td class="text-end small">{{ number_format($b['size'] / 1024, 1) }} KB</td>
                                <td class="text-center"><a href="{{ route('backup.download', $b['name']) }}" class="btn btn-outline-secondary btn-icon" title="Download" aria-label="Download"><i class="bi bi-download"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No backups yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div data-pager class="px-2 pb-2"></div>
                </div>
            </div>
        </div>

        {{-- Restore --}}
        <div class="col-lg-5">
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger-subtle fw-bold"><i class="bi bi-exclamation-triangle"></i> Restore from a backup</div>
                <div class="card-body">
                    <div class="alert alert-warning small">
                        Restoring <strong>replaces all current data</strong> with the contents of the backup file.
                        A safety backup of the current data is made first. You will be signed out afterwards.
                    </div>
                    <form method="POST" action="{{ route('backup.restore') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small mb-1">Backup file (.json)</label>
                            <input type="file" name="backup_file" accept=".json,application/json" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-1">Your password</label>
                            <input type="password" name="password" class="form-control form-control-sm" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small mb-1">Type <strong>RESTORE</strong> to confirm</label>
                            <input type="text" name="confirmation" class="form-control form-control-sm" required autocomplete="off">
                        </div>
                        <button class="btn btn-danger btn-icon" title="Restore database" aria-label="Restore database"
                                onclick="return confirm('Replace ALL current data with this backup?');"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
