<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD09AttendanceListTests;
use Tests\TestCase;

class D09MonthNavigationTest extends TestCase
{
    use RefreshDatabase, SupportsD09AttendanceListTests;

    /**
     * @test
     */
    public function 正常系_前月を選択すると前月の勤怠情報が表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $this->createAttendance(
            $user,
            '2026-09-10',
            '08:31:00',
            '17:31:00'
        );

        $this->createAttendance(
            $user,
            '2026-10-10',
            '09:41:00',
            '18:41:00'
        );

        $this->actingAs($user);

        // Act：現在月を表示
        $response = $this->get(route('attendance.list'));

        // Assert：前月へのリンクが表示されている
        $response->assertOk();
        $response->assertSee('前月');
        $response->assertSee('?date=2026-09', false);

        // Act：前月を選択
        $response = $this->get(
            route('attendance.list', ['date' => '2026-09'])
        );

        // Assert
        $response->assertOk();
        $response->assertSee('2026/09');
        $response->assertSee('08:31');
        $response->assertSee('17:31');

        // 当月の勤怠は表示されない
        $response->assertDontSee('09:41');
        $response->assertDontSee('18:41');
    }

    /**
     * @test
     */
    public function 正常系_翌月を選択すると翌月の勤怠情報が表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $this->createAttendance(
            $user,
            '2026-10-10',
            '09:42:00',
            '18:42:00'
        );

        $this->createAttendance(
            $user,
            '2026-11-10',
            '08:32:00',
            '17:32:00'
        );

        $this->actingAs($user);

        // Act：現在月を表示
        $response = $this->get(route('attendance.list'));

        // Assert：翌月へのリンクが表示されている
        $response->assertOk();
        $response->assertSee('翌月');
        $response->assertSee('?date=2026-11', false);

        // Act：翌月を選択
        $response = $this->get(
            route('attendance.list', ['date' => '2026-11'])
        );

        // Assert
        $response->assertOk();
        $response->assertSee('2026/11');
        $response->assertSee('08:32');
        $response->assertSee('17:32');

        // 当月の勤怠は表示されない
        $response->assertDontSee('09:42');
        $response->assertDontSee('18:42');
    }
}
