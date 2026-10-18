<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD15ApprovalTests;
use Tests\TestCase;

class D15AdminCorrectionApprovalTest extends TestCase
{
    use RefreshDatabase, SupportsD15ApprovalTests;

    /**
     * @test
     */
    public function 正常系_修正申請を承認すると勤怠情報に申請内容が反映される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01'
        );

        $application = $this->createCorrectionRequest(
            $user,
            $attendance,
            '09:10',
            '18:15',
            '12:10',
            '13:10',
            '承認後の備考'
        );

        // 承認前は元の勤怠情報のまま
        $this->assertOriginalAttendance($attendance);

        // Act
        $response = $this
            ->actingAs($admin)
            ->post(
                route('admin.application.approve', [
                    'attendance_correct_request_id' => $application->id,
                ])
            );

        // Assert
        $response->assertRedirect(
            route('admin.application.show', [
                'attendance_correct_request_id' => $application->id,
            ])
        );

        // 申請が承認済みになる
        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'id' => $application->id,
                'status' => 1,
            ]
        );

        // 出勤・退勤・備考が修正内容に更新される
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '2026-10-01 09:10:00',
            'clock_out' => '2026-10-01 18:15:00',
            'comment' => '承認後の備考',
        ]);

        // 休憩時間も修正内容に更新される
        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:10:00',
            'break_out' => '2026-10-01 13:10:00',
        ]);

        // 元の休憩時間は残っていない
        $this->assertDatabaseMissing('breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);
    }

    /**
     * 承認前の正式な勤怠と休憩が元の値であることを確認する。
     *
     * @param  Attendance  $attendance  承認対象の勤怠
     */
    private function assertOriginalAttendance(Attendance $attendance): void
    {
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => '2026-10-01 18:00:00',
            'comment' => '元の備考',
        ]);

        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);
    }
}
