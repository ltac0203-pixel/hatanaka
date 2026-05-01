<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Services\Fincode\PlanService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController extends Controller
{
    public function __construct(
        private PlanService $planService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $plans = $this->planService->listActivePlans();

        return PlanResource::collection(collect($plans));
    }
}
