<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Inertia の `auth.user` 共有 props は全画面で毎回シリアライズされるので、
     * クライアント (`resources/js/types/index.d.ts` の `User`) が実際に使っている
     * フィールドだけに絞り、過剰露出と payload サイズを最小化する。
     * 新規フィールドを足すときは TS 側の `User` 型にも必ずミラーすること。
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
