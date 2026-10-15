<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Tell a deactivated user why they cannot get in.
        $user = User::where('email', $credentials['email'])->first();
        if ($user && ! $user->is_active && Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'This account has been deactivated. Please contact the Owner.'])->onlyInput('email');
        }

        if (Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Staff are sent on to New Sale by the dashboard route.
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['email' => 'The email or password is incorrect.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
