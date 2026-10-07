<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => '請輸入帳號 Email。',
            'email.email' => '帳號 Email 格式不正確。',
            'password.required' => '請輸入密碼。',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            AuditLogger::log('login', Auth::user());
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => '帳號或密碼錯誤。',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        AuditLogger::log('logout', Auth::user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
