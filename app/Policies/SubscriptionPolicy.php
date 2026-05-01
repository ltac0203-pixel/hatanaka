<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    /**
     * 一覧取得は専用導線に限定し、ポリシー経由では許可しない。
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * 他人の契約を閲覧できないよう所有者だけに絞る。
     */
    public function view(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->user_id;
    }

    /**
     * 作成権限は専用フローで制御し、ポリシー経由では開放しない。
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * 更新操作は専用フローに限定し、直接更新を防ぐ。
     */
    public function update(User $user, Subscription $subscription): bool
    {
        return false;
    }

    /**
     * 他人の契約解約を防ぐため所有者だけに権限を絞る。
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->id === $subscription->user_id;
    }

    /**
     * 契約復元は未対応のため常に拒否する。
     */
    public function restore(User $user, Subscription $subscription): bool
    {
        return false;
    }

    /**
     * 監査可能性を失わないよう物理削除は許可しない。
     */
    public function forceDelete(User $user, Subscription $subscription): bool
    {
        return false;
    }
}
