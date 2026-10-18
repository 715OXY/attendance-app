<?php

namespace Tests\Support;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;

/**
 * 管理者の修正申請・承認テスト用のデータを準備する。
 */
trait SupportsD15ApprovalTests
{
    protected function createAttendance(
        User $user,
        string $date
    ): Attendance {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => "{$date} 09:00:00",
            'clock_out' => "{$date} 18:00:00",
            'comment' => '元の備考',
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => "{$date} 12:00:00",
            'break_out' => "{$date} 13:00:00",
        ]);

        return $attendance;
    }

    protected function createCorrectionRequest(
        User $user,
        Attendance $attendance,
        string $clockIn,
        string $clockOut,
        string $breakIn,
        string $breakOut,
        string $comment
    ): AttendanceCorrectionRequest {
        $date = Carbon::parse(
            $attendance->date
        )->format('m月d日');

        $response = $this
            ->actingAs($user)
            ->post(
                route(
                    'attendance.correction.store',
                    $attendance->id
                ),
                [
                    'new_date' => $date,
                    'new_clock_in' => $clockIn,
                    'new_clock_out' => $clockOut,
                    'new_break_in' => [$breakIn],
                    'new_break_out' => [$breakOut],
                    'comment' => $comment,
                ]
            );

        $response->assertSessionHasNoErrors();

        return AttendanceCorrectionRequest::where(
            'attendance_id',
            $attendance->id
        )
            ->latest('id')
            ->firstOrFail();
    }
}
