<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class AttendanceBreakResource extends JsonResource
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
            'break_in' => $this->break_in
                ? Carbon::parse($this->break_in)->format('H:i:s')
                : null,
            'break_out' => $this->break_out
                ? Carbon::parse($this->break_out)->format('H:i:s')
                : null,
        ];
    }
}
