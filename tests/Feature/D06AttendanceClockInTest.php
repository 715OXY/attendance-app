<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D06AttendanceClockInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 9, 0, 0, 'Asia/Tokyo')
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
    public function 正常系_勤務外では出勤ボタンが表示され出勤後は出勤中になる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $this->actingAs($user);

        // Act & Assert：出勤前
        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('勤務外');
        $response->assertSee('出勤');

        // Act：出勤
        $response = $this->post(route('attendance.store'), [
            'action' => 'clock_in',
        ]);

        // Assert
        $response->assertRedirect(route('attendance.index'));

        $response = $this->get(route('attendance.index'));

        $response->assertOk();
        $response->assertSee('出勤中');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => null,
        ]);
    }

    /**
     * @test
     */
    public function 正常系_退勤済みの場合は同じ日に再度出勤できない(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 07:00:00',
            'clock_out' => '2026-10-01 08:00:00',
            'comment' => null,
        ]);

        $this->actingAs($user);

        // Act
        $response = $this->get(route('attendance.index'));

        // Assert：退勤済みでは出勤ボタンが表示されない
        $response->assertOk();
        $response->assertSee('退勤済');
        $response->assertDontSee('>出勤<', false);

        // Act：直接POSTしても再出勤できないことを確認
        $response = $this->post(route('attendance.store'), [
            'action' => 'clock_in',
        ]);

        // Assert
        $response->assertRedirect(route('attendance.index'));

        $this->assertDatabaseCount('attendances', 1);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 07:00:00',
            'clock_out' => '2026-10-01 08:00:00',
        ]);
    }

    /**
     * @test
     */
    public function 正常系_出勤時刻が勤怠一覧画面に正しく表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $this->actingAs($user);

        // Act：09:00に出勤
        $response = $this->post(route('attendance.store'), [
            'action' => 'clock_in',
        ]);

        $response->assertRedirect(route('attendance.index'));

        // 勤怠一覧画面を表示
        $response = $this->get(route('attendance.list'));

        // Assert
        $response->assertOk();
        $response->assertSee('09:00');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 09:00:00',
        ]);
    }
}
