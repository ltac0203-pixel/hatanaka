<?php

declare(strict_types=1);

namespace App\Services\Fincode;

/**
 * Fincode への書き込み呼び出し向けに決定論的な Idempotency-Key を組み立てる。
 *
 * ランダム UUID を都度発番すると「同一意図の再試行」と「別の意図の操作」の区別が外部から付かず、
 * 二重送信時に二重作成を許してしまう。同じ操作には同じキー、別の操作には別のキーを保証するため、
 * 操作名 + 主要識別子の組から SHA-256 でキーを構築する。
 */
final class IdempotencyKey
{
    /**
     * @param  list<string|int>  $components
     */
    public static function for(string $operation, array $components): string
    {
        $payload = implode('|', array_map(static fn ($component): string => (string) $component, $components));

        return $operation.':'.hash('sha256', $payload);
    }
}
