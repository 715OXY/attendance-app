<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD11Attendance;
use Tests\TestCase;

class D11UserAttendanceCorrectionTest extends TestCase
{
    use CreatesD11Attendance, RefreshDatabase;

    /**
     * @test
     */
    public function 正常系_修正申請が実行され管理者の申請一覧と承認画面に表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $admin = User::factory()->create([
            'name' => '管理者',
            'admin_status' => true,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01',
            true
        );

        $data = [
            'new_date' => '10月01日',
            'new_clock_in' => '09:10',
            'new_clock_out' => '18:15',
            'new_break_in' => ['12:10'],
            'new_break_out' => ['13:10'],
            'comment' => '修正申請テスト',
        ];

        // Act：一般ユーザーが修正申請
        $response = $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                $data
            );

        // Assert：申請が保存される
        $response->assertRedirect(
            route('attendance.show', $attendance->id)
        );

        $application = $this->assertStoredApplication($attendance, $user);

        // Act：管理者で申請一覧を表示
        $response = $this
            ->actingAs($admin)
            ->get(route('attendance.application.list'));

        // Assert
        $response->assertOk();
        $response->assertSee('テスト太郎');
        $response->assertSee('修正申請テスト');

        // Act：管理者で承認画面を表示
        $response = $this->get(
            route('admin.application.show', [
                'attendance_correct_request_id' => $application->id,
            ])
        );

        // Assert
        $response->assertOk();
        $response->assertSee('修正申請テスト');
        $response->assertSee('09:10');
        $response->assertSee('18:15');
    }

    /**
     * 申請と休憩修正が保存され、正式な勤怠が未変更であることを確認する。
     *
     * @param  Attendance  $attendance  修正対象の勤怠
     * @param  User  $user  申請ユーザー
     * @return AttendanceCorrectionRequest 保存された修正申請
     */
    private function assertStoredApplication(
        Attendance $attendance,
        User $user
    ): AttendanceCorrectionRequest {
        $this->assertDatabaseHas('attendance_correction_requests', [
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in' => '2026-10-01 09:10:00',
            'requested_clock_out' => '2026-10-01 18:15:00',
            'requested_comment' => '修正申請テスト',
            'status' => 0,
        ]);

        $application = AttendanceCorrectionRequest::firstOrFail();

        $this->assertDatabaseHas('attendance_correction_breaks', [
            'attendance_correction_request_id' => $application->id,
            'requested_break_in' => '2026-10-01 12:10:00',
            'requested_break_out' => '2026-10-01 13:10:00',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => '2026-10-01 18:00:00',
        ]);

        return $application;
    }
}
