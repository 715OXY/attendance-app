<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class D19PublicApiSanctumTest extends TestCase
{
    use RefreshDatabase;

    private function createAttendance(User $owner): Attendance
    {
        return Attendance::create([
            'user_id' => $owner->id,
            'date' => '2026-10-10',
            'clock_in' => '2026-10-10 09:00:00',
            'clock_out' => '2026-10-10 18:00:00',
            'comment' => '変更前',
        ]);
    }

    private function authenticateWithToken(User $user): void
    {
        $this->withToken($user->createToken('D19-test')->plainTextToken);
    }

    private function updatePayload(string $method): array
    {
        return $method === 'PUT'
            ? [
                'date' => '2026-10-11',
                'clock_in' => '08:30:00',
                'clock_out' => '17:30:00',
                'comment' => '更新後',
            ]
            : ['comment' => '更新後'];
    }

    public static function writeMethods(): array
    {
        return [
            'POST' => ['POST'],
            'PUT' => ['PUT'],
            'PATCH' => ['PATCH'],
            'DELETE' => ['DELETE'],
        ];
    }

    /** @dataProvider writeMethods */
    public function test_未認証の書き込みは401になる(string $method): void
    {
        $owner = User::factory()->create();
        $attendance = $this->createAttendance($owner);
        $before = $attendance->fresh()->getRawOriginal();
        $url = '/api/v1/attendance-records';

        if ($method !== 'POST') {
            $url .= "/{$attendance->id}";
        }

        $payload = $method === 'POST'
            ? ['date' => '2026-10-11', 'clock_in' => '09:00:00']
            : ($method === 'DELETE' ? [] : $this->updatePayload($method));

        $this->json($method, $url, $payload)
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->assertDatabaseCount('attendances', 1);
        $this->assertSame($before, $attendance->fresh()->getRawOriginal());
    }

    public static function updateActors(): array
    {
        return [
            '本人 PUT' => ['owner', 'PUT', true],
            '本人 PATCH' => ['owner', 'PATCH', true],
            '管理者 PUT' => ['admin', 'PUT', true],
            '管理者 PATCH' => ['admin', 'PATCH', true],
            '他の一般ユーザー PUT' => ['other', 'PUT', false],
            '他の一般ユーザー PATCH' => ['other', 'PATCH', false],
        ];
    }

    /** @dataProvider updateActors */
    public function test_トークン認証で本人または管理者だけが更新できる(
        string $role,
        string $method,
        bool $allowed
    ): void {
        $owner = User::factory()->create();
        $attendance = $this->createAttendance($owner);
        $before = $attendance->fresh()->getRawOriginal();
        $actor = $this->actor($role, $owner);
        $this->authenticateWithToken($actor);

        $response = $this->json(
            $method,
            "/api/v1/attendance-records/{$attendance->id}",
            $this->updatePayload($method)
        );

        if (! $allowed) {
            $response->assertForbidden()->assertExactJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);
            $this->assertSame($before, $attendance->fresh()->getRawOriginal());

            return;
        }

        $date = $method === 'PUT' ? '2026-10-11' : '2026-10-10';
        $clockIn = $method === 'PUT' ? '08:30:00' : '09:00:00';
        $clockOut = $method === 'PUT' ? '17:30:00' : '18:00:00';

        $response->assertOk()
            ->assertJsonPath('data.user_id', $owner->id)
            ->assertJsonPath('data.date', $date)
            ->assertJsonPath('data.clock_in', $clockIn)
            ->assertJsonPath('data.clock_out', $clockOut)
            ->assertJsonPath('data.comment', '更新後');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $owner->id,
            'date' => $date,
            'clock_in' => "{$date} {$clockIn}",
            'clock_out' => "{$date} {$clockOut}",
            'comment' => '更新後',
        ]);
    }

    public static function deleteActors(): array
    {
        return [
            '本人' => ['owner', true],
            '管理者' => ['admin', true],
            '他の一般ユーザー' => ['other', false],
        ];
    }

    /** @dataProvider deleteActors */
    public function test_トークン認証で本人または管理者だけが削除できる(
        string $role,
        bool $allowed
    ): void {
        $owner = User::factory()->create();
        $attendance = $this->createAttendance($owner);
        $before = $attendance->fresh()->getRawOriginal();
        $this->authenticateWithToken($this->actor($role, $owner));

        $response = $this->deleteJson("/api/v1/attendance-records/{$attendance->id}");

        if (! $allowed) {
            $response->assertForbidden()->assertExactJson([
                'error' => 'この操作を実行する権限がありません。',
            ]);
            $this->assertSame($before, $attendance->fresh()->getRawOriginal());

            return;
        }

        $response->assertNoContent();
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
    }

    private function actor(string $role, User $owner): User
    {
        return match ($role) {
            'owner' => $owner,
            'admin' => User::factory()->admin()->create(),
            'other' => User::factory()->create(),
        };
    }

    public static function invalidUpdateMethods(): array
    {
        return ['PUT' => ['PUT'], 'PATCH' => ['PATCH']];
    }

    /** @dataProvider invalidUpdateMethods */
    public function test_他ユーザーの不正更新でも検証より先に403を返す(string $method): void
    {
        $owner = User::factory()->create();
        $attendance = $this->createAttendance($owner);
        $before = $attendance->fresh()->getRawOriginal();
        $this->authenticateWithToken(User::factory()->create());
        $this->json($method, "/api/v1/attendance-records/{$attendance->id}", ['clock_out' => '08:00:00'])
            ->assertForbidden()->assertExactJson(['error' => 'この操作を実行する権限がありません。']);
        $this->assertSame($before, $attendance->fresh()->getRawOriginal());
    }

    public function test_acceptなしの未認証postは401jsonになる(): void
    {
        $this->post('/api/v1/attendance-records', ['date' => '2026-10-10', 'clock_in' => '09:00:00'])
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Unauthenticated.']);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_acceptなしの認証済み不正postは422jsonになる(): void
    {
        $this->authenticateWithToken(User::factory()->create());
        $this->post('/api/v1/attendance-records', [])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonPath('errors.date.0', '勤怠日は必須です。')
            ->assertJsonPath('errors.clock_in.0', '出勤時刻は必須です。');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_実bearerトークンで勤怠を登録できる(): void
    {
        $user = User::factory()->create();
        $this->authenticateWithToken($user);
        $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-10-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'トークン登録',
        ])->assertCreated()->assertJsonPath('data.user_id', $user->id)->assertJsonPath('data.comment', 'トークン登録');
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'clock_in' => '2026-10-10 09:00:00',
            'clock_out' => '2026-10-10 18:00:00',
            'comment' => 'トークン登録',
        ]);
    }

    public static function duplicateOwners(): array
    {
        return ['所有者の重複は拒否' => [true], '管理者だけの重複は許可' => [false]];
    }

    /** @dataProvider duplicateOwners */
    public function test_管理者更新の重複判定は勤怠所有者を基準にする(bool $ownerDuplicate): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $attendance = $this->createAttendance($owner);
        $before = $attendance->fresh()->getRawOriginal();
        Attendance::create([
            'user_id' => $ownerDuplicate ? $owner->id : $admin->id,
            'date' => '2026-10-11',
            'clock_in' => '2026-10-11 09:00:00',
            'clock_out' => '2026-10-11 18:00:00',
        ]);
        $this->authenticateWithToken($admin);
        $response = $this->patchJson("/api/v1/attendance-records/{$attendance->id}", ['date' => '2026-10-11']);
        if ($ownerDuplicate) {
            $response->assertUnprocessable()->assertJsonPath('errors.date.0', 'この日付の勤怠は既に登録されています。');
            $this->assertSame($before, $attendance->fresh()->getRawOriginal());

            return;
        }
        $response->assertOk()->assertJsonPath('data.user_id', $owner->id)->assertJsonPath('data.date', '2026-10-11');
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id, 'user_id' => $owner->id, 'date' => '2026-10-11']);
    }

    public function test_acceptなしのweb未認証は従来どおりloginへ転送する(): void
    {
        $this->get('/attendance')->assertRedirect('/login');
    }
}
