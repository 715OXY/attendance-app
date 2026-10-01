<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D09UserAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 10, 15, 12, 0, 0, 'Asia/Tokyo')
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
    public function 正常系_自分の勤怠情報が全て表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $otherUser = User::factory()->create([
            'admin_status' => false,
        ]);

        $this->createAttendance(
            $user,
            '2026-10-01',
            '09:01:00',
            '18:01:00'
        );

        $this->createAttendance(
            $user,
            '2026-10-02',
            '09:02:00',
            '18:02:00'
        );

        $this->createAttendance(
            $user,
            '2026-10-03',
            '09:03:00',
            '18:03:00'
        );

        // 他ユーザーの勤怠
        $this->createAttendance(
            $otherUser,
            '2026-10-01',
            '06:30:00',
            '15:30:00'
        );

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.list'));

        // Assert
        $response->assertOk();

        $response->assertSee('09:01');
        $response->assertSee('18:01');

        $response->assertSee('09:02');
        $response->assertSee('18:02');

        $response->assertSee('09:03');
        $response->assertSee('18:03');

        // 他ユーザーの勤怠は表示されない
        $response->assertDontSee('06:30');
        $response->assertDontSee('15:30');
    }

    /**
     * @test
     */
    public function 正常系_勤怠一覧画面には現在の月が表示される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.list'));

        // Assert
        $response->assertOk();
        $response->assertSee('2026/10');
    }

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

    /**
     * @test
     */
    public function 正常系_詳細から選択した日の勤怠詳細画面に遷移できる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01',
            '09:00:00',
            '18:00:00'
        );

        $this->actingAs($user);

        // Act：勤怠一覧画面を表示
        $response = $this->get(route('attendance.list'));

        // Assert：提供Bladeの詳細リンクが存在する
        $response->assertOk();
        $response->assertSee('詳細');
        $response->assertSee(
            url('/attendance/'.$attendance->id),
            false
        );

        // Act：提供Bladeのリンク先へアクセス
        $response = $this->get(
            route('attendance.detail.redirect', $attendance->id)
        );

        // Assert：正式な勤怠詳細URLへリダイレクトされる
        $response->assertRedirect(
            route('attendance.show', $attendance->id)
        );

        // Act：正式な勤怠詳細画面を表示
        $response = $this->get(
            route('attendance.show', $attendance->id)
        );

        // Assert
        $response->assertOk();
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
