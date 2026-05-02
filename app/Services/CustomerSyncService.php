<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\CustomerCreated;
use App\Exceptions\FincodeApiException;
use App\Models\FincodeCustomer;
use App\Models\User;
use App\Services\Fincode\CustomerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerSyncService
{
    protected CustomerService $customerService;

    public function __construct(
        CustomerService $customerService,
        private RequestContextResolver $requestContextResolver
    ) {
        $this->customerService = $customerService;
    }

    /**
     * 後続処理が必ず Fincode 顧客IDを使えるよう存在を保証する。
     */
    public function ensureCustomerExists(User $user): FincodeCustomer
    {
        // Fast path: 既存顧客はロック不要
        $customer = $user->fincodeCustomer()->first();
        if ($customer) {
            return $customer;
        }

        // Slow path: User 行ロック → 再チェック → 作成
        return DB::transaction(function () use ($user) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $customer = $user->fincodeCustomer()->first();
            if ($customer) {
                Log::info('顧客作成の競合を検出、既存レコードを返却', [
                    'user_id' => $user->id,
                ]);

                return $customer;
            }

            return $this->createCustomerInternal($user);
        });
    }

    /**
     * Fincode API呼び出しとローカル保存を行う内部メソッド。
     * 呼び出し元でUser行のロック取得済みが前提。
     */
    private function createCustomerInternal(User $user): FincodeCustomer
    {
        $requestContext = $this->requestContextResolver->resolve();

        // 外部顧客の作成に失敗したままローカルだけ残る状態を防ぐ。
        try {
            $response = $this->customerService->create([
                'name' => $user->name,
                'email' => $user->email,
            ]);
        } catch (FincodeApiException $e) {
            Log::error('Failed to create customer on Fincode', [
                'user_id' => $user->id,
                'exception_class' => $e::class,
                'status_code' => $e->getStatusCode(),
            ]);
            throw $e;
        }

        // 後続のカード・契約処理で再利用できるようローカルへ保存する。
        $customer = FincodeCustomer::create(array_merge(
            [
                'user_id' => $user->id,
                'fincode_customer_id' => $response['id'],
            ],
            $this->extractCustomerAttributes($response),
        ));

        $event = new CustomerCreated(
            $customer,
            $user,
            [],
            $customer->toArray(),
            [],
            $requestContext->ipAddress,
            $requestContext->userAgent
        );

        // 顧客作成が確定した後だけ監査イベントを配信する。
        DB::afterCommit(static fn () => event($event));

        return $customer;
    }

    private function extractCustomerAttributes(array $response): array
    {
        return [
            'name' => $response['name'],
            'email' => $response['email'],
            'phone_cc' => $response['phone_cc'] ?? null,
            'phone_no' => $response['phone_no'] ?? null,
            'addr_country' => $response['addr_country'] ?? null,
            'addr_state' => $response['addr_state'] ?? null,
            'addr_city' => $response['addr_city'] ?? null,
            'addr_line_1' => $response['addr_line_1'] ?? null,
            'addr_line_2' => $response['addr_line_2'] ?? null,
            'addr_post_code' => $response['addr_post_code'] ?? null,
            'metadata' => $response['metadata'] ?? null,
            'synced_at' => now(),
        ];
    }

    /**
     * Fincode 側の正を取り込み、ローカル表示と食い違わないようにする。
     */
    public function syncCustomer(FincodeCustomer $customer): void
    {
        try {
            $response = $this->customerService->getCustomer($customer->fincode_customer_id);
        } catch (FincodeApiException $e) {
            Log::error('Failed to sync customer from Fincode', [
                'fincode_customer_id' => $customer->fincode_customer_id,
                'exception_class' => $e::class,
                'status_code' => $e->getStatusCode(),
            ]);
            throw $e;
        }

        DB::transaction(function () use ($customer, $response) {
            $customer->update($this->extractCustomerAttributes($response));
        });
    }
}
