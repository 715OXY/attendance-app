<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD09AttendanceListTests;
use Tests\TestCase;

class D09UserAttendanceListTest extends TestCase
{
    use RefreshDatabase, SupportsD09AttendanceListTests;

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
}
