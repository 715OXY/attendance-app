<?php

namespace Tests\Support;

use App\Models\Attendance;
use App\Models\User;

/**
 * D17の読み取りAPIテスト用の勤怠を作成する。
 */
trait CreatesD17Attendance
{
    /**
     * テスト用の勤怠データを作成する。
     */
    protected function createAttendance(
        User $user,
        string $date,
        string $clockIn = '09:00:00',
        string $clockOut = '18:00:00'
    ): Attendance {
        return Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => "{$date} {$clockIn}",
            'clock_out' => "{$date} {$clockOut}",
            'comment' => null,
        ]);
    }
}
