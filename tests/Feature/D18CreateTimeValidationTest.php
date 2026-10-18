<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 勤怠登録時の出退勤時刻の入力検証を確認する。
 */
class D18CreateTimeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_出勤時刻が未入力の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_out' => '18:00:00',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/attendance-records',
                $payload
            );

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_in.0',
                '出勤時刻は必須です。'
            );
    }

    public function test_出勤時刻の形式が不正な場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00',
            'clock_out' => '18:00:00',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/attendance-records',
                $payload
            );

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_in.0',
                '出勤時刻は HH:MM:SS 形式で指定してください。'
            );
    }

    public function test_退勤時刻の形式が不正な場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/attendance-records',
                $payload
            );

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_out.0',
                '退勤時刻は HH:MM:SS 形式で指定してください。'
            );
    }

    public function test_退勤時刻が出勤時刻より前の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'clock_out' => '08:59:59',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/attendance-records',
                $payload
            );

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_out.0',
                '退勤時刻は出勤時刻より後の時刻を指定してください。'
            );
    }

    public function test_退勤時刻が出勤時刻と同時刻の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'clock_out' => '09:00:00',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/attendance-records',
                $payload
            );

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_out.0',
                '退勤時刻は出勤時刻より後の時刻を指定してください。'
            );
    }
}
