<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
{
    /** @var list<string> */
    public const ALLOWED_ABILITIES = [
        'subscription:read',
        'subscription:write',
        'card:read',
        'card:write',
    ];

    /** @var list<string> */
    public const DEFAULT_ABILITIES = [
        'subscription:read',
        'card:read',
    ];

    /**
     * `RateLimiter::for('api-login')` のキーと AuthController::login の
     * `Auth::attempt` / `User::where('email', ...)` が同じ小文字キーを参照するよう、
     * バリデート前に email を正規化する (DB の照合がケースセンシティブな環境で
     * lookup miss するのを防ぐ)。
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => Str::lower($this->input('email')),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', 'in:'.implode(',', self::ALLOWED_ABILITIES)],
        ];
    }
}
