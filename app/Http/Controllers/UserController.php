<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleVoid;
use App\Models\StockMovement;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Owner only: STAFF accounts (create, edit, deactivate, delete).
 * The Owner's own name / email / password are changed in My Profile, not here.
 */
class UserController extends Controller
{
    public function index()
    {
        $users = User::where('role', 'staff')->orderByDesc('is_active')->orderBy('name')->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.form', ['user' => new User(['role' => 'staff', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create($data + ['role' => 'staff', 'is_active' => true]);
        ActivityLog::record('staff_add', 'Added staff account ' . $data['name'] . ' (' . $data['email'] . ').');

        return redirect()->route('users.index')->with('status', 'Staff account created for ' . $data['name'] . '.');
    }

    public function edit(User $user)
    {
        $this->staffOnly($user);

        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $this->staffOnly($user);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'is_active' => ['required', 'boolean'],
            'password'  => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $passwordChanged = ! blank($data['password'] ?? null);
        if (! $passwordChanged) {
            unset($data['password']);
        }
        $data['is_active'] = (bool) $data['is_active'];

        $user->update($data);
        ActivityLog::record('staff_edit', 'Edited staff account ' . $user->name
            . ($data['is_active'] ? '' : ' - account DEACTIVATED')
            . ($passwordChanged ? ' - password reset' : '') . '.');

        return redirect()->route('users.index')->with('status', 'Account of ' . $user->name . ' updated.');
    }

    public function destroy(User $user)
    {
        $this->staffOnly($user);

        if (Sale::where('user_id', $user->id)->exists() || StockMovement::where('user_id', $user->id)->exists() || SaleVoid::where('voided_by', $user->id)->exists()) {
            return back()->withErrors(['user' => $user->name . ' already has recorded sales or stock changes, so the account cannot be deleted. Deactivate it instead.']);
        }

        $user->delete();
        ActivityLog::record('staff_delete', 'Deleted staff account ' . $user->name . ' (' . $user->email . ').');

        return redirect()->route('users.index')->with('status', 'Account deleted.');
    }

    /** This page only manages staff. The Owner's own account is edited in My Profile. */
    private function staffOnly(User $user): void
    {
        abort_unless($user->role === 'staff', 404);
    }
}
