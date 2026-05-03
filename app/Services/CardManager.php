<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\CardDeleted;
use App\Events\CardRegistered;
use App\Exceptions\CardInUseException;
use App\Exceptions\FincodeApiException;
use App\Models\FincodeCard;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Fincode\CardService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CardManager
{
    protected CardService $cardService;

    /**
     * カード登録前に顧客同期の前提を満たすために利用する。
     */
    protected CustomerSyncService $customerSyncService;

    /**
     * 現在のリクエスト由来コンテキストを監査イベントへ固定する。
     */
    protected RequestContextResolver $requestContextResolver;

    public function __construct(
        CardService $cardService,
        CustomerSyncService $customerSyncService,
        RequestContextResolver $requestContextResolver
    ) {
        $this->cardService = $cardService;
        $this->customerSyncService = $customerSyncService;
        $this->requestContextResolver = $requestContextResolver;
    }

    public function create(User $user, string $token, bool $isDefault = false): FincodeCard
    {
        $requestContext = $this->requestContextResolver->resolve();

        return DB::transaction(function () use ($user, $token, $isDefault, $requestContext) {
            // Fincode 顧客が無いまま登録すると整合性が崩れるため、先に同期を保証する。
            $customer = $this->customerSyncService->ensureCustomerExists($user);

            // デフォルトカードの切り替えと初回判定を競合状態に晒さないため、ユーザー行で排他ロックを取る。
            // 同一ユーザーで並列に create() が走っても is_default=true が複数生成されない。
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            // デフォルトカードを1枚に保ち、決済対象がぶれないようにする。
            if ($isDefault) {
                $user->fincodeCards()->update(['is_default' => false]);
            } elseif (! $user->fincodeCards()->exists()) {
                // 初回登録カードは選択不能状態を避けるため自動で既定にする。
                // ロック取得後の確定 SQL に統一し、relationLoaded に依存した分岐を排除する。
                $isDefault = true;
            }

            // 外部サービスで登録が失敗した場合にローカルだけ残る不整合を防ぐ。
            try {
                $response = $this->cardService->create(
                    $customer->fincode_customer_id,
                    $token,
                    $isDefault
                );
            } catch (FincodeApiException $e) {
                Log::error('Failed to create card on Fincode', [
                    'user_id' => $user->id,
                    'exception_class' => $e::class,
                    'status_code' => $e->getStatusCode(),
                ]);
                throw $e;
            }

            // Fincode の card レスポンスは expire を YYMM (年下2桁 + 月) 形式の 4 桁文字列で返す。
            // 後続の請求や画面表示の都合で年/月に分けて保存する。
            [$expMonth, $expYear] = $this->splitExpire((string) ($response['expire'] ?? ''));

            // 以後の決済や画面表示で参照できるようローカルへ反映する。
            $card = FincodeCard::create([
                'user_id' => $user->id,
                'fincode_customer_id' => $customer->fincode_customer_id,
                'fincode_card_id' => $response['id'],
                'brand' => $response['brand'],
                'last4' => substr($response['card_no'], -4),
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'holder_name' => $response['holder_name'] ?? null,
                'is_default' => $isDefault,
            ]);

            $event = new CardRegistered(
                $card,
                $user,
                [],
                $card->toArray(),
                [],
                $requestContext->ipAddress,
                $requestContext->userAgent
            );

            // 永続化が確定した後だけ副作用を走らせる。
            DB::afterCommit(static fn () => event($event));

            return $card;
        });
    }

    /**
     * Fincode が返す expire (YYMM 4 桁) を [月, 年(4桁)] に分解する。
     *
     * @return array{0:int,1:int}
     */
    private function splitExpire(string $expire): array
    {
        $digits = (string) preg_replace('/\D/', '', $expire);
        if (strlen($digits) !== 4) {
            return [0, 0];
        }

        $year = 2000 + (int) substr($digits, 0, 2);
        $month = (int) substr($digits, 2, 2);

        return [$month, $year];
    }

    public function delete(FincodeCard $card): void
    {
        $requestContext = $this->requestContextResolver->resolve();
        $hasActiveSubscription = Subscription::active()
            ->where('fincode_card_id', $card->fincode_card_id)
            ->exists();

        if ($hasActiveSubscription) {
            throw new CardInUseException;
        }

        DB::transaction(function () use ($card, $requestContext) {
            $card->loadMissing('user');
            $user = $card->getRelation('user');

            if (! $user instanceof User) {
                throw (new ModelNotFoundException)->setModel(User::class, [$card->user_id]);
            }

            $oldValues = $card->toArray();

            // 外部サービスに残骸を作らないよう、先に Fincode 側を削除する。
            try {
                $this->cardService->deleteCard(
                    $card->fincode_customer_id,
                    $card->fincode_card_id
                );
            } catch (FincodeApiException $e) {
                Log::error('Failed to delete card on Fincode', [
                    'card_id' => $card->id,
                    'exception_class' => $e::class,
                    'status_code' => $e->getStatusCode(),
                ]);
                throw $e;
            }

            // 既定カードが空になると次回決済で選択不能になるため、最古の残カードを補充する。
            if ($card->is_default) {
                $newDefaultCard = $user->fincodeCards()
                    ->where('id', '!=', $card->id)
                    ->oldest('id')
                    ->first();

                if ($newDefaultCard) {
                    $newDefaultCard->update(['is_default' => true]);
                }
            }

            // 外部削除後にローカルも消し、表示上の不整合を防ぐ。
            $card->delete();

            $event = new CardDeleted(
                $card,
                $user,
                $oldValues,
                [],
                [],
                $requestContext->ipAddress,
                $requestContext->userAgent
            );

            // 削除が確定した後だけ監査イベントを配信する。
            DB::afterCommit(static fn () => event($event));
        });
    }
}
