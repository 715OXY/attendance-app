<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SupportsD19ApiTests;
use Tests\TestCase;

class D19PublicApiSanctumTest extends TestCase
{
    use RefreshDatabase, SupportsD19ApiTests;

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

    public function test_acceptなしのweb未認証は従来どおりloginへ転送する(): void
    {
        $this->get('/attendance')->assertRedirect('/login');
    }
}
