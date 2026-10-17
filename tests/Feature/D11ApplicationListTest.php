<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD11Attendance;
use Tests\TestCase;

class D11ApplicationListTest extends TestCase
{
    use CreatesD11Attendance, RefreshDatabase;

    /**
     * @test
     */
    public function 正常系_承認待ち一覧に自分が行った申請が全て表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance1 = $this->createAttendance(
            $user,
            '2026-10-01'
        );

        $attendance2 = $this->createAttendance(
            $user,
            '2026-10-02'
        );

        $this->actingAs($user);

        // Act：1件目の申請
        $this->post(
            route('attendance.correction.store', $attendance1->id),
            [
                'new_date' => '10月01日',
                'new_clock_in' => '09:10',
                'new_clock_out' => '18:10',
                'new_break_in' => ['12:00'],
                'new_break_out' => ['13:00'],
                'comment' => '承認待ち申請1',
            ]
        );

        // Act：2件目の申請
        $this->post(
            route('attendance.correction.store', $attendance2->id),
            [
                'new_date' => '10月01日',
                'new_clock_in' => '09:20',
                'new_clock_out' => '18:20',
                'new_break_in' => ['12:10'],
                'new_break_out' => ['13:10'],
                'comment' => '承認待ち申請2',
            ]
        );

        // Act：申請一覧を表示
        $response = $this->get(
            route('attendance.application.list')
        );

        // Assert
        $response->assertOk();
        $response->assertSee('承認待ち');
        $response->assertSee('承認待ち申請1');
        $response->assertSee('承認待ち申請2');

        $this->assertDatabaseCount(
            'attendance_correction_requests',
            2
        );
    }

    /**
     * @test
     */
    public function 正常系_承認済み一覧に管理者が承認した申請が表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01',
            true
        );

        // Act：一般ユーザーが修正申請
        $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                [
                    'new_date' => '10月01日',
                    'new_clock_in' => '09:10',
                    'new_clock_out' => '18:15',
                    'new_break_in' => ['12:10'],
                    'new_break_out' => ['13:10'],
                    'comment' => '承認済み申請テスト',
                ]
            );

        $application = AttendanceCorrectionRequest::firstOrFail();

        // Act：管理者が承認
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

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $application->id,
            'status' => 1,
        ]);

        // Act：一般ユーザーで申請一覧を表示
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.application.list'));

        // Assert
        $response->assertOk();
        $response->assertSee('承認済');
        $response->assertSee('承認済み申請テスト');
    }
}
