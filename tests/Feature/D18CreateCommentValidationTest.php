<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 勤怠登録時の備考の文字数制限を確認する。
 */
class D18CreateCommentValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_備考は255文字まで登録できる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => str_repeat('あ', 255),
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->postJson(
                '/api/v1/attendance-records',
                $payload
            );

        // Assert
        $response->assertCreated();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'comment' => str_repeat('あ', 255),
        ]);
    }

    public function test_備考が256文字の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => str_repeat('あ', 256),
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
                'errors.comment.0',
                '備考は 255 文字以内で入力してください。'
            );
    }
}
