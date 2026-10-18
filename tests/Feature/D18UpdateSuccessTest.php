<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD18Attendance;
use Tests\TestCase;

/**
 * 勤怠APIの更新成功時の動作を確認する。
 */
class D18UpdateSuccessTest extends TestCase
{
    use CreatesD18Attendance, RefreshDatabase;

    public function test_putで勤怠の全項目を更新できる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => 'PUT更新',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/attendance-records/{$attendance->id}",
                $payload
            );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-10')
            ->assertJsonPath('data.clock_in', '08:30:00')
            ->assertJsonPath('data.clock_out', '17:30:00')
            ->assertJsonPath('data.comment', 'PUT更新');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'clock_in' => '2026-10-10 08:30:00',
            'clock_out' => '2026-10-10 17:30:00',
            'comment' => 'PUT更新',
        ]);
    }

    public function test_patchで備考だけ部分更新でき未送信項目は保持される(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        // Act
        $response = $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/attendance-records/{$attendance->id}",
                [
                    'comment' => '備考のみ更新',
                ]
            );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-10')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00')
            ->assertJsonPath('data.comment', '備考のみ更新');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'date' => '2026-10-10',
            'clock_in' => '2026-10-10 09:00:00',
            'clock_out' => '2026-10-10 18:00:00',
            'comment' => '備考のみ更新',
        ]);
    }

    public function test_patchで日付だけ変更しても既存時刻は保持される(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        // Act
        $response = $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/attendance-records/{$attendance->id}",
                [
                    'date' => '2026-10-11',
                ]
            );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-11')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'date' => '2026-10-11',
            'clock_in' => '2026-10-11 09:00:00',
            'clock_out' => '2026-10-11 18:00:00',
        ]);
    }

    public function test_patchで退勤時刻だけ更新できる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        // Act
        $response = $this
            ->actingAs($user)
            ->patchJson(
                "/api/v1/attendance-records/{$attendance->id}",
                [
                    'clock_out' => '19:00:00',
                ]
            );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '19:00:00');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '2026-10-10 09:00:00',
            'clock_out' => '2026-10-10 19:00:00',
        ]);
    }
}
