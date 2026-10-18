<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD15ApprovalTests;
use Tests\TestCase;

class D15ApplicationListTest extends TestCase
{
    use RefreshDatabase, SupportsD15ApprovalTests;

    /**
     * @test
     */
    public function 正常系_承認待ちに全ユーザーの未承認修正申請が表示される(): void
    {
        // Arrange
        [$admin, $user1, $user2] = $this->createListUsers(
            'テスト太郎',
            'テスト花子'
        );

        $attendance1 = $this->createAttendance(
            $user1,
            '2026-10-01'
        );

        $attendance2 = $this->createAttendance(
            $user2,
            '2026-10-02'
        );

        $application1 = $this->createCorrectionRequest(
            $user1,
            $attendance1,
            '09:10',
            '18:10',
            '12:10',
            '13:10',
            '太郎の承認待ち申請'
        );

        $application2 = $this->createCorrectionRequest(
            $user2,
            $attendance2,
            '09:20',
            '18:20',
            '12:20',
            '13:20',
            '花子の承認待ち申請'
        );

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(route('attendance.application.list'));

        // Assert
        $response->assertOk();

        $response->assertSee('承認待ち');

        $response->assertSee('テスト太郎');
        $response->assertSee('太郎の承認待ち申請');

        $response->assertSee('テスト花子');
        $response->assertSee('花子の承認待ち申請');

        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'id' => $application1->id,
                'status' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'id' => $application2->id,
                'status' => 0,
            ]
        );
    }

    /**
     * @test
     */
    public function 正常系_承認済みに全ユーザーの承認済み修正申請が表示される(): void
    {
        // Arrange
        [$admin, $user1, $user2] = $this->createListUsers(
            '承認済み太郎',
            '承認済み花子'
        );

        $attendance1 = $this->createAttendance(
            $user1,
            '2026-10-01'
        );

        $attendance2 = $this->createAttendance(
            $user2,
            '2026-10-02'
        );

        $application1 = $this->createCorrectionRequest(
            $user1,
            $attendance1,
            '09:10',
            '18:10',
            '12:10',
            '13:10',
            '太郎の承認済み申請'
        );

        $application2 = $this->createCorrectionRequest(
            $user2,
            $attendance2,
            '09:20',
            '18:20',
            '12:20',
            '13:20',
            '花子の承認済み申請'
        );

        // 承認済み状態を準備
        $application1->update([
            'status' => 1,
        ]);

        $application2->update([
            'status' => 1,
        ]);

        // Act
        $response = $this
            ->actingAs($admin)
            ->get(route('attendance.application.list'));

        // Assert
        $response->assertOk();

        $response->assertSee('承認済み');

        $response->assertSee('承認済み太郎');
        $response->assertSee('太郎の承認済み申請');

        $response->assertSee('承認済み花子');
        $response->assertSee('花子の承認済み申請');

        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'id' => $application1->id,
                'status' => 1,
            ]
        );

        $this->assertDatabaseHas(
            'attendance_correction_requests',
            [
                'id' => $application2->id,
                'status' => 1,
            ]
        );
    }

    /**
     * 一覧確認用の管理者と一般ユーザー2名を作成する。
     *
     * @param  string  $firstName  1人目の氏名
     * @param  string  $secondName  2人目の氏名
     * @return array{0: User, 1: User, 2: User} 管理者と一般ユーザー2名
     */
    private function createListUsers(
        string $firstName,
        string $secondName
    ): array {
        return [
            User::factory()->create(['admin_status' => true]),
            User::factory()->create([
                'name' => $firstName,
                'admin_status' => false,
            ]),
            User::factory()->create([
                'name' => $secondName,
                'admin_status' => false,
            ]),
        ];
    }
}
