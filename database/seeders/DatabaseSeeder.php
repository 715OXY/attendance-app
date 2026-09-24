<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 管理者
        User::factory()->admin()->create([
            'name' => '管理者',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        // 動作確認用の固定一般ユーザー
        $testUser = User::factory()->create([
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // 一般ユーザーのダミーデータ
        $users = User::factory()
            ->count(5)
            ->create();

        // 固定ユーザーも勤怠生成対象に加える
        $users->push($testUser);

        foreach ($users as $user) {
            $this->createAttendanceRecords($user);
        }
    }

    private function createAttendanceRecords(User $user): void
    {
        $startDate = now()->subMonth()->startOfMonth();
        $endDate = now()->subDay();
        for (
            $date = $startDate->copy();
            $date->lte($endDate);
            $date->addDay()
        ) {
            // 土日はダミー勤怠を作らない
            if ($date->isWeekend()) {
                continue;
            }

            $attendance = Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => $date->toDateString(),
                'clock_in' => $date->copy()
                    ->setTime(9, random_int(0, 15)),
                'clock_out' => $date->copy()
                    ->setTime(18, random_int(0, 30)),
                'comment' => null,
            ]);

            $this->createBreak($attendance, $date);
        }
    }

    private function createBreak(
        Attendance $attendance,
        Carbon $date
    ): void {
        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => $date->copy()->setTime(12, 0),
            'break_out' => $date->copy()->setTime(13, 0),
        ]);
    }
}
