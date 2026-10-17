<?php

namespace Tests\Support;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

/**
 * 休憩テストの日時固定と出勤済みユーザーの作成を提供する。
 */
trait SupportsD07BreakTests
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 10, 0, 0, 'Asia/Tokyo')
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function createClockedInUser(): User
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => null,
            'comment' => null,
        ]);

        return $user;
    }
}
