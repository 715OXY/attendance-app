<?php

namespace Tests\Support;

use App\Models\Attendance;
use App\Models\User;

/**
 * D18の更新・削除テストで使用する勤怠を作成する。
 */
trait CreatesD18Attendance
{
    /**
     * 指定ユーザーの勤怠を作成する。
     *
     * @param  User  $user  勤怠の所有者
     * @param  string  $date  勤怠日
     * @param  string  $clockIn  出勤時刻
     * @param  string|null  $clockOut  退勤時刻
     * @param  string|null  $comment  備考
     * @return Attendance 作成した勤怠
     */
    protected function createAttendance(
        User $user,
        string $date = '2026-10-10',
        string $clockIn = '09:00:00',
        ?string $clockOut = '18:00:00',
        ?string $comment = '変更前'
    ): Attendance {
        return Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => "{$date} {$clockIn}",
            'clock_out' => $clockOut
                ? "{$date} {$clockOut}"
                : null,
            'comment' => $comment,
        ]);
    }
}
