<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D20AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(
                2026,
                10,
                5,
                12,
                0,
                0,
                'Asia/Tokyo'
            )
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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
            function ($monthlyTrend): bool {
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
            fn ($monthlyTrend): bool => $monthlyTrend->count() === 6
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

    /**
     * テスト用の勤怠と1時間休憩を作成する。
     */
    private function createAttendance(
        User $user,
        string $date,
        string $clockIn,
        string $clockOut
    ): Attendance {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => Carbon::parse(
                "{$date} {$clockIn}",
                'Asia/Tokyo'
            ),
            'clock_out' => Carbon::parse(
                "{$date} {$clockOut}",
                'Asia/Tokyo'
            ),
            'comment' => null,
        ]);

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => Carbon::parse(
                "{$date} 12:00",
                'Asia/Tokyo'
            ),
            'break_out' => Carbon::parse(
                "{$date} 13:00",
                'Asia/Tokyo'
            ),
        ]);

        return $attendance;
    }
}
