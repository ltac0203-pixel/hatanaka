<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'status' => $this->status,
            'amount' => $this->amount,
            'tax' => $this->tax,
            'charged_at' => $this->charged_at?->toIso8601String(),
            'charged_at_date' => $this->charged_at_date?->toDateString(),
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
