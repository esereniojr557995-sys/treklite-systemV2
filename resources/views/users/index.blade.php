@extends('layouts.app')

@section('title', 'Staff Accounts')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Staff Accounts</h3>
        <a href="{{ route('users.create') }}" class="btn btn-success btn-icon" title="Add Staff" aria-label="Add Staff"><i class="bi bi-person-plus"></i></a>
    </div>

    <div data-ctable data-per-page="10">
    <div class="card shadow-sm">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th class="text-center">Status</th>
                    <th>Created</th>
                    <th class="text-center" style="width:110px">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr class="{{ $u->is_active ? '' : 'text-muted' }}" data-row>
                        <td class="fw-semibold">{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td class="text-center">
                            @if ($u->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Deactivated</span>
                            @endif
                        </td>
                        <td class="small">{{ $u->created_at->format('M d, Y') }}</td>
                        <td class="text-center text-nowrap">
                            <a href="{{ route('users.edit', $u) }}" class="btn btn-outline-secondary btn-icon" title="Edit" aria-label="Edit"><i class="bi bi-pencil-square"></i></a>
                            <form method="POST" action="{{ route('users.destroy', $u) }}" class="d-inline"
                                  onsubmit="return confirm('Delete the account of {{ addslashes($u->name) }}? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-icon" title="Delete" aria-label="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No staff accounts yet. Press the <i class="bi bi-person-plus"></i> icon above to create one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div data-pager></div>
    </div>

    <div class="small text-muted mt-2">
        This page is for your <strong>staff</strong> only. Your own name, email and password are in <a href="{{ route('profile.edit') }}">My Profile</a>.
        A staff member who already has recorded sales cannot be deleted. Deactivate the account instead (Edit &rarr; Status) so the person can no longer log in.
    </div>
@endsection
