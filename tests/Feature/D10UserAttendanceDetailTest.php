<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D10UserAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function 正常系_勤怠詳細画面の名前がログインユーザーの氏名になっている(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.show', $attendance->id));

        // Assert
        $response->assertOk();
        $response->assertSee('テスト太郎');
    }

    /**
     * @test
     */
    public function 正常系_勤怠詳細画面の日付が選択した日付になっている(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.show', $attendance->id));

        // Assert
        $response->assertOk();
        $response->assertSee('2026年');
        $response->assertSee('10月01日');
    }

    /**
     * @test
     */
    public function 正常系_出勤退勤時刻がログインユーザーの打刻と一致している(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.show', $attendance->id));

        // Assert
        $response->assertOk();
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    /**
     * @test
     */
    public function 正常系_休憩時刻がログインユーザーの打刻と一致している(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance($user);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.show', $attendance->id));

        // Assert
        $response->assertOk();
        $response->assertSee('12:00');
        $response->assertSee('13:00');
    }

    private function createAttendance(User $user): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => '2026-10-01 18:00:00',
            'comment' => '詳細表示テスト',
        ]);
    }
}
