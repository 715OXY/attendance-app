<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D08AttendanceClockOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 18, 0, 0, 'Asia/Tokyo')
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @test
     */
    public function 正常系_出勤中は退勤ボタンが表示され退勤後は退勤済になる(): void
    {
        // Arrange
        $user = $this->createClockedInUser();

        $this->actingAs($user);

        // Act & Assert：退勤前
        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('出勤中');
        $response->assertSee('退勤');

        // Act：退勤
        $response = $this->post(route('attendance.store'), [
            'action' => 'clock_out',
        ]);

        // Assert
        $response->assertRedirect(route('attendance.index'));

        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('退勤済');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => '2026-10-01 18:00:00',
        ]);
    }

    /**
     * @test
     */
    public function 正常系_退勤時刻が勤怠一覧画面に正しく表示される(): void
    {
        // Arrange
        $user = $this->createClockedInUser();

        $this->actingAs($user);

        // Act：18:00に退勤
        $response = $this->post(route('attendance.store'), [
            'action' => 'clock_out',
        ]);

        $response->assertRedirect(route('attendance.index'));

        // Act：勤怠一覧画面を表示
        $response = $this->get(route('attendance.list'));

        // Assert
        $response->assertOk();
        $response->assertSee('18:00');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_out' => '2026-10-01 18:00:00',
        ]);
    }

    private function createClockedInUser(): User
    {
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => null,
            'comment' => null,
        ]);

        return $user;
    }
}
