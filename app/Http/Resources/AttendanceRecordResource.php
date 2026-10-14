<?php

namespace App\Http\Resources;

use App\Http\Resources\Api\V1\ApplicationResource;
use App\Http\Resources\Api\V1\AttendanceBreakResource;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pendingApplication = $this->pendingApplication();

        $clockIn = $pendingApplication?->requested_clock_in
            ?? $this->clock_in;

        $clockOut = $pendingApplication?->requested_clock_out
            ?? $this->clock_out;

        $breakSeconds = $this->breakSeconds($pendingApplication);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,

            'user_name' => $this->when(
                $this->relationLoaded('user'),
                fn () => $this->user?->name
            ),

            'user' => new UserResource(
                $this->whenLoaded('user')
            ),

            'date' => $this->date?->format('Y-m-d'),

            'clock_in' => $clockIn?->format('H:i:s'),
            'clock_out' => $clockOut?->format('H:i:s'),

            'total_time' => $this->totalTime(
                $clockIn,
                $clockOut,
                $breakSeconds
            ),

            'total_break_time' => $clockIn && $clockOut
                ? $this->formatSeconds($breakSeconds)
                : null,

            'comment' => $pendingApplication?->requested_comment
                ?? $this->comment,

            'breaks' => $this->when(
                $this->relationLoaded('breaks'),
                function () use ($pendingApplication) {
                    if ($pendingApplication) {
                        return $pendingApplication->correctionBreaks
                            ->map(fn ($break) => [
                                'id' => $break->id,
                                'break_in' => $break->requested_break_in?->format('H:i:s'),
                                'break_out' => $break->requested_break_out?->format('H:i:s'),
                            ])
                            ->values();
                    }

                    return AttendanceBreakResource::collection(
                        $this->breaks
                    );
                }
            ),

            'applications' => ApplicationResource::collection(
                $this->whenLoaded('correctionRequests')
            ),
        ];
    }

    /**
     * Get the latest pending correction request.
     */
    private function pendingApplication()
    {
        if (! $this->relationLoaded('correctionRequests')) {
            return null;
        }

        return $this->correctionRequests
            ->where('status', 0)
            ->sortByDesc('id')
            ->first();
    }

    /**
     * Calculate break seconds.
     */
    private function breakSeconds($pendingApplication): int
    {
        if ($pendingApplication) {
            return (int) $pendingApplication->correctionBreaks
                ->sum(function ($break) {
                    if (
                        ! $break->requested_break_in
                        || ! $break->requested_break_out
                    ) {
                        return 0;
                    }

                    return $break->requested_break_in
                        ->diffInSeconds($break->requested_break_out);
                });
        }

        if (! $this->relationLoaded('breaks')) {
            return 0;
        }

        return (int) $this->breaks->sum(function ($break) {
            if (! $break->break_in || ! $break->break_out) {
                return 0;
            }

            return $break->break_in
                ->diffInSeconds($break->break_out);
        });
    }

    /**
     * Calculate total working time.
     */
    private function totalTime(
        $clockIn,
        $clockOut,
        int $breakSeconds
    ): ?string {
        if (! $clockIn || ! $clockOut) {
            return null;
        }

        $workSeconds = (int) $clockIn->diffInSeconds($clockOut);

        return $this->formatSeconds(
            $workSeconds - $breakSeconds
        );
    }

    /**
     * Convert seconds to HH:MM.
     */
    private function formatSeconds(int $seconds): string
    {
        return gmdate('H:i', max(0, $seconds));
    }
}
