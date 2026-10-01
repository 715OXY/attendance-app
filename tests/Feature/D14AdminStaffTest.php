<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D14AdminStaffTest extends TestCase
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
    public function 正常系_スタッフ一覧に全一般ユーザーの氏名とメールアドレスが表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'name' => '管理者ユーザー',
            'email' => 'admin@example.com',
            'admin_status' => true,
        ]);

        User::factory()->create([
            'name' => 'テスト太郎',
            'email' => 'taro@example.com',
            'admin_status' => false,
        ]);

        User::factory()->create([
            'name' => 'テスト花子',
            'email' => 'hanako@example.com',
            'admin_status' => false,
        ]);

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(route('admin.staff.index'));

        // Assert
        $response->assertOk();

        $response->assertSee('テスト太郎');
        $response->assertSee('taro@example.com');

        $response->assertSee('テスト花子');
        $response->assertSee('hanako@example.com');

        // 管理者自身はスタッフ一覧に含めない
        $response->assertDontSee('管理者ユーザー');
        $response->assertDontSee('admin@example.com');
    }

    /**
     * @test
     */
    public function 正常系_選択したユーザーの勤怠情報が正確に表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $otherUser = User::factory()->create([
            'name' => '別ユーザー',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01',
            '09:00:00',
            '18:00:00'
        );

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-01 12:00:00',
            'break_out' => '2026-10-01 13:00:00',
        ]);

        // 他ユーザーの勤怠
        $this->createAttendance(
            $otherUser,
            '2026-10-01',
            '06:30:00',
            '15:30:00'
        );

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(
                route('admin.staff.attendance.index', $user->id)
            );

        // Assert
        $response->assertOk();

        $response->assertSee('テスト太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('1:00');
        $response->assertSee('8:00');

        // 他ユーザーの勤怠は表示されない
        $response->assertDontSee('06:30');
        $response->assertDontSee('15:30');
    }

    /**
     * @test
     */
    public function 正常系_前月を選択すると前月の勤怠情報が表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

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

        $this->actingAs($admin);

        // Act：現在月を表示
        $response = $this->get(
            route('admin.staff.attendance.index', $user->id)
        );

        // Assert：前月リンクが存在する
        $response->assertOk();
        $response->assertSee('前月');
        $response->assertSee('?date=2026-09', false);

        // Act：前月を表示
        $response = $this->get(
            route(
                'admin.staff.attendance.index',
                [
                    'id' => $user->id,
                    'date' => '2026-09',
                ]
            )
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
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

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

        $this->actingAs($admin);

        // Act：現在月を表示
        $response = $this->get(
            route('admin.staff.attendance.index', $user->id)
        );

        // Assert：翌月リンクが存在する
        $response->assertOk();
        $response->assertSee('翌月');
        $response->assertSee('?date=2026-11', false);

        // Act：翌月を表示
        $response = $this->get(
            route(
                'admin.staff.attendance.index',
                [
                    'id' => $user->id,
                    'date' => '2026-11',
                ]
            )
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
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01',
            '09:00:00',
            '18:00:00'
        );

        $this->actingAs($admin);

        // Act：スタッフ別勤怠一覧を表示
        $response = $this->get(
            route('admin.staff.attendance.index', $user->id)
        );

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

        // Assert：管理者用の正式な勤怠詳細画面へリダイレクト
        $response->assertRedirect(
            route('admin.attendance.show', $attendance->id)
        );

        // Act：管理者用勤怠詳細画面を表示
        $response = $this->get(
            route('admin.attendance.show', $attendance->id)
        );

        // Assert
        $response->assertOk();
        $response->assertSee('テスト太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
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
