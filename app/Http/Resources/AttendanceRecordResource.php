<?php

namespace App\Http\Resources;

use App\Http\Resources\Api\V1\ApplicationResource;
use App\Http\Resources\Api\V1\AttendanceBreakResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\AttendanceCorrectionBreak;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTime;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class AttendanceRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request  現在のAPIリクエスト
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
                fn (): ?string => $this->user?->name
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
                function () use ($pendingApplication): Collection|AnonymousResourceCollection {
                    if ($pendingApplication) {
                        return $pendingApplication->correctionBreaks
                            ->map(fn (AttendanceCorrectionBreak $break): array => [
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
     * 読み込み済みの修正申請から最新の承認待ち申請を取得する。
     *
     * @return AttendanceCorrectionRequest|null 最新の承認待ち申請。なければnull
     */
    private function pendingApplication(): ?AttendanceCorrectionRequest
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
     * 申請中の休憩、または正式な休憩から合計秒数を算出する。
     *
     * @param  AttendanceCorrectionRequest|null  $pendingApplication  承認待ち申請
     * @return int 終了済み休憩の合計秒数
     */
    private function breakSeconds(
        ?AttendanceCorrectionRequest $pendingApplication
    ): int {
        if ($pendingApplication) {
            return (int) $pendingApplication->correctionBreaks
                ->sum(function (AttendanceCorrectionBreak $break): int {
                    if (
                        ! $break->requested_break_in
                        || ! $break->requested_break_out
                    ) {
                        return 0;
                    }

                    return (int) $break->requested_break_in
                        ->diffInSeconds($break->requested_break_out);
                });
        }

        if (! $this->relationLoaded('breaks')) {
            return 0;
        }

        return (int) $this->breaks->sum(function (BreakTime $break): int {
            if (! $break->break_in || ! $break->break_out) {
                return 0;
            }

            return (int) $break->break_in
                ->diffInSeconds($break->break_out);
        });
    }

    /**
     * 出退勤時刻と休憩秒数から実勤務時間を算出する。
     *
     * @param  CarbonInterface|null  $clockIn  出勤時刻
     * @param  CarbonInterface|null  $clockOut  退勤時刻
     * @param  int  $breakSeconds  合計休憩秒数
     * @return string|null HH:MM形式の勤務時間。出退勤が未完了ならnull
     */
    private function totalTime(
        ?CarbonInterface $clockIn,
        ?CarbonInterface $clockOut,
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
     * 秒数を時間・分の表示文字列へ変換する。
     *
     * @param  int  $seconds  変換対象の秒数
     * @return string HH:MM形式の時間
     */
    private function formatSeconds(int $seconds): string
    {
        return gmdate('H:i', max(0, $seconds));
    }
}
