<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CardResource;
use App\Services\Fincode\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(
        private PlanService $planService
    ) {}

    public function index(Request $request): Response
    {
        $plans = $this->planService->listActivePlans();

        return Inertia::render('Plan/Index', [
            'plans' => $plans,
        ]);
    }

    public function show(Request $request, string $fincode_plan_id): Response|RedirectResponse
    {
        $plan = $this->planService->findActivePlan($fincode_plan_id);

        if (! $plan) {
            return redirect()->route('plans.index')
                ->with('error', 'このプランは現在利用できません。');
        }

        $cards = $request->user()->fincodeCards()->get();

        return Inertia::render('Plan/Show', [
            'plan' => $plan,
            'cards' => CardResource::collection($cards),
            'hasActiveSubscription' => $request->user()->hasActiveSubscription(),
            'minimumStartDate' => today()->toDateString(),
        ]);
    }
}
