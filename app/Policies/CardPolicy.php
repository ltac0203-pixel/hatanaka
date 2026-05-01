<?php

namespace App\Policies;

use App\Models\FincodeCard;
use App\Models\User;

class CardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * 他人のカード削除を防ぐため所有者だけに権限を絞る。
     */
    public function delete(User $user, FincodeCard $card): bool
    {
        return $user->id === $card->user_id;
    }
}
