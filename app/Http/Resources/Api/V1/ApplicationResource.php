<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'requested_clock_in' => $this->requested_clock_in?->format('H:i:s'),
            'requested_clock_out' => $this->requested_clock_out?->format('H:i:s'),
            'requested_comment' => $this->requested_comment,
            'status' => $this->status,
            'breaks' => $this->whenLoaded(
                'correctionBreaks',
                fn () => $this->correctionBreaks->map(
                    fn ($break) => [
                        'id' => $break->id,
                        'requested_break_in' => $break->requested_break_in?->format('H:i:s'),
                        'requested_break_out' => $break->requested_break_out?->format('H:i:s'),
                    ]
                )
            ),
        ];
    }
}
