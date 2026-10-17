<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const REPORT_MONTH_COUNT = 6;

    private const MINUTES_PER_HOUR = 60;

    private const STANDARD_WORK_MINUTES = 8 * self::MINUTES_PER_HOUR;

    private const LONG_WORK_MINUTES = 10 * self::MINUTES_PER_HOUR;

    private const SHIFT_START = '09:00:00';

    private const SHIFT_END = '18:00:00';

    /**
     * ログインユーザーのマイ勤怠レポートを表示する。
     *
     * @param  Request  $request  認証ユーザーを含むリクエスト
     * @return View 集計結果を含むレポート画面
     */
    public function index(Request $request): View
    {
        $endMonth = now()->subMonthNoOverflow()->endOfMonth();
        $startMonth = $endMonth->copy()
            ->subMonths(self::REPORT_MONTH_COUNT - 1)
            ->startOfMonth();

        $attendances = Attendance::with('breaks')
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [
                $startMonth->toDateString(),
                $endMonth->toDateString(),
            ])
            ->orderBy('date')
            ->get();

        $calculatedAttendances = $this->calculateAttendances($attendances);
        $totalWorkMinutes = $calculatedAttendances->sum('work_minutes');

        $summary = [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $calculatedAttendances
                ->sum('overtime_minutes'),
            'avg_work_minutes' => $calculatedAttendances->isEmpty()
                ? 0
                : intdiv($totalWorkMinutes, $calculatedAttendances->count()),
        ];

        $monthlyTrend = $this->buildMonthlyTrend(
            $calculatedAttendances,
            $startMonth
        );

        $anomalies = $this->buildAnomalies(
            $calculatedAttendances,
            $endMonth->format('Y-m')
        );

        return view('reports.index', compact(
            'summary',
            'monthlyTrend',
            'anomalies'
        ));
    }

    /**
     * 出退勤済みの勤怠について、勤務時間と残業時間を分単位で算出する。
     *
     * @param  Collection<int, Attendance>  $attendances  対象期間の勤怠
     * @return Collection<int, array<string, mixed>> 計算済みの勤怠データ
     */
    private function calculateAttendances(Collection $attendances): Collection
    {
        return $attendances
            ->filter(
                fn (Attendance $attendance): bool => $attendance->clock_in !== null
                    && $attendance->clock_out !== null
            )
            ->map(function (Attendance $attendance): array {
                $workMinutes = $this->timeToMinutes($attendance->total_time);

                return [
                    'attendance' => $attendance,
                    'work_minutes' => $workMinutes,
                    'overtime_minutes' => max(
                        $workMinutes - self::STANDARD_WORK_MINUTES,
                        0
                    ),
                ];
            });
    }

    /**
     * 勤怠のない月を含め、対象期間の月別集計を生成する。
     *
     * @param  Collection<int, array<string, mixed>>  $records  計算済み勤怠
     * @param  Carbon  $startMonth  集計開始月
     * @return Collection<int, array<string, mixed>> 月別の勤務・残業時間
     */
    private function buildMonthlyTrend(
        Collection $records,
        Carbon $startMonth
    ): Collection {
        return collect(range(0, self::REPORT_MONTH_COUNT - 1))
            ->map(function (int $offset) use ($startMonth, $records): array {
                $month = $startMonth->copy()->addMonths($offset);
                $monthKey = $month->format('Y-m');

                $monthlyRecords = $records->filter(
                    fn (array $row): bool => $row['attendance']->date
                        ->format('Y-m') === $monthKey
                );

                return [
                    'month' => $month->format('Y/m'),
                    'work_minutes' => $monthlyRecords->sum('work_minutes'),
                    'overtime_minutes' => $monthlyRecords->sum('overtime_minutes'),
                ];
            });
    }

    /**
     * 集計対象の最終月について、遅刻・早退・長時間労働を集計する。
     *
     * @param  Collection<int, array<string, mixed>>  $records  計算済み勤怠
     * @param  string  $monthKey  集計対象月（Y-m形式）
     * @return array<string, int> 各判定に該当する勤怠の件数
     */
    private function buildAnomalies(Collection $records, string $monthKey): array
    {
        $monthlyRecords = $records->filter(
            fn (array $row): bool => $row['attendance']->date
                ->format('Y-m') === $monthKey
        );

        return [
            'late_count' => $monthlyRecords->filter(
                fn (array $row): bool => $row['attendance']->clock_in
                    ->format('H:i:s') > self::SHIFT_START
            )->count(),
            'early_leave_count' => $monthlyRecords->filter(
                fn (array $row): bool => $row['attendance']->clock_out
                    ->format('H:i:s') < self::SHIFT_END
            )->count(),
            'long_work_count' => $monthlyRecords->filter(
                fn (array $row): bool => $row['work_minutes']
                    > self::LONG_WORK_MINUTES
            )->count(),
        ];
    }

    /**
     * HH:MM:SS形式の時間を分へ変換する。
     *
     * @param  string|null  $time  変換対象の時間
     * @return int 分単位の時間。nullの場合は0
     */
    private function timeToMinutes(?string $time): int
    {
        if ($time === null) {
            return 0;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * self::MINUTES_PER_HOUR) + $minutes;
    }
}
