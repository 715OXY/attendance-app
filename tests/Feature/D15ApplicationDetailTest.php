<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD15ApprovalTests;
use Tests\TestCase;

class D15ApplicationDetailTest extends TestCase
{
    use RefreshDatabase, SupportsD15ApprovalTests;

    /**
     * @test
     */
    public function 正常系_修正申請の詳細内容が正しく表示される(): void
    {
        // Arrange
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => false,
        ]);

        $attendance = $this->createAttendance(
            $user,
            '2026-10-01'
        );

        $application = $this->createCorrectionRequest(
            $user,
            $attendance,
            '09:10',
            '18:15',
            '12:10',
            '13:10',
            '管理者承認画面テスト'
        );

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(
                route('admin.application.show', [
                    'attendance_correct_request_id' => $application->id,
                ])
            );

        // Assert
        $response->assertOk();

        $response->assertSee('テスト太郎');

        $response->assertSee('09:10');
        $response->assertSee('18:15');

        $response->assertSee('12:10');
        $response->assertSee('13:10');

        $response->assertSee(
            '管理者承認画面テスト'
        );
    }
}
