<?php

namespace Tests\Support;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;

/**
 * D11の修正申請テスト用の勤怠を作成する。
 */
trait CreatesD11Attendance
{
    protected function createAttendance(
        User $user,
        string $date,
        bool $withBreak = false
    ): Attendance {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => "{$date} 09:00:00",
            'clock_out' => "{$date} 18:00:00",
            'comment' => '元の備考',
        ]);

        if ($withBreak) {
            BreakTime::create([
                'attendance_id' => $attendance->id,
                'break_in' => "{$date} 12:00:00",
                'break_out' => "{$date} 13:00:00",
            ]);
        }

        return $attendance;
    }
}
