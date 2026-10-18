<?php

namespace Tests\Feature;

use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD14StaffTests;
use Tests\TestCase;

class D14StaffCsvTest extends TestCase
{
    use RefreshDatabase, SupportsD14StaffTests;

    /**
     * @test
     */
    public function 正常系_選択したユーザーと月の勤怠一覧を_csvでダウンロードできる(): void
    {
        // Arrange
        [$admin, $user] = $this->prepareCsvData();

        // Act
        $response = $this
            ->actingAs($admin)
            ->post(
                route('admin.staff.attendance.export'),
                [
                    'user_id' => $user->id,
                    'year_month' => '2026-09',
                ]
            );

        // Assert
        $response->assertOk();

        $this->assertStringContainsString(
            "attendance_{$user->id}_2026-09.csv",
            $response->headers->get('content-disposition')
        );

        $csv = $response->streamedContent();

        // UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        // ヘッダー
        $this->assertStringContainsString(
            '日付,出勤,退勤,休憩,合計',
            $csv
        );

        // 対象ユーザー・対象月の勤怠
        $this->assertStringContainsString(
            '09/01(火),09:03,18:07,1:00,8:04',
            $csv
        );

        // 勤怠がない日は空欄
        $this->assertStringContainsString(
            '09/02(水),,,,',
            $csv
        );

        // 対象月以外を含めない
        $this->assertStringNotContainsString(
            '10:00,19:00',
            $csv
        );

        // 他ユーザーを含めない
        $this->assertStringNotContainsString(
            '06:30,15:30',
            $csv
        );
    }

    /**
     * @test
     */
    public function 異常系_一般ユーザーは_csvを出力できない(): void
    {
        // Arrange
        [$admin, $user] = $this->prepareCsvData();

        // Act
        $response = $this
            ->actingAs($user)
            ->post(
                route('admin.staff.attendance.export'),
                [
                    'user_id' => $user->id,
                    'year_month' => '2026-09',
                ]
            );

        // Assert
        $response->assertForbidden();
    }

    /**
     * CSV出力対象と、出力対象外の勤怠を用意する。
     *
     * @return array{0: User, 1: User} 管理者と出力対象ユーザー
     */
    private function prepareCsvData(): array
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $otherUser = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-09-01',
            '09:03:00',
            '18:07:00'
        );

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-09-01 12:00:00',
            'break_out' => '2026-09-01 13:00:00',
        ]);

        $this->createAttendance(
            $user,
            '2026-10-01',
            '10:00:00',
            '19:00:00'
        );

        $this->createAttendance(
            $otherUser,
            '2026-09-01',
            '06:30:00',
            '15:30:00'
        );

        return [$admin, $user];
    }
}
