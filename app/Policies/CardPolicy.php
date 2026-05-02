<?php

namespace App\Policies;

use App\Models\FincodeCard;
use App\Models\User;

class CardPolicy
{
    /**
     * カード一覧表示は認証済みユーザーに限定し、未認証列挙経路の defense-in-depth とする。
     * Controller 側で $request->user()->fincodeCards() 経由でフィルタ済みのため一覧自体は安全だが、
     * 認証チェックを policy 層にも置くことで誤って公開された場合の保険となる。
     */
    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    /**
     * カード詳細表示も所有者のみに制限する。Route Model Binding で他人のカードが渡された場合の保険。
     */
    public function view(User $user, FincodeCard $card): bool
    {
        return $user->id === $card->user_id;
    }

    /**
     * 他人のカード削除を防ぐため所有者だけに権限を絞る。
     */
    public function delete(User $user, FincodeCard $card): bool
    {
        return $user->id === $card->user_id;
    }
}
