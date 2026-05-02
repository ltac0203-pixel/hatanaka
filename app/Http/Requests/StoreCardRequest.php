<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class StoreCardRequest extends FormRequest
{
    /**
     * トークン二重送信検出用キャッシュの保持時間 (秒)。Fincode の単発トークン仕様に合わせ短期で十分。
     */
    public const TOKEN_REPLAY_TTL_SECONDS = 300;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Fincode のカードトークンは英数字 + ハイフン/アンダースコア。形式チェックで明らかな不正値を弾く。
            'token' => ['required', 'string', 'min:20', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $token = (string) $this->input('token');
            $cacheKey = 'card_token_used:'.hash('sha256', $token);

            // Cache::add で原子的に「初回のみ true」を取得。再送信時は false が返り 4xx で拒否する。
            if (! Cache::add($cacheKey, true, self::TOKEN_REPLAY_TTL_SECONDS)) {
                $validator->errors()->add('token', 'このトークンは既に使用されています。');
            }
        });
    }
}
