<?php

namespace Tests\Feature;

use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD13Attendance;
use Tests\TestCase;

class D13AdminAttendanceDetailTest extends TestCase
{
    use CreatesD13Attendance, RefreshDatabase;

    /**
     * @test
     */
    public function 正常系_勤怠詳細画面に選択した勤怠情報が正しく表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'テスト太郎',
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
            ->actingAs($admin)
            ->get(route('admin.attendance.show', $attendance->id));

        // Assert
        $response->assertOk();

        $response->assertSee('テスト太郎');
        $response->assertSee('2026年');
        $response->assertSee('10月01日');

        $response->assertSee('09:00');
        $response->assertSee('18:00');

        $response->assertSee('12:00');
        $response->assertSee('13:00');

        $response->assertSee('元の備考');
    }
}
