<?php

namespace App\Http\Resources\Progress;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'completed_lessons_count' => $data['completed_lessons_count'] ?? 0,
            'total_lessons_count' => $data['total_lessons_count'] ?? 0,
            'levels' => $data['levels'] ?? [],
        ];
    }
}
