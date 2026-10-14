<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionBreak;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D18PublicApiWriteTest extends TestCase
{
    use RefreshDatabase;

    private function createAttendance(
        User $user,
        string $date = '2026-10-10',
        string $clockIn = '09:00:00',
        ?string $clockOut = '18:00:00',
        ?string $comment = '変更前'
    ): Attendance {
        return Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => "{$date} {$clockIn}",
            'clock_out' => $clockOut
                ? "{$date} {$clockOut}"
                : null,
            'comment' => $comment,
        ]);
    }

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

    public function test_出勤時刻が未入力の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
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
                'errors.clock_in.0',
                '出勤時刻は必須です。'
            );
    }

    public function test_出勤時刻の形式が不正な場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00',
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
                'errors.clock_in.0',
                '出勤時刻は HH:MM:SS 形式で指定してください。'
            );
    }

    public function test_退勤時刻の形式が不正な場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00',
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
                'errors.clock_out.0',
                '退勤時刻は HH:MM:SS 形式で指定してください。'
            );
    }

    public function test_退勤時刻が出勤時刻より前の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'clock_out' => '08:59:59',
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
                'errors.clock_out.0',
                '退勤時刻は出勤時刻より後の時刻を指定してください。'
            );
    }

    public function test_退勤時刻が出勤時刻と同時刻の場合はバリデーションエラーになる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'clock_out' => '09:00:00',
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
                'errors.clock_out.0',
                '退勤時刻は出勤時刻より後の時刻を指定してください。'
            );
    }

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

    public function test_putで勤怠の全項目を更新できる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        $payload = [
            'date' => '2026-10-10',
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'comment' => 'PUT更新',
        ];

        // Act
        $response = $this
            ->actingAs($user)
            ->putJson(
                "/api/v1/attendance-records/{$attendance->id}",
                $payload
            );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-10')
            ->assertJsonPath('data.clock_in', '08:30:00')
            ->assertJsonPath('data.clock_out', '17:30:00')
            ->assertJsonPath('data.comment', 'PUT更新');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'clock_in' => '2026-10-10 08:30:00',
            'clock_out' => '2026-10-10 17:30:00',
            'comment' => 'PUT更新',
        ]);
    }

    public function test_patchで備考だけ部分更新でき未送信項目は保持される(): void
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
                    'comment' => '備考のみ更新',
                ]
            );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-10')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00')
            ->assertJsonPath('data.comment', '備考のみ更新');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'date' => '2026-10-10',
            'clock_in' => '2026-10-10 09:00:00',
            'clock_out' => '2026-10-10 18:00:00',
            'comment' => '備考のみ更新',
        ]);
    }

    public function test_patchで日付だけ変更しても既存時刻は保持される(): void
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
                    'date' => '2026-10-11',
                ]
            );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.date', '2026-10-11')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'date' => '2026-10-11',
            'clock_in' => '2026-10-11 09:00:00',
            'clock_out' => '2026-10-11 18:00:00',
        ]);
    }

    public function test_patchで退勤時刻だけ更新できる(): void
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
                    'clock_out' => '19:00:00',
                ]
            );

        // Assert
        $response
            ->assertOk()
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '19:00:00');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '2026-10-10 09:00:00',
            'clock_out' => '2026-10-10 19:00:00',
        ]);
    }

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

    public function test_勤怠を削除できる(): void
    {
        // Arrange
        $user = User::factory()->create();

        $attendance = $this->createAttendance($user);

        // Act
        $response = $this
            ->actingAs($user)
            ->deleteJson(
                "/api/v1/attendance-records/{$attendance->id}"
            );

        // Assert
        $response->assertNoContent();

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
        ]);
    }

    public function test_存在しない勤怠を削除すると404になる(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this
            ->actingAs($user)
            ->deleteJson(
                '/api/v1/attendance-records/99999'
            );

        // Assert
        $response
            ->assertNotFound()
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
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

    public static function partialUpdateMethods(): array
    {
        return ['PUT' => ['PUT'], 'PATCH' => ['PATCH']];
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

    public static function requiredNullFields(): array
    {
        return [
            'PUT date' => ['PUT', 'date', '勤怠日は必須です。'],
            'PATCH date' => ['PATCH', 'date', '勤怠日は必須です。'],
            'PUT clock_in' => ['PUT', 'clock_in', '出勤時刻は必須です。'],
            'PATCH clock_in' => ['PATCH', 'clock_in', '出勤時刻は必須です。'],
        ];
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

    public static function clockInBoundaries(): array
    {
        return [
            '退勤より前' => ['17:59:59', true],
            '退勤と同時刻' => ['18:00:00', false],
            '退勤より後' => ['18:00:01', false],
        ];
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

    public function test_勤怠削除で休憩と修正申請と修正休憩も削除される(): void
    {
        $user = User::factory()->create();
        $attendance = $this->createAttendance($user);
        $break = BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => '2026-10-10 12:00:00',
            'break_out' => '2026-10-10 13:00:00',
        ]);
        $application = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'requested_clock_in' => '2026-10-10 10:00:00',
            'requested_clock_out' => '2026-10-10 19:00:00',
            'requested_comment' => '修正申請',
            'status' => 0,
        ]);
        $correctionBreak = AttendanceCorrectionBreak::create([
            'attendance_correction_request_id' => $application->id,
            'requested_break_in' => '2026-10-10 13:00:00',
            'requested_break_out' => '2026-10-10 14:00:00',
        ]);

        $this->actingAs($user)->deleteJson("/api/v1/attendance-records/{$attendance->id}")->assertNoContent();
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
        $this->assertDatabaseMissing('breaks', ['id' => $break->id]);
        $this->assertDatabaseMissing('attendance_correction_requests', ['id' => $application->id]);
        $this->assertDatabaseMissing('attendance_correction_breaks', ['id' => $correctionBreak->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
