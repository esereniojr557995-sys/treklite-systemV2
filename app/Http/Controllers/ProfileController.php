<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * "My Profile": every signed-in person (Owner or Staff) can change their OWN name, email and password here.
 * This is separate from User Accounts, which is where the Owner creates / deactivates other people's accounts.
 */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /** Own name + email. Role and active status can never be changed from here. */
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validateWithBag('profile', [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return redirect()->route('profile.edit')->with('status', 'Your profile was updated.');
    }

    /** Own password. The current password must be entered first. */
    public function password(Request $request)
    {
        $user = $request->user();

        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], [
            'current_password.current_password' => 'The current password is not correct.',
            'password.different'                => 'The new password must be different from the current one.',
        ]);

        // The User model hashes the password automatically.
        $user->update(['password' => $data['password']]);

        return redirect()->route('profile.edit')->with('status', 'Your password was changed.');
    }
}
