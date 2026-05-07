<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
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
            // 期限切れカード/重複契約の業務不変条件は SubscriptionManager 側で例外として扱い、
            // bootstrap/app.php の render で errors.card_id / errors.fincode_plan_id にマッピングする。
            'card_id' => [
                'required',
                'integer',
                Rule::exists('fincode_cards', 'id')
                    ->where(fn ($query) => $query->where('user_id', $this->user()?->id)
                        ->whereNull('deleted_at')),
            ],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
