<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Support\SupportsD20ReportTests;
use Tests\TestCase;

class D20AttendanceReportTest extends TestCase
{
    use RefreshDatabase, SupportsD20ReportTests;

    /**
     * 異常系：ゲストはレポートページにアクセスできない。
     */
    public function test_ゲストはレポートページにアクセスできない(): void
    {
        // Arrange

        // Act
        $response = $this->get('/attendance/report');

        // Assert
        $response->assertRedirect('/login');
    }

    /**
     * 正常系：認証ユーザーの統計情報が正しく計算される。
     */
    public function test_認証ユーザーの統計情報が正しく計算される(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'admin_status' => false,
        ]);

        $this->createAttendance(
            $user,
            '2026-09-01',
            '09:00',
            '18:00'
        );

        $this->createAttendance(
            $user,
            '2026-09-02',
            '09:00',
            '20:00'
        );

        $this->createAttendance(
            $user,
            '2026-09-03',
            '09:30',
            '18:00'
        );

        $this->createAttendance(
            $user,
            '2026-09-04',
            '09:00',
            '17:00'
        );

        $this->createAttendance(
            $user,
            '2026-09-07',
            '08:00',
            '21:00'
        );

        // Act
        $response = $this
            ->actingAs($user)
            ->get('/attendance/report');

        // Assert
        $response->assertOk();

        $response->assertViewHas('summary', [
            'total_work_minutes' => 2670,
            'total_overtime_minutes' => 360,
            'avg_work_minutes' => 534,
        ]);

        $response->assertViewHas(
            'monthlyTrend',
            function (Collection $monthlyTrend): bool {
                $september = $monthlyTrend->firstWhere(
                    'month',
                    '2026/09'
                );

                return $monthlyTrend->count() === 6
                    && $september['work_minutes'] === 2670
                    && $september['overtime_minutes'] === 360;
            }
        );

        $response->assertViewHas('anomalies', [
            'late_count' => 1,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);
    }

    /**
     * 境界値：勤怠記録がなくても安全に表示できる。
     */
    public function test_勤怠記録がないユーザーでも安全に表示できる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'admin_status' => false,
        ]);

        // Act
        $response = $this
            ->actingAs($user)
            ->get('/attendance/report');

        // Assert
        $response->assertOk();

        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);

        $response->assertViewHas(
            'monthlyTrend',
            fn (Collection $monthlyTrend): bool => $monthlyTrend->count() === 6
                && $monthlyTrend->every(
                    fn (array $row): bool => $row['work_minutes'] === 0
                        && $row['overtime_minutes'] === 0
                )
        );

        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
    }
}
