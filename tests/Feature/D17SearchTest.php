<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD17Attendance;
use Tests\TestCase;

class D17SearchTest extends TestCase
{
    use CreatesD17Attendance, RefreshDatabase;

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
}
