<?php

namespace Tests\Support;

use App\Models\Attendance;
use App\Models\User;

/**
 * 管理者の勤怠詳細テスト用の勤怠を作成する。
 */
trait CreatesD13Attendance
{
    protected function createAttendance(User $user): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => '2026-10-01 18:00:00',
            'comment' => '元の備考',
        ]);
    }
}
