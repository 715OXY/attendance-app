<?php

namespace Tests\Feature;

use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD13Attendance;
use Tests\TestCase;

/**
 * 管理者による勤怠修正の入力検証を確認する。
 */
class D13AdminAttendanceValidationTest extends TestCase
{
    use CreatesD13Attendance, RefreshDatabase;

    /**
     * @test
     */
    public function 異常系_出勤時間が退勤時間より後の場合はエラーになる(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validUpdateData();
        $data['new_clock_in'] = '19:00';
        $data['new_clock_out'] = '18:00';

        // Act
        $response = $this
            ->actingAs($admin)
            ->post('/attendance/'.$attendance->id, $data);

        // Assert
        $response->assertSessionHasErrors([
            'new_clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => '2026-10-01 18:00:00',
        ]);
    }

    /**
     * @test
     */
    public function 異常系_休憩開始時間が退勤時間より後の場合はエラーになる(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);

        $data = $this->validUpdateData();
        $data['new_break_in'] = ['18:30'];
        $data['new_break_out'] = ['18:45'];

        // Act
        $response = $this
            ->actingAs($admin)
            ->post('/attendance/'.$attendance->id, $data);

        // Assert
        $response->assertSessionHasErrors([
            'new_break_in.0' => '休憩時間が不適切な値です',
        ]);

        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);
    }

    /**
     * @test
     */
    public function 異常系_休憩終了時間が退勤時間より後の場合はエラーになる(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);

        $data = $this->validUpdateData();
        $data['new_break_in'] = ['17:30'];
        $data['new_break_out'] = ['18:30'];

        // Act
        $response = $this
            ->actingAs($admin)
            ->post('/attendance/'.$attendance->id, $data);

        // Assert
        $response->assertSessionHasErrors([
            'new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です',
        ]);

        $this->assertDatabaseHas('breaks', [
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);
    }

    /**
     * @test
     */
    public function 異常系_備考が未入力の場合はエラーになる(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        $data = $this->validUpdateData();
        $data['comment'] = '';

        // Act
        $response = $this
            ->actingAs($admin)
            ->post('/attendance/'.$attendance->id, $data);

        // Assert
        $response->assertSessionHasErrors([
            'comment' => '備考を記入してください',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'comment' => '元の備考',
        ]);
    }

    private function validUpdateData(): array
    {
        return [
            'new_date' => '10月01日',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['13:00'],
            'comment' => '管理者修正テスト',
        ];
    }
}
