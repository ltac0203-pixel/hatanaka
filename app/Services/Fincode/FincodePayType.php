<?php

declare(strict_types=1);

namespace App\Services\Fincode;

final class FincodePayType
{
    /**
     * Fincode のサブスク作成・削除で必須となる決済種別 (ES001023001 / ES002023001)。
     * 本実装はカード決済のみを想定する。
     */
    public const CARD = 'Card';
}
