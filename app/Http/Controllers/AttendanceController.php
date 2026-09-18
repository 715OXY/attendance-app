<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * 勤怠登録画面を表示する。
     */
    public function index()
    {
        $user = auth()->user();
        $now = now('Asia/Tokyo');

        /*
         * 当日の勤怠、または前日以前から継続している未退勤の勤怠を取得する。
         *
         * 日付が変わっても退勤していない場合は、
         * 前日の勤務状態を引き継ぐ仕様に対応する。
         */
        $attendance = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->where(function ($query) use ($now) {
                $query->whereDate('date', $now->toDateString())
                    ->orWhereNull('clock_out');
            })
            ->latest('date')
            ->first();

        $status = $this->determineStatus($attendance);

        /*
         * 提供Bladeでは $user->attendance_status を参照しているため、
         * DBには保存せず、画面表示用の属性として設定する。
         */
        $user->setAttribute('attendance_status', $status);

        $weekdays = ['日', '月', '火', '水', '木', '金', '土'];

        return view('user.attendance-register', [
            'user' => $user,
            'formattedDate' => $now->format('Y年n月j日')
                .'('.$weekdays[$now->dayOfWeek].')',
            'formattedTime' => $now->format('H:i'),
        ]);
    }

    /**
     * 出勤・退勤を登録する。
     */
    public function store(Request $request)
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
     */
    private function clockIn(int $userId, $now)
    {
        // 前日以前を含め、未退勤の勤怠が存在する場合は新しく出勤できない。
        $activeAttendance = AttendanceRecord::where('user_id', $userId)
            ->whereNull('clock_out')
            ->exists();

        if ($activeAttendance) {
            return redirect()
                ->route('attendance.index');
        }

        // 同じ日に再度出勤することを防止する。
        $todayAttendance = AttendanceRecord::where('user_id', $userId)
            ->whereDate('date', $now->toDateString())
            ->exists();

        if ($todayAttendance) {
            return redirect()
                ->route('attendance.index');
        }

        AttendanceRecord::create([
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
     */
    private function clockOut(int $userId, $now)
    {
        $attendance = AttendanceRecord::with('breaks')
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
            ->contains(function ($break) {
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
     */
    private function breakIn(int $userId, $now)
    {
        $attendance = AttendanceRecord::with('breaks')
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
            ->contains(function ($break) {
                return $break->break_out === null;
            });

        if ($isOnBreak) {
            return redirect()
                ->route('attendance.index');
        }

        BreakTime::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => $now,
            'break_out' => null,
        ]);

        return redirect()
            ->route('attendance.index');
    }

    /**
     * 休憩終了を登録する。
     */
    private function breakOut(int $userId, $now)
    {
        $attendance = AttendanceRecord::where('user_id', $userId)
            ->whereNull('clock_out')
            ->latest('date')
            ->first();

        if (! $attendance) {
            return redirect()
                ->route('attendance.index');
        }

        $break = BreakTime::where('attendance_record_id', $attendance->id)
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

    /**
     * 勤怠レコードから現在の勤怠ステータスを判定する。
     */
    private function determineStatus(?AttendanceRecord $attendance): string
    {
        // 当日または継続中の勤怠レコードが存在しない
        if (! $attendance) {
            return '勤務外';
        }
        // 退勤済み
        if ($attendance->clock_out !== null) {
            return '退勤済';
        }
        // 終了していない休憩が存在する
        $activeBreak = $attendance->breaks
            ->first(function ($break) {
                return $break->break_out === null;
            });
        if ($activeBreak) {
            return '休憩中';
        }

        // 出勤済み・未退勤・休憩中ではない
        return '出勤中';
    }
}
