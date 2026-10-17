<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD07BreakTests;
use Tests\TestCase;

/**
 * 同じ日に休憩開始・終了を繰り返せることを確認する。
 */
class D07RepeatedBreakTest extends TestCase
{
    use RefreshDatabase, SupportsD07BreakTests;

    /**
     * @test
     */
    public function 正常系_休憩終了後は同じ日に再度休憩できる(): void
    {
        // Arrange
        $user = $this->createClockedInUser();

        $this->actingAs($user);

        // Act：1回目の休憩
        $this->post(route('attendance.store'), [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 10, 15, 0, 'Asia/Tokyo')
        );

        $this->post(route('attendance.store'), [
            'action' => 'break_out',
        ]);

        // Assert：休憩終了後、再び休憩入できる
        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('出勤中');
        $response->assertSee('休憩入');

        // Act：2回目の休憩
        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 11, 0, 0, 'Asia/Tokyo')
        );

        $response = $this->post(route('attendance.store'), [
            'action' => 'break_in',
        ]);

        // Assert
        $response->assertRedirect(route('attendance.index'));

        $this->assertDatabaseCount('breaks', 2);

        $this->assertDatabaseHas('breaks', [
            'break_in' => '2026-10-01 11:00:00',
            'break_out' => null,
        ]);
    }

    /**
     * @test
     */
    public function 正常系_同じ日に複数回休憩戻できる(): void
    {
        // Arrange
        $user = $this->createClockedInUser();

        $this->actingAs($user);

        // Act：1回目の休憩入
        $this->post(route('attendance.store'), [
            'action' => 'break_in',
        ]);

        // 1回目の休憩戻
        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 10, 15, 0, 'Asia/Tokyo')
        );

        $this->post(route('attendance.store'), [
            'action' => 'break_out',
        ]);

        // 2回目の休憩入
        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 11, 0, 0, 'Asia/Tokyo')
        );

        $this->post(route('attendance.store'), [
            'action' => 'break_in',
        ]);

        // Assert：2回目も休憩戻できる状態
        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('休憩中');
        $response->assertSee('休憩戻');

        // Act：2回目の休憩戻
        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 11, 15, 0, 'Asia/Tokyo')
        );

        $response = $this->post(route('attendance.store'), [
            'action' => 'break_out',
        ]);

        // Assert
        $response->assertRedirect(route('attendance.index'));

        $this->assertDatabaseCount('breaks', 2);

        $this->assertDatabaseHas('breaks', [
            'break_in' => '2026-10-01 11:00:00',
            'break_out' => '2026-10-01 11:15:00',
        ]);

        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('出勤中');
    }
}
