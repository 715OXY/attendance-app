<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD18Attendance;
use Tests\TestCase;

/**
 * 勤怠APIの更新時の入力検証と存在確認を行う。
 */
class D18UpdateValidationTest extends TestCase
{
    use CreatesD18Attendance, RefreshDatabase;

    public function test_同一ユーザーの別勤怠日へ変更すると重複エラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance(
            $user,
            '2026-10-10'
        );

        $this->createAttendance(
            $user,
            '2026-10-11'
        );

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
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.date.0',
                'この日付の勤怠は既に登録されています。'
            );
    }

    public function test_patchした退勤時刻が既存出勤時刻より前の場合はエラーになる(): void
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
                    'clock_out' => '08:59:59',
                ]
            );

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_out.0',
                '退勤時刻は出勤時刻より後の時刻を指定してください。'
            );
    }

    public function test_patchした退勤時刻が既存出勤時刻と同時刻の場合はエラーになる(): void
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
                    'clock_out' => '09:00:00',
                ]
            );

        // Assert
        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.clock_out.0',
                '退勤時刻は出勤時刻より後の時刻を指定してください。'
            );
    }

    public function test_存在しない勤怠を更新すると404になる(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this
            ->actingAs($user)
            ->putJson(
                '/api/v1/attendance-records/99999',
                [
                    'date' => '2026-10-10',
                    'clock_in' => '09:00:00',
                    'clock_out' => '18:00:00',
                ]
            );

        // Assert
        $response
            ->assertNotFound()
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }
}
