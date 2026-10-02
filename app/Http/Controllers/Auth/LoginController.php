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
        $input = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        // 同一個欄位同時支援「帳號名稱」與「Email」：長得像 Email 就用 email 欄位比對，
        // 否則用 username 欄位比對。
        $field = filter_var($input['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [$field => $input['login'], 'password' => $input['password']];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            AuditLogger::log('login', Auth::user());
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'login' => '帳號或密碼錯誤。',
        ])->onlyInput('login');
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
