<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceListController extends Controller
{
    /**
     * ログインユーザーの月次勤怠一覧を表示する。
     *
     * @param  Request  $request  表示対象の年月を含むリクエスト
     * @return View 月次勤怠一覧画面
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m'],
        ]);

        $date = isset($validated['date'])
            ? Carbon::createFromFormat('!Y-m', $validated['date'], 'Asia/Tokyo')
            : now('Asia/Tokyo')->startOfMonth();

        return view('user.user-attendance-list', [
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $this->buildMonthlyRecords(
                $request->user(),
                $date
            ),
        ]);
    }

    /**
     * 指定ユーザーの対象月について、未打刻日を含む表示データを生成する。
     *
     * @param  User  $user  表示対象のユーザー
     * @param  Carbon  $date  対象月に属する日付
     * @return Collection<int, array<string, mixed>> 日付順の月次表示データ
     */
    private function buildMonthlyRecords(User $user, Carbon $date): Collection
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $attendanceRecords = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $startOfMonth->toDateString(),
                $endOfMonth->toDateString(),
            ])
            ->orderBy('date')
            ->get()
            ->keyBy(
                fn (Attendance $attendance): string => $attendance->date
                    ->format('Y-m-d')
            );

        $formattedAttendanceRecords = collect();

        for (
            $currentDate = $startOfMonth->copy();
            $currentDate->lte($endOfMonth);
            $currentDate->addDay()
        ) {
            $attendance = $attendanceRecords->get(
                $currentDate->format('Y-m-d')
            );

            $formattedAttendanceRecords->push(
                $this->formatDailyRecord($attendance, $currentDate)
            );
        }

        return $formattedAttendanceRecords;
    }

    /**
     * 1日分の勤怠を一覧画面用に整形する。
     *
     * @param  Attendance|null  $attendance  対象日の勤怠。未打刻日はnull
     * @param  Carbon  $date  表示対象の日付
     * @return array<string, mixed> 日付・打刻時刻・合計時間
     */
    private function formatDailyRecord(
        ?Attendance $attendance,
        Carbon $date
    ): array {
        $totalBreakSeconds = $attendance
            ? (int) $attendance->breaks
                ->filter(
                    fn (BreakTime $break): bool => $break->break_in !== null
                        && $break->break_out !== null
                )
                ->sum(
                    fn (BreakTime $break): int => (int) $break->break_in
                        ->diffInSeconds($break->break_out)
                )
            : 0;

        $totalWorkSeconds = null;

        if ($attendance && $attendance->clock_in && $attendance->clock_out) {
            $totalWorkSeconds = (int) $attendance->clock_in
                ->diffInSeconds($attendance->clock_out)
                - $totalBreakSeconds;
        }

        return [
            'id' => $attendance?->id,
            'date' => $date->isoFormat('MM/DD(ddd)'),
            'clock_in' => $attendance?->clock_in?->format('H:i') ?? '',
            'clock_out' => $attendance?->clock_out?->format('H:i') ?? '',
            'total_break_time' => $this->formatDuration(
                $attendance ? $totalBreakSeconds : null
            ),
            'total_time' => $this->formatDuration($totalWorkSeconds),
        ];
    }

    /**
     * 秒数を時間・分の表示文字列へ変換する。
     *
     * @param  int|null  $seconds  合計秒数。未確定の場合はnull
     * @return string|null HH:MM形式の時間、またはnull
     */
    private function formatDuration(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
