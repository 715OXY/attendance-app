<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * アプリケーションのダミーデータを登録する。
     */
    public function run(): void
    {
        $user1 = User::factory()->create([
            'name' => '一般ユーザー1',
            'email' => 'user1@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'admin_status' => false,
        ]);

        $user2 = User::factory()->create([
            'name' => '一般ユーザー2',
            'email' => 'user2@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'admin_status' => false,
        ]);

        $admin = User::factory()->admin()->create([
            'name' => '管理者ユーザー',
            'email' => 'user3@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'admin_status' => true,
        ]);

        $this->createReportAttendanceRecords($user1);
        $this->createStandardAttendanceRecords($user2);
        $this->createStandardAttendanceRecords($admin);
    }

    /**
     * user1用のマイ勤怠レポート検証データを作成する。
     */
    private function createReportAttendanceRecords(User $user): void
    {
        $latestMonth = now()
            ->subMonthNoOverflow()
            ->startOfMonth();

        $startMonth = $latestMonth
            ->copy()
            ->subMonths(5);

        collect(range(0, 4))
            ->each(function (int $offset) use ($user, $startMonth): void {
                $month = $startMonth
                    ->copy()
                    ->addMonths($offset);

                $this->weekdaysForMonth($month, 15)
                    ->each(function (Carbon $date) use ($user): void {
                        $this->createAttendance(
                            $user,
                            $date,
                            '09:00',
                            '18:00'
                        );
                    });
            });

        $latestMonthDates = $this->weekdaysForMonth(
            $latestMonth,
            17
        );

        $patterns = collect([
            ...array_fill(0, 10, ['09:00', '18:00']),
            ...array_fill(0, 3, ['09:00', '20:00']),
            ...array_fill(0, 2, ['09:30', '18:00']),
            ['09:00', '17:00'],
            ['08:00', '21:00'],
        ]);

        $latestMonthDates
            ->values()
            ->each(function (Carbon $date, int $index) use ($user, $patterns): void {
                [$clockIn, $clockOut] = $patterns[$index];

                $this->createAttendance(
                    $user,
                    $date,
                    $clockIn,
                    $clockOut
                );
            });
    }

    /**
     * 通常ユーザー用の勤怠データを作成する。
     */
    private function createStandardAttendanceRecords(User $user): void
    {
        $month = now()
            ->subMonthNoOverflow()
            ->startOfMonth();

        $this->weekdaysForMonth($month, 15)
            ->each(function (Carbon $date) use ($user): void {
                $this->createAttendance(
                    $user,
                    $date,
                    '09:00',
                    '18:00'
                );
            });
    }

    /**
     * 指定月から指定件数の平日を取得する。
     */
    private function weekdaysForMonth(
        Carbon $month,
        int $count
    ): Collection {
        $endOfMonth = $month
            ->copy()
            ->endOfMonth();

        $dates = collect();

        for (
            $date = $month->copy();
            $date->lte($endOfMonth) && $dates->count() < $count;
            $date->addDay()
        ) {
            if ($date->isWeekday()) {
                $dates->push($date->copy());
            }
        }

        return $dates;
    }

    /**
     * 勤怠と固定休憩を作成する。
     */
    private function createAttendance(
        User $user,
        Carbon $date,
        string $clockIn,
        string $clockOut
    ): void {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date->toDateString(),
            'clock_in' => $date
                ->copy()
                ->setTimeFromTimeString($clockIn),
            'clock_out' => $date
                ->copy()
                ->setTimeFromTimeString($clockOut),
            'comment' => null,
        ]);

        $this->createBreak($attendance, $date);
    }

    /**
     * 12:00〜13:00の固定休憩を作成する。
     */
    private function createBreak(
        Attendance $attendance,
        Carbon $date
    ): void {
        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => $date
                ->copy()
                ->setTime(12, 0),
            'break_out' => $date
                ->copy()
                ->setTime(13, 0),
        ]);
    }
}
