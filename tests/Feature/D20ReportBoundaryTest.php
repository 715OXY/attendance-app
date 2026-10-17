<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD20ReportTests;
use Tests\TestCase;

/**
 * レポートの集計対象と労働時間の境界値を確認する。
 */
class D20ReportBoundaryTest extends TestCase
{
    use RefreshDatabase, SupportsD20ReportTests;

    /**
     * 異常系：対象期間外と他ユーザーの勤怠は集計されない。
     */
    public function test_対象期間外と他ユーザーの勤怠は集計されない(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'admin_status' => false,
        ]);

        $otherUser = User::factory()->create([
            'email_verified_at' => now(),
            'admin_status' => false,
        ]);

        // 対象期間より前
        $this->createAttendance(
            $user,
            '2026-03-31',
            '08:00',
            '21:00'
        );

        // 当月は対象外
        $this->createAttendance(
            $user,
            '2026-10-01',
            '08:00',
            '21:00'
        );

        // 他ユーザー
        $this->createAttendance(
            $otherUser,
            '2026-09-01',
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
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);

        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
    }

    /**
     * 境界値：8時間ちょうどは残業ではなく、
     * 10時間ちょうどは長時間労働ではない。
     */
    public function test_残業時間と長時間労働の境界値を正しく判定できる(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'admin_status' => false,
        ]);

        // 実働8時間
        $this->createAttendance(
            $user,
            '2026-09-01',
            '09:00',
            '18:00'
        );

        // 実働10時間
        $this->createAttendance(
            $user,
            '2026-09-02',
            '08:00',
            '19:00'
        );

        // Act
        $response = $this
            ->actingAs($user)
            ->get('/attendance/report');

        // Assert
        $response->assertOk();

        $response->assertViewHas('summary', [
            'total_work_minutes' => 1080,
            'total_overtime_minutes' => 120,
            'avg_work_minutes' => 540,
        ]);

        $response->assertViewHas(
            'anomalies',
            function (array $anomalies): bool {
                return $anomalies['late_count'] === 0
                    && $anomalies['early_leave_count'] === 0
                    && $anomalies['long_work_count'] === 0;
            }
        );
    }
}
