<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\FincodeApiException;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Resources\CardResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\SubscriptionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionManager $subscriptionManager
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $subscription = $user->activeSubscription()->with('card')->first();
        $cards = $user->fincodeCards()->get();

        return Inertia::render('Subscription/Index', [
            'subscription' => $subscription ? json_decode((new SubscriptionResource($subscription))->toJson(), true) : null,
            'cards' => CardResource::collection($cards)->resolve(),
        ]);
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $this->subscriptionManager->createForPlan(
                $request->user(),
                $validated['fincode_plan_id'],
                $request->getValidatedCard(),
                $validated['start_date']
            );
        } catch (FincodeApiException) {
            return redirect()->route('subscription.index')
                ->withErrors([
                    'subscription' => 'サブスクリプションの登録に失敗しました。時間をおいて再試行してください。',
                ]);
        }

        return redirect()->route('subscription.index')
            ->with('success', 'サブスクリプションを登録しました。');
    }

    public function destroy(Request $request, Subscription $subscription): RedirectResponse
    {
        $this->authorize('delete', $subscription);

        try {
            $this->subscriptionManager->cancel($subscription);
        } catch (FincodeApiException) {
            return redirect()->route('subscription.index')
                ->withErrors([
                    'subscription' => 'サブスクリプションの解約に失敗しました。時間をおいて再試行してください。',
                ]);
        }

        return redirect()->route('subscription.index')
            ->with('success', 'サブスクリプションを解約しました。');
    }
}
