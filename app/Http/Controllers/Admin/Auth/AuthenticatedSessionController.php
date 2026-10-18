<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * 管理者の認証に成功した場合、セッションを再生成する。
     *
     * @param  Request  $request  ログイン情報を含むリクエスト
     * @return RedirectResponse 管理者の勤怠一覧へのリダイレクト
     *
     * @throws ValidationException 入力検証または認証に失敗した場合
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
            'admin_status' => true,
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect('/admin/attendance/list');
    }

    /**
     * ログアウトし、セッションとCSRFトークンを更新する。
     *
     * @param  Request  $request  現在のセッションを含むリクエスト
     * @return RedirectResponse 管理者ログイン画面へのリダイレクト
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
