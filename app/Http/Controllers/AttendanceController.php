<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;

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
