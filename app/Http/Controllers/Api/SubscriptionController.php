<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\SubscriptionResultResource;
use App\Models\Subscription;
use App\Models\SubscriptionResult;
use App\Services\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $subscription = $this->findActiveSubscription($request);

        if (! $subscription) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => new SubscriptionResource($subscription),
        ]);
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $subscription = $this->subscriptionManager->createForPlan(
            $request->user(),
            $validated['fincode_plan_id'],
            (int) $validated['card_id'],
            $validated['start_date']
        );

        return response()->json([
            'data' => new SubscriptionResource($subscription),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $subscription = $this->findActiveSubscription($request);

        if (! $subscription) {
            return response()->json([
                'message' => 'アクティブなサブスクリプションがありません。',
            ], 404);
        }

        $this->authorize('delete', $subscription);

        $this->subscriptionManager->cancel($subscription);

        return response()->json(['message' => 'サブスクリプションを解約しました。']);
    }

    public function history(Request $request): AnonymousResourceCollection
    {
        $results = SubscriptionResult::with('subscription')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('charged_at')
            ->paginate(20);

        return SubscriptionResultResource::collection($results);
    }

    private function findActiveSubscription(Request $request): ?Subscription
    {
        return $request->user()
            ->activeSubscription()
            ->with('card')
            ->first();
    }
}
