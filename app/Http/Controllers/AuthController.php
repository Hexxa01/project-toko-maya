<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function form()
    {
        return view('auth.form');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt([...$data, 'active' => true], $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau kata sandi tidak cocok.'])->onlyInput('email');
        }
        $r->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(8)]]);
        $user = User::create($data);
        Auth::login($user);
        $r->session()->regenerate();

        return redirect()->route('home');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function dashboard(Request $r)
    {
        abort_unless($r->user()->active, 403);

        return redirect()->route(match ($r->user()->role) {
            'pemilik' => 'owner.index','kasir' => 'cashier.index','admin_gudang' => 'warehouse.index',default => 'orders.index'
        });
    }
}
