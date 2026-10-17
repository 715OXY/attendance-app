<?php

namespace Tests\Feature;

use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD14StaffTests;
use Tests\TestCase;

class D14StaffAttendanceTest extends TestCase
{
    use RefreshDatabase, SupportsD14StaffTests;

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
}
