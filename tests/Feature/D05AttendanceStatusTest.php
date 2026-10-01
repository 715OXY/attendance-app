<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D05AttendanceStatusTest extends TestCase
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
    public function 正常系_勤怠情報がない場合は勤務外と表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('勤務外');
    }

    /**
     * @test
     */
    public function 正常系_出勤後は出勤中と表示される(): void
    {
        // Arrange
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

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('出勤中');
    }

    /**
     * @test
     */
    public function 正常系_休憩開始後は休憩中と表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => null,
            'comment' => null,
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 10:00:00',
            'break_out' => null,
        ]);

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('休憩中');
    }

    /**
     * @test
     */
    public function 正常系_退勤後は退勤済と表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-01',
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => '2026-10-01 18:00:00',
            'comment' => null,
        ]);

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.index'));

        // Assert
        $response->assertOk();
        $response->assertSee('退勤済');
    }
}
