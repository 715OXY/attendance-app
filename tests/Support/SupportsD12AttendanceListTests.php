<?php

namespace Tests\Support;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

/**
 * 管理者の勤怠一覧テストの準備処理を提供する。
 */
trait SupportsD12AttendanceListTests
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 12, 0, 0, 'Asia/Tokyo')
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function createAttendance(
        User $user,
        string $date,
        string $clockIn,
        string $clockOut
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
