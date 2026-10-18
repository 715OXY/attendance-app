<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionBreak;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD17Attendance;
use Tests\TestCase;

class D17PendingCorrectionTest extends TestCase
{
    use CreatesD17Attendance, RefreshDatabase;

    public function test_承認待ちの修正申請がある場合は申請後の勤怠情報を返す(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance(
            $user,
            '2026-09-10',
            '09:00:00',
            '18:00:00'
        );

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-09-10 12:00:00',
            'break_out' => '2026-09-10 13:00:00',
        ]);

        $this->createPendingApplication($attendance, $user);

        // Act
        $response = $this->getJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath(
                'data.clock_in',
                '10:00:00'
            )
            ->assertJsonPath(
                'data.clock_out',
                '19:00:00'
            )
            ->assertJsonPath(
                'data.total_break_time',
                '01:00'
            )
            ->assertJsonPath(
                'data.total_time',
                '08:00'
            )
            ->assertJsonPath(
                'data.comment',
                '勤務時間修正'
            )
            ->assertJsonPath(
                'data.breaks.0.break_in',
                '13:00:00'
            )
            ->assertJsonPath(
                'data.breaks.0.break_out',
                '14:00:00'
            )
            ->assertJsonPath(
                'data.applications.0.requested_clock_in',
                '10:00:00'
            )
            ->assertJsonPath(
                'data.applications.0.requested_clock_out',
                '19:00:00'
            )
            ->assertJsonPath(
                'data.applications.0.status',
                0
            );
    }

    /**
     * 休憩修正を含む承認待ち申請を作成する。
     *
     * @param  Attendance  $attendance  修正対象の勤怠
     * @param  User  $user  申請ユーザー
     */
    private function createPendingApplication(
        Attendance $attendance,
        User $user
    ): void {
        $application = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in' => '2026-09-10 10:00:00',
            'requested_clock_out' => '2026-09-10 19:00:00',
            'requested_comment' => '勤務時間修正',
            'status' => 0,
        ]);

        AttendanceCorrectionBreak::create([
            'attendance_correction_request_id' => $application->id,
            'requested_break_in' => '2026-09-10 13:00:00',
            'requested_break_out' => '2026-09-10 14:00:00',
        ]);
    }
}
