<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public const ALLOWED_ABILITIES = [
        'subscription:read',
        'subscription:write',
        'card:read',
        'card:write',
    ];

    public const DEFAULT_ABILITIES = [
        'subscription:read',
        'card:read',
    ];

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
