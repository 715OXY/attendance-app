<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD07BreakTests;
use Tests\TestCase;

class D07AttendanceBreakTest extends TestCase
{
    use RefreshDatabase, SupportsD07BreakTests;

    /**
     * @test
     */
    public function 正常系_出勤中は休憩入ボタンが表示され休憩後は休憩中になる(): void
    {
        // Arrange
        $user = $this->createClockedInUser();

        $this->actingAs($user);

        // Act & Assert：休憩前
        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('出勤中');
        $response->assertSee('休憩入');

        // Act：休憩入
        $response = $this->post(route('attendance.store'), [
            'action' => 'break_in',
        ]);

        // Assert
        $response->assertRedirect(route('attendance.index'));

        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('休憩中');

        $this->assertDatabaseHas('breaks', [
            'break_in' => '2026-10-01 10:00:00',
            'break_out' => null,
        ]);
    }

    /**
     * @test
     */
    public function 正常系_休憩中は休憩戻ボタンが表示され休憩戻後は出勤中になる(): void
    {
        // Arrange
        $user = $this->createClockedInUser();

        $this->actingAs($user);

        $this->post(route('attendance.store'), [
            'action' => 'break_in',
        ]);

        // Act & Assert：休憩中
        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('休憩中');
        $response->assertSee('休憩戻');

        // Act：休憩戻
        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 10, 15, 0, 'Asia/Tokyo')
        );

        $response = $this->post(route('attendance.store'), [
            'action' => 'break_out',
        ]);

        // Assert
        $response->assertRedirect(route('attendance.index'));

        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('出勤中');

        $this->assertDatabaseHas('breaks', [
            'break_in' => '2026-10-01 10:00:00',
            'break_out' => '2026-10-01 10:15:00',
        ]);
    }

    /**
     * @test
     */
    public function 正常系_休憩時間が勤怠一覧画面に正しく表示される(): void
    {
        // Arrange
        $user = $this->createClockedInUser();

        $this->actingAs($user);

        // Act：12:00に休憩入
        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 12, 0, 0, 'Asia/Tokyo')
        );

        $this->post(route('attendance.store'), [
            'action' => 'break_in',
        ]);

        // Act：13:00に休憩戻
        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 13, 0, 0, 'Asia/Tokyo')
        );

        $this->post(route('attendance.store'), [
            'action' => 'break_out',
        ]);

        // Act：勤怠一覧を表示
        $response = $this->get(route('attendance.list'));

        // Assert
        $response->assertOk();
        $response->assertSee('1:00');

        $this->assertDatabaseHas('breaks', [
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);
    }
}
