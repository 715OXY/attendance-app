<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StaffMonthlyAttendanceBuilder
{
    /**
     * スタッフの指定月について、未打刻日を含む勤怠表示データを生成する。
     *
     * @param  User  $user  対象スタッフ
     * @param  Carbon  $date  対象月に属する日付
     * @return Collection<int, array<string, mixed>> 日付順の勤怠表示データ
     */
    public function build(
        User $user,
        Carbon $date
    ): Collection {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $attendanceRecords = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $startOfMonth->format('Y-m-d'),
                $endOfMonth->format('Y-m-d'),
            ])
            ->get()
            ->keyBy(function (Attendance $attendance): string {
                return $attendance->date->format('Y-m-d');
            });

        $formattedAttendanceRecords = collect();

        $currentDate = $startOfMonth->copy();

        while ($currentDate->lte($endOfMonth)) {
            $attendanceRecord = $attendanceRecords->get(
                $currentDate->format('Y-m-d')
            );

            $hasIncompleteBreak = $attendanceRecord
                ? $attendanceRecord->breaks->contains(function (BreakTime $break): bool {
                    return $break->break_in && ! $break->break_out;
                })
                : false;

            $isIncompleteAttendance = $attendanceRecord
                && (
                    ! $attendanceRecord->clock_in
                    || ! $attendanceRecord->clock_out
                    || $hasIncompleteBreak
                );

            $formattedAttendanceRecords->push([
                'id' => $attendanceRecord?->id,
                'date' => $currentDate->isoFormat('MM/DD(ddd)'),
                'clock_in' => $attendanceRecord?->clock_in?->format('H:i') ?? '',
                'clock_out' => $attendanceRecord?->clock_out?->format('H:i') ?? '',
                'total_break_time' => $attendanceRecord && ! $isIncompleteAttendance
                    ? $attendanceRecord->total_break_time
                    : null,
                'total_time' => $attendanceRecord && ! $isIncompleteAttendance
                    ? $attendanceRecord->total_time
                    : null,
            ]);

            $currentDate->addDay();
        }

        return $formattedAttendanceRecords;
    }
}
