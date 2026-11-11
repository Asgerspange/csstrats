<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Login/logout for the isolated partner (referrer) dashboard. Uses the
 * dedicated "referrer" session guard so partners never touch the product app.
 */
class PartnerAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('referrer')->check()) {
            return redirect()->route('partner.dashboard');
        }

        return view('partner.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('referrer')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if (! Auth::guard('referrer')->user()->is_active) {
            Auth::guard('referrer')->logout();

            throw ValidationException::withMessages([
                'email' => 'This account is disabled.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('partner.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('referrer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('partner.login');
    }
}
