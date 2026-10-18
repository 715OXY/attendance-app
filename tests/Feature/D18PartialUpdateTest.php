<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesD18Attendance;
use Tests\TestCase;

/**
 * 勤怠APIの部分更新と未送信項目の保持を確認する。
 */
class D18PartialUpdateTest extends TestCase
{
    use CreatesD18Attendance, RefreshDatabase;

    public function test_patchで退勤時刻をnullに更新できる(): void
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
                    'clock_out' => null,
                ]
            );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.clock_out', null)
            ->assertJsonPath('data.total_time', null);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_out' => null,
        ]);
    }

    public function test_putで備考だけ部分更新できる(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);
        $this->actingAs($user)->putJson("/api/v1/attendance-records/{$attendance->id}", [
            'comment' => 'PUT部分更新',
        ])->assertOk()->assertJsonPath('data.comment', 'PUT部分更新');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'clock_in' => '2026-10-10 09:00:00',
            'clock_out' => '2026-10-10 18:00:00',
            'comment' => 'PUT部分更新',
        ]);
    }

    /** @dataProvider partialUpdateMethods */
    public function test_空bodyの更新は既存値を保持する(string $method): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);
        $before = $attendance->fresh()->getRawOriginal();
        $this->actingAs($user)->json($method, "/api/v1/attendance-records/{$attendance->id}", [])
            ->assertOk()->assertJsonPath('data.comment', '変更前');
        $this->assertSame($before, $attendance->fresh()->getRawOriginal());
    }

    /** @dataProvider requiredNullFields */
    public function test_必須項目のnull更新は422になる(string $method, string $field, string $message): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);
        $before = $attendance->fresh()->getRawOriginal();
        $this->actingAs($user)->json($method, "/api/v1/attendance-records/{$attendance->id}", [$field => null])
            ->assertUnprocessable()->assertJsonPath("errors.{$field}.0", $message);
        $this->assertSame($before, $attendance->fresh()->getRawOriginal());
    }

    /** @dataProvider clockInBoundaries */
    public function test_出勤だけ変更する場合も既存退勤との前後関係を検証する(string $clockIn, bool $allowed): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);
        $before = $attendance->fresh()->getRawOriginal();
        $response = $this->actingAs($user)->patchJson("/api/v1/attendance-records/{$attendance->id}", ['clock_in' => $clockIn]);
        if (! $allowed) {
            $response->assertUnprocessable()->assertJsonPath('errors.clock_out.0', '退勤時刻は出勤時刻より後の時刻を指定してください。');
            $this->assertSame($before, $attendance->fresh()->getRawOriginal());

            return;
        }
        $response->assertOk()->assertJsonPath('data.clock_in', $clockIn)->assertJsonPath('data.clock_out', '18:00:00');
        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '2026-10-10 '.$clockIn,
            'clock_out' => '2026-10-10 18:00:00',
        ]);
    }

    public static function partialUpdateMethods(): array
    {
        return ['PUT' => ['PUT'], 'PATCH' => ['PATCH']];
    }

    public static function requiredNullFields(): array
    {
        return [
            'PUT date' => ['PUT', 'date', '勤怠日は必須です。'],
            'PATCH date' => ['PATCH', 'date', '勤怠日は必須です。'],
            'PUT clock_in' => ['PUT', 'clock_in', '出勤時刻は必須です。'],
            'PATCH clock_in' => ['PATCH', 'clock_in', '出勤時刻は必須です。'],
        ];
    }

    public static function clockInBoundaries(): array
    {
        return [
            '退勤より前' => ['17:59:59', true],
            '退勤と同時刻' => ['18:00:00', false],
            '退勤より後' => ['18:00:01', false],
        ];
    }
}
