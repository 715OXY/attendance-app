<?php

namespace Tests\Feature;

use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD12AttendanceListTests;
use Tests\TestCase;

class D12AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase, SupportsD12AttendanceListTests;

    /**
     * @test
     */
    public function 正常系_その日の全ユーザーの勤怠情報が正確に表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user1 = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => 'テスト花子',
            'admin_status' => false,
        ]);

        $attendance1 = $this->createAttendance(
            $user1,
            '2026-10-01',
            '09:00:00',
            '18:00:00'
        );

        BreakTime::create([
            'attendance_id' => $attendance1->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);

        $this->createAttendance(
            $user2,
            '2026-10-01',
            '08:15:00',
            '17:45:00'
        );

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(route('admin.attendance.index'));

        // Assert
        $response->assertOk();

        // ユーザー1
        $response->assertSee('テスト太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');

        // ユーザー2
        $response->assertSee('テスト花子');
        $response->assertSee('08:15');
        $response->assertSee('17:45');
        $response->assertSee('9:30');
    }

    /**
     * @test
     */
    public function 正常系_勤怠一覧画面には現在の日付が表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(route('admin.attendance.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('2026年10月01日');
    }
}
