<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D07AttendanceBreakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 10, 0, 0, 'Asia/Tokyo')
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
