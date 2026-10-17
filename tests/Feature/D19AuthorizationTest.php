<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD19ApiTests;
use Tests\TestCase;

/**
 * 勤怠APIの更新・削除に対する操作権限を確認する。
 */
class D19AuthorizationTest extends TestCase
{
    use RefreshDatabase, SupportsD19ApiTests;

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

    private function actor(string $role, User $owner): User
    {
        return match ($role) {
            'owner' => $owner,
            'admin' => User::factory()->admin()->create(),
            'other' => User::factory()->create(),
        };
    }
}
