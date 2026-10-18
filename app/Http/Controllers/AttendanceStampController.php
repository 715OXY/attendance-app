<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceStampController extends Controller
{
    /**
     * 出勤・休憩入・休憩戻・退勤の打刻処理を行う。
     *
     * @param  Request  $request  打刻アクションを含むリクエスト
     * @return RedirectResponse 勤怠登録画面へのリダイレクト
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => [
                'required',
                'in:clock_in,clock_out,break_in,break_out',
            ],
        ]);

        $user = auth()->user();
        $now = now('Asia/Tokyo');

        return match ($validated['action']) {
            'clock_in' => $this->clockIn($user->id, $now),
            'clock_out' => $this->clockOut($user->id, $now),
            'break_in' => $this->breakIn($user->id, $now),
            'break_out' => $this->breakOut($user->id, $now),
        };
    }

    /**
     * 出勤を登録する。
     *
     * @param  int  $userId  打刻するユーザーID
     * @param  Carbon  $now  打刻日時
     * @return RedirectResponse 勤怠登録画面へのリダイレクト
     */
    private function clockIn(int $userId, Carbon $now): RedirectResponse
    {
        // 前日以前を含め、未退勤の勤怠が存在する場合は新しく出勤できない。
        $activeAttendance = Attendance::where('user_id', $userId)
            ->whereNull('clock_out')
            ->exists();

        if ($activeAttendance) {
            return redirect()
                ->route('attendance.index');
        }

        // 同じ日に再度出勤することを防止する。
        $todayAttendance = Attendance::where('user_id', $userId)
            ->whereDate('date', $now->toDateString())
            ->exists();

        if ($todayAttendance) {
            return redirect()
                ->route('attendance.index');
        }

        Attendance::create([
            'user_id' => $userId,
            'date' => $now->toDateString(),
            'clock_in' => $now,
            'clock_out' => null,
            'comment' => null,
        ]);

        return redirect()
            ->route('attendance.index');
    }

    /**
     * 退勤を登録する。
     *
     * @param  int  $userId  打刻するユーザーID
     * @param  Carbon  $now  打刻日時
     * @return RedirectResponse 勤怠登録画面へのリダイレクト
     */
    private function clockOut(int $userId, Carbon $now): RedirectResponse
    {
        $attendance = Attendance::with('breaks')
            ->where('user_id', $userId)
            ->whereNull('clock_out')
            ->latest('date')
            ->first();

        if (! $attendance) {
            return redirect()
                ->route('attendance.index');
        }

        // 休憩中は退勤できない。
        $isOnBreak = $attendance->breaks
            ->contains(function (BreakTime $break): bool {
                return $break->break_out === null;
            });

        if ($isOnBreak) {
            return redirect()
                ->route('attendance.index');
        }

        $attendance->update([
            'clock_out' => $now,
        ]);

        return redirect()
            ->route('attendance.index');
    }

    /**
     * 休憩開始を登録する。
     *
     * @param  int  $userId  打刻するユーザーID
     * @param  Carbon  $now  打刻日時
     * @return RedirectResponse 勤怠登録画面へのリダイレクト
     */
    private function breakIn(int $userId, Carbon $now): RedirectResponse
    {
        $attendance = Attendance::with('breaks')
            ->where('user_id', $userId)
            ->whereNull('clock_out')
            ->latest('date')
            ->first();

        // 出勤していなければ休憩開始できない。
        if (! $attendance) {
            return redirect()
                ->route('attendance.index');
        }

        // すでに休憩中なら重複して休憩開始できない。
        $isOnBreak = $attendance->breaks
            ->contains(function (BreakTime $break): bool {
                return $break->break_out === null;
            });

        if ($isOnBreak) {
            return redirect()
                ->route('attendance.index');
        }

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'break_in' => $now,
            'break_out' => null,
        ]);

        return redirect()
            ->route('attendance.index');
    }

    /**
     * 休憩終了を登録する。
     *
     * @param  int  $userId  打刻するユーザーID
     * @param  Carbon  $now  打刻日時
     * @return RedirectResponse 勤怠登録画面へのリダイレクト
     */
    private function breakOut(int $userId, Carbon $now): RedirectResponse
    {
        $attendance = Attendance::where('user_id', $userId)
            ->whereNull('clock_out')
            ->latest('date')
            ->first();

        if (! $attendance) {
            return redirect()
                ->route('attendance.index');
        }

        $break = BreakTime::where('attendance_id', $attendance->id)
            ->whereNull('break_out')
            ->latest('id')
            ->first();

        // 休憩中でなければ休憩終了できない。
        if (! $break) {
            return redirect()
                ->route('attendance.index');
        }

        $break->update([
            'break_out' => $now,
        ]);

        return redirect()
            ->route('attendance.index');
    }
}
