<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = null;
        if (! empty($this->fincode_plan_id) || ! empty($this->plan_name)) {
            $snapshot = $this->plan_snapshot ?? [];
            $plan = new PlanResource(array_merge($snapshot, [
                'fincode_plan_id' => $this->fincode_plan_id,
                'name' => $this->plan_name,
                'amount' => $this->plan_amount,
                'interval' => $this->plan_interval,
                'interval_count' => $this->plan_interval_count,
            ]));
        }

        return [
            'id' => $this->id,
            'fincode_subscription_id' => $this->fincode_subscription_id,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'stop_date' => $this->stop_date?->toDateString(),
            'next_charge_date' => $this->next_charge_date?->toDateString(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'plan' => $plan,
            'card' => new CardResource($this->whenLoaded('card')),
        ];
    }
}
