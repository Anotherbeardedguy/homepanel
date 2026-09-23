<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route($request->user()->isAdmin() ? 'admin.dashboard' : 'admin.chores.index');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Kirjautumistunnus puuttuu.',
            'password.required' => 'Salasana puuttuu.',
        ]);

        if (! Auth::attempt(['username' => $credentials['username'], 'password' => $credentials['password']])) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Tunnus tai salasana ei kelpaa.']);
        }

        $request->session()->regenerate();

        $user = $request->user();
        $fallback = $user->isAdmin() ? route('admin.dashboard') : route('admin.chores.index');

        return redirect()->intended($fallback);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
