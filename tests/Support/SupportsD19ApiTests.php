<?php

namespace Tests\Support;

use App\Models\Attendance;
use App\Models\User;

/**
 * D19の認証・認可テストで使用する準備処理を提供する。
 */
trait SupportsD19ApiTests
{
    protected function createAttendance(User $owner): Attendance
    {
        return Attendance::create([
            'user_id' => $owner->id,
            'date' => '2026-10-10',
            'clock_in' => '2026-10-10 09:00:00',
            'clock_out' => '2026-10-10 18:00:00',
            'comment' => '変更前',
        ]);
    }

    protected function authenticateWithToken(User $user): void
    {
        $this->withToken($user->createToken('D19-test')->plainTextToken);
    }

    protected function updatePayload(string $method): array
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
}
