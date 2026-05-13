<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * email 比較を Web の lowercase ルールと噛み合わせるため、判定前に小文字へ正規化する。
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
        // email を変える場合のみ current_password を要求する。セッション奪取された攻撃者が
        // パスワード未知のままで email を書き換えてメール検証 / リセット経路から完全乗っ取りに
        // 移行することを阻止するため。同じ email のままなら影響面はないので追加の摩擦を避ける。
        $emailChanging = $this->input('email') !== $this->user()?->email;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()?->id ?? 0),
            ],
            'current_password' => $emailChanging
                ? ['required', 'string', 'current_password']
                : ['sometimes', 'nullable', 'string'],
        ];
    }
}
