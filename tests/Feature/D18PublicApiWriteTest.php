<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D18PublicApiWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_認証ユーザーは勤怠を新規登録できる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API登録テスト',
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
            ->assertCreated()
            ->assertJsonPath(
                'data.user_id',
                $user->id
            )
            ->assertJsonPath(
                'data.date',
                '2026-10-10'
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
                'data.comment',
                'API登録テスト'
            );

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'comment' => 'API登録テスト',
        ]);
    }

    public function test_退勤時刻を省略して勤怠を登録できる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'comment' => '勤務中',
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
            ->assertCreated()
            ->assertJsonPath('data.clock_out', null);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'clock_out' => null,
        ]);
    }
}
