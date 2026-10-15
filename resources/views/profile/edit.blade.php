@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    @php
        $pe = $errors->getBag('profile');
        $pw = $errors->getBag('password');
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">My Profile</h3>
        @if ($user->hasFullAccess())
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-icon" title="Manage Staff Accounts" aria-label="Manage Staff Accounts"><i class="bi bi-people"></i></a>
        @endif
    </div>

    <div class="row g-3">
        {{-- Who am I --}}
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="bi bi-person-circle text-secondary" style="font-size: 5rem; line-height: 1;"></i>
                    <h5 class="mt-2 mb-1">{{ $user->name }}</h5>
                    <div class="text-muted small mb-2">{{ $user->email }}</div>
                    <span class="badge {{ $user->hasFullAccess() ? 'bg-success' : 'bg-secondary' }} text-uppercase">{{ $user->role }}</span>
                    <hr>
                    <div class="small text-muted text-start">
                        <div><i class="bi bi-calendar3"></i> Account created {{ $user->created_at?->format('M d, Y') }}</div>
                        <div class="mt-2">
                            @if ($user->hasFullAccess())
                                Creating, deactivating or resetting your <strong>staff's</strong> accounts is done in <a href="{{ route('users.index') }}">Staff Accounts</a>.
                            @else
                                Your role and access are set by the Owner. If you forget your password, ask the Owner to reset it.
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            {{-- Name + email --}}
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-bold">Account details</div>
                <form method="POST" action="{{ route('profile.update') }}" class="card-body">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" for="name">Name</label>
                        <input type="text" id="name" name="name" class="form-control {{ $pe->has('name') ? 'is-invalid' : '' }}" value="{{ old('name', $user->name) }}" required maxlength="255">
                        @if ($pe->has('name'))<div class="invalid-feedback">{{ $pe->first('name') }}</div>@endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email (used to sign in)</label>
                        <input type="email" id="email" name="email" class="form-control {{ $pe->has('email') ? 'is-invalid' : '' }}" value="{{ old('email', $user->email) }}" required maxlength="255">
                        @if ($pe->has('email'))<div class="invalid-feedback">{{ $pe->first('email') }}</div>@endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="{{ ucfirst($user->role) }}" disabled>
                        <div class="form-text">Your role is fixed. It cannot be changed here.</div>
                    </div>

                    <button class="btn btn-success btn-icon" title="Save changes" aria-label="Save changes"><i class="bi bi-check-lg"></i></button>
                </form>
            </div>

            {{-- Password --}}
            <div class="card shadow-sm">
                <div class="card-header fw-bold">Change password</div>
                <form method="POST" action="{{ route('profile.password') }}" class="card-body">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password</label>
                        <div class="input-group">
                            <input type="password" id="current_password" name="current_password" class="form-control {{ $pw->has('current_password') ? 'is-invalid' : '' }}" required autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary js-toggle-pw" tabindex="-1" title="Show / hide"><i class="bi bi-eye"></i></button>
                            @if ($pw->has('current_password'))<div class="invalid-feedback">{{ $pw->first('current_password') }}</div>@endif
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="password">New password</label>
                            <div class="input-group">
                                <input type="password" id="password" name="password" class="form-control {{ $pw->has('password') ? 'is-invalid' : '' }}" required minlength="8" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary js-toggle-pw" tabindex="-1" title="Show / hide"><i class="bi bi-eye"></i></button>
                                @if ($pw->has('password'))<div class="invalid-feedback">{{ $pw->first('password') }}</div>@endif
                            </div>
                            <div class="form-text">At least 8 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirmation">Confirm new password</label>
                            <div class="input-group">
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary js-toggle-pw" tabindex="-1" title="Show / hide"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-success btn-icon" title="Change password" aria-label="Change password"><i class="bi bi-key"></i></button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Eye button: show / hide the password in the box next to it.
        document.querySelectorAll('.js-toggle-pw').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.parentElement.querySelector('input');
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        });
    </script>
@endsection
