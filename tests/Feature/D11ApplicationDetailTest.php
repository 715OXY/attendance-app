<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD11Attendance;
use Tests\TestCase;

class D11ApplicationDetailTest extends TestCase
{
    use CreatesD11Attendance, RefreshDatabase;

    /**
     * @test
     */
    public function 正常系_申請一覧の詳細から対象の勤怠詳細画面に遷移できる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01'
        );

        $this->actingAs($user);

        $this->post(
            route('attendance.correction.store', $attendance->id),
            [
                'new_date' => '10月01日',
                'new_clock_in' => '09:10',
                'new_clock_out' => '18:10',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '詳細遷移テスト',
            ]
        );

        $application = AttendanceCorrectionRequest::firstOrFail();

        // Act：申請一覧を表示
        $response = $this->get(
            route('attendance.application.list')
        );

        // Assert：提供Bladeの詳細リンクを確認
        $response->assertOk();
        $response->assertSee('詳細');
        $response->assertSee(
            url('/application/'.$application->id),
            false
        );

        // Act：提供Bladeのリンク先へアクセス
        $response = $this->get(
            route('attendance.application.detail', $application->id)
        );

        // Assert：正式な勤怠詳細画面へリダイレクト
        $response->assertRedirect(
            route('attendance.show', $attendance->id)
        );

        // Act：勤怠詳細画面を表示
        $response = $this->get(
            route('attendance.show', $attendance->id)
        );

        // Assert
        $response->assertOk();
    }
}
