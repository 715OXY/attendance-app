<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 一覧・レポートの取得件数によってSELECT回数が増えないことを確認する。
 */
class D21NPlusOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 10, 15, 12, 0, 0, 'Asia/Tokyo')
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public static function scenarios(): array
    {
        return [
            '一般ユーザーの月次一覧' => ['user'],
            '管理者の日次一覧' => ['admin'],
            '公開APIの一覧' => ['api'],
            'マイ勤怠レポート' => ['report'],
        ];
    }

    /**
     * @dataProvider scenarios
     */
    public function test_勤怠件数が増えてもselect回数は増えない(
        string $scenario
    ): void {
        $user = User::factory()->create([
            'admin_status' => false,
            'email_verified_at' => now(),
        ]);

        $admin = User::factory()->create([
            'admin_status' => true,
            'email_verified_at' => now(),
        ]);

        if ($scenario !== 'api') {
            $this->actingAs($scenario === 'admin' ? $admin : $user);
        }

        $date = $scenario === 'report' ? '2026-09-01' : '2026-10-01';

        $uri = match ($scenario) {
            'user' => '/attendance/list?date=2026-10',
            'admin' => '/admin/attendance/list?date=2026-10-01',
            'api' => '/api/v1/attendance-records?user_id='.$user->id,
            'report' => '/attendance/report',
        };

        $this->createAttendanceWithBreak($user, $date);
        $singleCount = $this->countSelectsFor($uri);

        for ($offset = 1; $offset <= 4; $offset++) {
            $owner = $scenario === 'admin'
                ? User::factory()->create(['admin_status' => false])
                : $user;

            $attendanceDate = $scenario === 'admin'
                ? $date
                : Carbon::parse($date)->addDays($offset)->toDateString();

            $this->createAttendanceWithBreak($owner, $attendanceDate);
        }

        $multipleCount = $this->countSelectsFor($uri);

        $this->assertSame(
            $singleCount,
            $multipleCount,
            "SELECT回数が {$singleCount} 回から {$multipleCount} 回に増えました。"
        );
    }

    /**
     * 勤怠と1時間の休憩を作成する。
     */
    private function createAttendanceWithBreak(User $user, string $date): void
    {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $date.' 09:00:00',
            'clock_out' => $date.' 18:00:00',
            'comment' => null,
        ]);

        $attendance->breaks()->create([
            'break_in' => $date.' 12:00:00',
            'break_out' => $date.' 13:00:00',
        ]);
    }

    /**
     * レスポンス生成中に実行されたSELECTの回数を返す。
     */
    private function countSelectsFor(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $response = $this->get($uri);

            $selectCount = count(array_filter(
                DB::getQueryLog(),
                fn (array $query): bool => str_starts_with(
                    strtolower(ltrim($query['query'])),
                    'select'
                )
            ));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $response->assertOk();

        return $selectCount;
    }
}
