<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D12AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 12, 0, 0, 'Asia/Tokyo')
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

    private function createAttendance(
        User $user,
        string $date,
        string $clockIn,
        string $clockOut
    ): Attendance {
        return Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => "{$date} {$clockIn}",
            'clock_out' => "{$date} {$clockOut}",
            'comment' => null,
        ]);
    }
}
