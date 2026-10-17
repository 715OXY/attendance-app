<?php

namespace Tests\Feature;

use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD17Attendance;
use Tests\TestCase;

class D17PublicApiReadTest extends TestCase
{
    use CreatesD17Attendance, RefreshDatabase;

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
}
