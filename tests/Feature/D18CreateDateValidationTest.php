<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 勤怠登録時の日付の入力検証を確認する。
 */
class D18CreateDateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_勤怠日が未入力の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'clock_in' => '09:00:00',
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
                'errors.date.0',
                '勤怠日は必須です。'
            );
    }

    public function test_勤怠日の形式が不正な場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026/10/10',
            'clock_in' => '09:00:00',
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
                'errors.date.0',
                '勤怠日は YYYY-MM-DD 形式で指定してください。'
            );
    }

    public function test_同一ユーザーの同一日に勤怠を重複登録できない(): void
    {
        // Arrange
        $user = User::factory()->create();

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'clock_in' => '2026-10-10 08:00:00',
            'clock_out' => '2026-10-10 17:00:00',
            'comment' => null,
        ]);

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
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
                'errors.date.0',
                'この日付の勤怠は既に登録されています。'
            );
    }
}
