<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D04AttendanceDateTimeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function 正常系_現在の日時が指定された形式で表示される(): void
    {
        // Arrange
        Carbon::setTestNow(
            Carbon::create(2026, 10, 1, 7, 12, 0, 'Asia/Tokyo')
        );

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $expectedDate = '2026年10月1日(木)';
        $expectedTime = '07:12';

        // Act
        $response = $this
            ->actingAs($user)
            ->get(route('attendance.index'));

        // Assert
        $response->assertOk();
        $response->assertSee($expectedDate);
        $response->assertSee($expectedTime);

        Carbon::setTestNow();
    }
}
