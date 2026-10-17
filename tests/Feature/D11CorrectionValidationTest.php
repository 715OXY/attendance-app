<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD11Attendance;
use Tests\TestCase;

class D11CorrectionValidationTest extends TestCase
{
    use CreatesD11Attendance, RefreshDatabase;

    /**
     * @test
     */
    public function 異常系_出勤時間が退勤時間より後の場合はエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user, '2026-10-01');

        $data = [
            'new_date' => '10月01日',
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '修正申請テスト',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                $data
            );

        // Assert
        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);

        $this->assertDatabaseCount('attendance_correction_requests', 0);
    }

    /**
     * @test
     */
    public function 異常系_休憩開始時間が退勤時間より後の場合はエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user, '2026-10-01');

        $data = [
            'new_date' => '10月01日',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['18:30'],
            'new_break_out' => ['18:45'],
            'comment' => '修正申請テスト',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                $data
            );

        // Assert
        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);

        $this->assertDatabaseCount('attendance_correction_requests', 0);
    }

    /**
     * @test
     */
    public function 異常系_休憩終了時間が退勤時間より後の場合はエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user, '2026-10-01');

        $data = [
            'new_date' => '10月01日',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['17:30'],
            'new_break_out' => ['18:30'],
            'comment' => '修正申請テスト',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                $data
            );

        // Assert
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);

        $this->assertDatabaseCount('attendance_correction_requests', 0);
    }

    /**
     * @test
     */
    public function 異常系_備考が未入力の場合はエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user, '2026-10-01');

        $data = [
            'new_date' => '10月01日',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->post(
                route('attendance.correction.store', $attendance->id),
                $data
            );

        // Assert
        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);

        $this->assertDatabaseCount('attendance_correction_requests', 0);
    }
}
