<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * ログインユーザーのマイ勤怠レポートを表示する。
     */
    public function index(Request $request): View
    {
        $endMonth = now()
            ->subMonthNoOverflow()
            ->endOfMonth();

        $startMonth = $endMonth
            ->copy()
            ->subMonths(5)
            ->startOfMonth();

        $attendances = Attendance::with('breaks')
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [
                $startMonth->toDateString(),
                $endMonth->toDateString(),
            ])
            ->orderBy('date')
            ->get();

        $calculatedAttendances = $attendances
            ->filter(
                fn (Attendance $attendance): bool => $attendance->clock_in !== null
                    && $attendance->clock_out !== null
            )
            ->map(function (Attendance $attendance): array {
                $workMinutes = $this->timeToMinutes(
                    $attendance->total_time
                );

                return [
                    'attendance' => $attendance,
                    'work_minutes' => $workMinutes,
                    'overtime_minutes' => max(
                        $workMinutes - (8 * 60),
                        0
                    ),
                ];
            });

        $totalWorkMinutes = $calculatedAttendances
            ->sum('work_minutes');

        $totalOvertimeMinutes = $calculatedAttendances
            ->sum('overtime_minutes');

        $summary = [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'avg_work_minutes' => $calculatedAttendances->isEmpty()
                ? 0
                : intdiv(
                    $totalWorkMinutes,
                    $calculatedAttendances->count()
                ),
        ];

        $monthlyTrend = collect(range(0, 5))
            ->map(function (int $offset) use ($startMonth, $calculatedAttendances): array {
                $month = $startMonth
                    ->copy()
                    ->addMonths($offset);

                $monthKey = $month->format('Y-m');

                $monthlyAttendances = $calculatedAttendances
                    ->filter(
                        fn (array $row): bool => $row['attendance']
                            ->date
                            ->format('Y-m') === $monthKey
                    );

                return [
                    'month' => $month->format('Y/m'),
                    'work_minutes' => $monthlyAttendances
                        ->sum('work_minutes'),
                    'overtime_minutes' => $monthlyAttendances
                        ->sum('overtime_minutes'),
                ];
            });

        $latestMonthKey = $endMonth->format('Y-m');

        $latestMonthAttendances = $calculatedAttendances
            ->filter(
                fn (array $row): bool => $row['attendance']
                    ->date
                    ->format('Y-m') === $latestMonthKey
            );

        $anomalies = [
            'late_count' => $latestMonthAttendances
                ->filter(
                    fn (array $row): bool => $row['attendance']
                        ->clock_in
                        ->format('H:i:s') > '09:00:00'
                )
                ->count(),

            'early_leave_count' => $latestMonthAttendances
                ->filter(
                    fn (array $row): bool => $row['attendance']
                        ->clock_out
                        ->format('H:i:s') < '18:00:00'
                )
                ->count(),

            'long_work_count' => $latestMonthAttendances
                ->filter(
                    fn (array $row): bool => $row['work_minutes'] > (10 * 60)
                )
                ->count(),
        ];

        return view(
            'reports.index',
            compact(
                'summary',
                'monthlyTrend',
                'anomalies'
            )
        );
    }

    /**
     * HH:MM:SS形式の時間を分へ変換する。
     */
    private function timeToMinutes(?string $time): int
    {
        if ($time === null) {
            return 0;
        }

        [$hours, $minutes] = array_map(
            'intval',
            explode(':', $time)
        );

        return ($hours * 60) + $minutes;
    }
}
