<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\FincodeCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSubscriptionRequest extends FormRequest
{
    protected ?FincodeCard $validatedCard = null;

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
            'fincode_plan_id' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            // exists を user_id でスコープし、他人の card_id 存在を validation メッセージから判別できないようにする (IDOR/列挙対策)。
            'card_id' => [
                'required',
                Rule::exists('fincode_cards', 'id')
                    ->where(fn ($query) => $query->where('user_id', $this->user()?->id)
                        ->whereNull('deleted_at')),
            ],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $user = $this->user();

                if ($user->hasActiveSubscription()) {
                    $validator->errors()->add('fincode_plan_id', '既にアクティブなサブスクリプションがあります。');

                    return;
                }

                $card = $user->fincodeCards()->find($this->validated('card_id'));
                if (! $card) {
                    $validator->errors()->add('card_id', 'このカードは使用できません。');

                    return;
                }

                if ($card->isExpired()) {
                    $validator->errors()->add('card_id', 'このカードは期限切れです。');

                    return;
                }

                $this->validatedCard = $card;
            },
        ];
    }

    public function getValidatedCard(): ?FincodeCard
    {
        return $this->validatedCard;
    }
}
