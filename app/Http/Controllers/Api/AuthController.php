<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            return response()->json([
                'message' => 'メールアドレスまたはパスワードが正しくありません。',
            ], 422);
        }

        $user = $request->user() ?? User::where('email', $validated['email'])->first();

        // モバイルアプリなどの継続利用では、セッションの代わりにAPIトークンを返せるようにする。
        if (! empty($validated['device_name'])) {
            // クライアントが要求した abilities をホワイトリストでフィルタし、
            // 未指定時は最小権限 (read のみ) でトークン発行することで PoLP を担保する。
            $requested = $validated['abilities'] ?? LoginRequest::DEFAULT_ABILITIES;
            $abilities = array_values(array_intersect(LoginRequest::ALLOWED_ABILITIES, $requested));
            if ($abilities === []) {
                $abilities = LoginRequest::DEFAULT_ABILITIES;
            }

            $hasWriteAbility = (bool) array_filter(
                $abilities,
                static fn (string $ability): bool => str_ends_with($ability, ':write'),
            );
            // 書き込み権限を含むトークンは漏洩時の被害を抑えるため寿命を短縮する。
            $expiresAt = $hasWriteAbility ? now()->addDays(7) : now()->addDays(30);

            // 同じ device_name の既存トークンは破棄してローテーションを強制する。
            // 漏洩した古いトークンが寿命まで併用されるリスクを排除し、再ログインで失効を確定させる。
            $user->tokens()->where('name', $validated['device_name'])->delete();

            $token = $user->createToken($validated['device_name'], $abilities, $expiresAt)->plainTextToken;

            return response()->json([
                'user' => new UserResource($user),
                'token' => $token,
                'abilities' => $abilities,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->user()?->currentAccessToken() instanceof PersonalAccessToken) {
            $request->user()->currentAccessToken()->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'ログアウトしました。']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function sessionStatus(Request $request): JsonResponse
    {
        return response()->json([
            'authenticated' => Auth::check(),
        ]);
    }
}
