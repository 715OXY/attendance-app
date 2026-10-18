<?php

namespace Tests\Support;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;

/**
 * レポートテストの日時固定と勤怠データ作成を提供する。
 */
trait SupportsD20ReportTests
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(
                2026,
                10,
                5,
                12,
                0,
                0,
                'Asia/Tokyo'
            )
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * テスト用の勤怠と1時間休憩を作成する。
     */
    protected function createAttendance(
        User $user,
        string $date,
        string $clockIn,
        string $clockOut
    ): Attendance {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => Carbon::parse(
                "{$date} {$clockIn}",
                'Asia/Tokyo'
            ),
            'clock_out' => Carbon::parse(
                "{$date} {$clockOut}",
                'Asia/Tokyo'
            ),
            'comment' => null,
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => Carbon::parse(
                "{$date} 12:00",
                'Asia/Tokyo'
            ),
            'break_out' => Carbon::parse(
                "{$date} 13:00",
                'Asia/Tokyo'
            ),
        ]);

        return $attendance;
    }
}
