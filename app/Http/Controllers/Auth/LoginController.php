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
        // is_active => true：用戶主檔裡被停用的帳號不能登入（被刪除的帳號本來就查不到）。
        $credentials = [$field => $input['login'], 'password' => $input['password'], 'is_active' => true];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            Auth::user()->forceFill(['last_login_at' => now()])->save();
            AuditLogger::log('login', Auth::user(), [], __('audit.messages.login', ['name' => Auth::user()->name]));
            return redirect()->intended('/dashboard');
        }

        // 登入失敗也要留紀錄（稽核常看的項目）：只記輸入的帳號，絕對不記密碼。
        AuditLogger::log('login_failed', null, ['ip' => $request->ip()], __('audit.messages.login_failed', ['account' => $input['login']]));

        // 不特別說明是「密碼錯」還是「帳號被停用」，避免讓外人探測帳號是否存在。
        return back()->withErrors([
            'login' => '帳號或密碼錯誤，或帳號已被停用。',
        ])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        AuditLogger::log('logout', Auth::user(), [], __('audit.messages.logout', ['name' => Auth::user()?->name]));
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
