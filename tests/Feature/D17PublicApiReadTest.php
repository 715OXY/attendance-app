<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionBreak;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D17PublicApiReadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テスト用の勤怠データを作成する。
     */
    private function createAttendance(
        User $user,
        string $date,
        string $clockIn = '09:00:00',
        string $clockOut = '18:00:00'
    ): Attendance {
        return Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => "{$date} {$clockIn}",
            'clock_out' => "{$date} {$clockOut}",
            'comment' => null,
        ]);
    }

    public function test_勤怠一覧を取得できる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance(
            $user,
            '2026-09-10'
        );

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-09-10 12:00:00',
            'break_out' => '2026-09-10 13:00:00',
        ]);

        // Act
        $response = $this->getJson(
            '/api/v1/attendance-records'
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'user_name',
                        'user' => [
                            'id',
                            'name',
                        ],
                        'date',
                        'clock_in',
                        'clock_out',
                        'total_time',
                        'total_break_time',
                        'comment',
                        'breaks',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonPath(
                'data.0.id',
                $attendance->id
            )
            ->assertJsonPath(
                'data.0.date',
                '2026-09-10'
            )
            ->assertJsonPath(
                'data.0.total_time',
                '08:00'
            )
            ->assertJsonPath(
                'data.0.total_break_time',
                '01:00'
            )
            ->assertJsonPath(
                'data.0.breaks.0.break_in',
                '12:00:00'
            );
    }

    public function test_user_idで勤怠を絞り込める(): void
    {
        // Arrange
        $targetUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $targetAttendance = $this->createAttendance(
            $targetUser,
            '2026-09-10'
        );

        $this->createAttendance(
            $otherUser,
            '2026-09-11'
        );

        // Act
        $response = $this->getJson(
            "/api/v1/attendance-records?user_id={$targetUser->id}"
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $targetAttendance->id
            )
            ->assertJsonPath(
                'data.0.user_id',
                $targetUser->id
            );
    }

    public function test_dateで勤怠を絞り込める(): void
    {
        // Arrange
        $user = User::factory()->create();

        $targetAttendance = $this->createAttendance(
            $user,
            '2026-09-10'
        );

        $this->createAttendance(
            $user,
            '2026-09-11'
        );

        // Act
        $response = $this->getJson(
            '/api/v1/attendance-records?date=2026-09-10'
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $targetAttendance->id
            )
            ->assertJsonPath(
                'data.0.date',
                '2026-09-10'
            );
    }

    public function test_monthで勤怠を絞り込める(): void
    {
        // Arrange
        $user = User::factory()->create();

        $septemberAttendance = $this->createAttendance(
            $user,
            '2026-09-10'
        );

        $this->createAttendance(
            $user,
            '2026-10-10'
        );

        // Act
        $response = $this->getJson(
            '/api/v1/attendance-records?month=2026-09'
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $septemberAttendance->id
            )
            ->assertJsonPath(
                'data.0.date',
                '2026-09-10'
            );
    }

    public function test_複数の検索条件はand条件で絞り込まれる(): void
    {
        // Arrange
        $targetUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $targetAttendance = $this->createAttendance(
            $targetUser,
            '2026-09-10'
        );

        $this->createAttendance(
            $targetUser,
            '2026-10-10'
        );

        $this->createAttendance(
            $otherUser,
            '2026-09-10'
        );

        // Act
        $response = $this->getJson(
            '/api/v1/attendance-records'
            ."?user_id={$targetUser->id}"
            .'&month=2026-09'
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $targetAttendance->id
            );
    }

    public function test_per_pageを指定してページネーションできる(): void
    {
        // Arrange
        $user = User::factory()->create();

        for ($day = 1; $day <= 5; $day++) {
            $this->createAttendance(
                $user,
                sprintf('2026-09-%02d', $day)
            );
        }

        // Act
        $response = $this->getJson(
            '/api/v1/attendance-records?per_page=2&page=2'
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath(
                'meta.current_page',
                2
            )
            ->assertJsonPath(
                'meta.per_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                5
            );
    }

    public function test_per_page未指定時は20件になる(): void
    {
        // Arrange
        $user = User::factory()->create();

        for ($day = 1; $day <= 21; $day++) {
            $this->createAttendance(
                $user,
                sprintf('2026-09-%02d', $day)
            );
        }

        // Act
        $response = $this->getJson(
            '/api/v1/attendance-records'
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath(
                'meta.per_page',
                20
            )
            ->assertJsonPath(
                'meta.total',
                21
            );
    }

    public function test_per_pageは100件まで指定できる(): void
    {
        // Arrange
        User::factory()->create();

        // Act
        $response = $this->getJson(
            '/api/v1/attendance-records?per_page=100'
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.per_page',
                100
            );
    }

    public function test_per_pageが101件の場合はバリデーションエラーになる(): void
    {
        // Arrange
        User::factory()->create();

        // Act
        $response = $this->getJson(
            '/api/v1/attendance-records?per_page=101'
        );

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ])
            ->assertJsonPath(
                'errors.per_page.0',
                '1ページあたりの件数は100以下で指定してください。'
            );
    }

    public function test_pageが0の場合はバリデーションエラーになる(): void
    {
        // Arrange
        User::factory()->create();

        // Act
        $response = $this->getJson(
            '/api/v1/attendance-records?page=0'
        );

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page',
            ])
            ->assertJsonPath(
                'errors.page.0',
                'ページ番号は1以上で指定してください。'
            );
    }

    public function test_勤怠詳細を取得できる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance(
            $user,
            '2026-09-10'
        );

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-09-10 12:00:00',
            'break_out' => '2026-09-10 13:00:00',
        ]);

        // Act
        $response = $this->getJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $attendance->id
            )
            ->assertJsonPath(
                'data.user.id',
                $user->id
            )
            ->assertJsonPath(
                'data.user.name',
                $user->name
            )
            ->assertJsonPath(
                'data.date',
                '2026-09-10'
            )
            ->assertJsonPath(
                'data.clock_in',
                '09:00:00'
            )
            ->assertJsonPath(
                'data.clock_out',
                '18:00:00'
            )
            ->assertJsonPath(
                'data.total_time',
                '08:00'
            )
            ->assertJsonPath(
                'data.total_break_time',
                '01:00'
            )
            ->assertJsonPath(
                'data.breaks.0.break_in',
                '12:00:00'
            )
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'user',
                    'date',
                    'clock_in',
                    'clock_out',
                    'total_time',
                    'total_break_time',
                    'comment',
                    'breaks',
                    'applications',
                ],
            ]);
    }

    public function test_存在しない勤怠詳細は404になる(): void
    {
        // Arrange
        $missingId = 99999;

        // Act
        $response = $this->getJson(
            "/api/v1/attendance-records/{$missingId}"
        );

        // Assert
        $response
            ->assertNotFound()
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    public function test_承認待ちの修正申請がある場合は申請後の勤怠情報を返す(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance(
            $user,
            '2026-09-10',
            '09:00:00',
            '18:00:00'
        );

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-09-10 12:00:00',
            'break_out' => '2026-09-10 13:00:00',
        ]);

        $application = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in' => '2026-09-10 10:00:00',
            'requested_clock_out' => '2026-09-10 19:00:00',
            'requested_comment' => '勤務時間修正',
            'status' => 0,
        ]);

        AttendanceCorrectionBreak::create([
            'attendance_correction_request_id' => $application->id,
            'requested_break_in' => '2026-09-10 13:00:00',
            'requested_break_out' => '2026-09-10 14:00:00',
        ]);

        // Act
        $response = $this->getJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath(
                'data.clock_in',
                '10:00:00'
            )
            ->assertJsonPath(
                'data.clock_out',
                '19:00:00'
            )
            ->assertJsonPath(
                'data.total_break_time',
                '01:00'
            )
            ->assertJsonPath(
                'data.total_time',
                '08:00'
            )
            ->assertJsonPath(
                'data.comment',
                '勤務時間修正'
            )
            ->assertJsonPath(
                'data.breaks.0.break_in',
                '13:00:00'
            )
            ->assertJsonPath(
                'data.breaks.0.break_out',
                '14:00:00'
            )
            ->assertJsonPath(
                'data.applications.0.requested_clock_in',
                '10:00:00'
            )
            ->assertJsonPath(
                'data.applications.0.requested_clock_out',
                '19:00:00'
            )
            ->assertJsonPath(
                'data.applications.0.status',
                0
            );
    }

    public function test_per_pageが空の場合は20件になる(): void
    {
        $user = User::factory()->create();
        for ($day = 1; $day <= 21; $day++) {
            $this->createAttendance($user, sprintf('2026-09-%02d', $day));
        }

        $this->getJson('/api/v1/attendance-records?per_page=')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 21);
    }
}
