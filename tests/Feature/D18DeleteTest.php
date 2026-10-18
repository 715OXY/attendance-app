<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionBreak;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD18Attendance;
use Tests\TestCase;

/**
 * 勤怠APIの削除と関連データの削除を確認する。
 */
class D18DeleteTest extends TestCase
{
    use CreatesD18Attendance, RefreshDatabase;

    public function test_勤怠を削除できる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        // Act
        $response = $this
            ->actingAs($user)
            ->deleteJson(
                "/api/v1/attendance-records/{$attendance->id}"
            );

        // Assert
        $response->assertNoContent();

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
        ]);
    }

    public function test_存在しない勤怠を削除すると404になる(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this
            ->actingAs($user)
            ->deleteJson(
                '/api/v1/attendance-records/99999'
            );

        // Assert
        $response
            ->assertNotFound()
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    public function test_勤怠削除で休憩と修正申請と修正休憩も削除される(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);
        $break = BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-10 12:00:00',
            'break_out' => '2026-10-10 13:00:00',
        ]);
        $application = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in' => '2026-10-10 10:00:00',
            'requested_clock_out' => '2026-10-10 19:00:00',
            'requested_comment' => '修正申請',
            'status' => 0,
        ]);
        $correctionBreak = AttendanceCorrectionBreak::create([
            'attendance_correction_request_id' => $application->id,
            'requested_break_in' => '2026-10-10 13:00:00',
            'requested_break_out' => '2026-10-10 14:00:00',
        ]);

        $this->actingAs($user)->deleteJson("/api/v1/attendance-records/{$attendance->id}")->assertNoContent();
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
        $this->assertDatabaseMissing('breaks', ['id' => $break->id]);
        $this->assertDatabaseMissing('attendance_correction_requests', ['id' => $application->id]);
        $this->assertDatabaseMissing('attendance_correction_breaks', ['id' => $correctionBreak->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
