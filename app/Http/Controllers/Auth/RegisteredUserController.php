<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRegisteredUserRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * @throws ValidationException
     */
    public function store(StoreRegisteredUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // MustVerifyEmail: 登録イベントを発火して検証メール送信通知をリスナに任せる。
        event(new Registered($user));

        Auth::login($user);

        // Session Fixation 対策: ログイン直後に必ず ID を再生成し、
        // 攻撃者が事前に固定したセッション ID を認証済みセッションへ昇格させない。
        $request->session()->regenerate();

        // dashboard は 'verified' で保護されているので、未検証ユーザーは Laravel が
        // 自動で verification.notice に飛ばす。明示的にも同じ URL を指す。
        return to_route('verification.notice');
    }
}
