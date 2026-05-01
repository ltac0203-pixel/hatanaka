<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fincodePlanId = (string) data_get($this->resource, 'fincode_plan_id', data_get($this->resource, 'id', ''));
        $amount = (int) data_get($this->resource, 'amount', 0);
        $interval = (string) data_get($this->resource, 'interval', 'monthly');
        $intervalCount = max((int) data_get($this->resource, 'interval_count', 1), 1);

        return [
            'id' => $fincodePlanId,
            'fincode_plan_id' => $fincodePlanId,
            'name' => (string) data_get($this->resource, 'name', ''),
            'description' => data_get($this->resource, 'description'),
            'amount' => $amount,
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'status' => (string) data_get($this->resource, 'status', 'active'),
            'features' => data_get($this->resource, 'features'),
            'price_display' => (string) data_get(
                $this->resource,
                'price_display',
                '¥'.number_format($amount).'/'.$this->intervalLabel($interval, $intervalCount)
            ),
            'interval_label' => (string) data_get(
                $this->resource,
                'interval_label',
                $this->intervalLabel($interval, $intervalCount)
            ),
        ];
    }

    private function intervalLabel(string $interval, int $intervalCount): string
    {
        $labels = [
            'monthly' => '月',
            'yearly' => '年',
            'weekly' => '週',
            'daily' => '日',
        ];

        $label = $labels[$interval] ?? $interval;

        return $intervalCount > 1 ? "{$intervalCount}{$label}" : $label;
    }
}
