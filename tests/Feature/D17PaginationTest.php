<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD17Attendance;
use Tests\TestCase;

class D17PaginationTest extends TestCase
{
    use CreatesD17Attendance, RefreshDatabase;

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
