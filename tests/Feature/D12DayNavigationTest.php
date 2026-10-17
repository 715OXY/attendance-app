<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD12AttendanceListTests;
use Tests\TestCase;

class D12DayNavigationTest extends TestCase
{
    use RefreshDatabase, SupportsD12AttendanceListTests;

    /**
     * @test
     */
    public function 正常系_前日を選択すると前日の勤怠情報が表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '前日テストユーザー',
            'admin_status' => false,
        ]);

        $this->createAttendance(
            $user,
            '2026-09-30',
            '08:31:00',
            '17:31:00'
        );

        $this->createAttendance(
            $user,
            '2026-10-01',
            '09:41:00',
            '18:41:00'
        );

        $this->actingAs($admin);

        // Act：現在日を表示
        $response = $this->get(
            route('admin.attendance.index')
        );

        // Assert：前日リンクが存在する
        $response->assertOk();
        $response->assertSee('前日');
        $response->assertSee('?date=2026-09-30', false);

        // Act：前日を表示
        $response = $this->get(
            route('admin.attendance.index', [
                'date' => '2026-09-30',
            ])
        );

        // Assert
        $response->assertOk();
        $response->assertSee('2026年09月30日');
        $response->assertSee('08:31');
        $response->assertSee('17:31');

        // 当日の勤怠時刻は表示されない
        $response->assertDontSee('09:41');
        $response->assertDontSee('18:41');
    }

    /**
     * @test
     */
    public function 正常系_翌日を選択すると翌日の勤怠情報が表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => '翌日テストユーザー',
            'admin_status' => false,
        ]);

        $this->createAttendance(
            $user,
            '2026-10-01',
            '09:42:00',
            '18:42:00'
        );

        $this->createAttendance(
            $user,
            '2026-10-02',
            '08:32:00',
            '17:32:00'
        );

        $this->actingAs($admin);

        // Act：現在日を表示
        $response = $this->get(
            route('admin.attendance.index')
        );

        // Assert：翌日リンクが存在する
        $response->assertOk();
        $response->assertSee('翌日');
        $response->assertSee('?date=2026-10-02', false);

        // Act：翌日を表示
        $response = $this->get(
            route('admin.attendance.index', [
                'date' => '2026-10-02',
            ])
        );

        // Assert
        $response->assertOk();
        $response->assertSee('2026年10月02日');
        $response->assertSee('08:32');
        $response->assertSee('17:32');

        // 当日の勤怠時刻は表示されない
        $response->assertDontSee('09:42');
        $response->assertDontSee('18:42');
    }
}
