<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionBreak;
use App\Models\BreakTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class AttendanceController extends Controller
{
    /**
     * 一般ユーザー用の勤怠登録画面を表示する。
     *
     * @return View 現在の勤怠状態を含む登録画面
     */
    public function index(): View
    {
        $user = auth()->user();
        $now = now('Asia/Tokyo');

        /*
         * 当日の勤怠、または前日以前から継続している未退勤の勤怠を取得する。
         *
         * 日付が変わっても退勤していない場合は、
         * 前日の勤務状態を引き継ぐ仕様に対応する。
         */
        $attendance = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->where(function (Builder $query) use ($now): void {
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
     * 一般ユーザー用の勤怠詳細画面を表示する。
     *
     * @param  int  $id  表示対象の勤怠ID
     * @return View 本人の勤怠詳細画面
     */
    public function show(int $id): View
    {
        $user = auth()->user();

        $attendance = Attendance::with([
            'breaks',
            'correctionRequests.correctionBreaks',
        ])
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $application = $attendance->correctionRequests
            ->where('status', 0)
            ->sortByDesc('id')
            ->first();

        /*
         * 承認待ち申請がある場合は申請内容を表示し、
         * 無い場合は現在の正式な勤怠情報を表示する。
         */
        if ($application) {
            $clockIn = $application->requested_clock_in;
            $clockOut = $application->requested_clock_out;
            $comment = $application->requested_comment;

            $breaks = $application->correctionBreaks
                ->map(function (AttendanceCorrectionBreak $break): array {
                    return [
                        'break_in' => $break->requested_break_in->format('H:i'),
                        'break_out' => $break->requested_break_out->format('H:i'),
                    ];
                })
                ->values()
                ->toArray();
        } else {
            $clockIn = $attendance->clock_in;
            $clockOut = $attendance->clock_out;
            $comment = $attendance->comment;

            $breaks = $attendance->breaks
                ->map(function (BreakTime $break): array {
                    return [
                        'break_in' => $break->break_in?->format('H:i') ?? '',
                        'break_out' => $break->break_out?->format('H:i') ?? '',
                    ];
                })
                ->values()
                ->toArray();
        }

        $data = [
            'id' => $attendance->id,
            'year' => $attendance->date->format('Y年'),
            'date' => $attendance->date->format('m月d日'),
            'clock_in' => $clockIn?->format('H:i') ?? '',
            'clock_out' => $clockOut?->format('H:i') ?? '',
            'breaks' => $breaks,
            'comment' => $comment ?? '',
            'application' => $application,
        ];

        return view('user.user-detail', [
            'user' => $user,
            'data' => $data,
        ]);
    }

    /**
     * 勤怠レコードから現在の勤怠ステータスを判定する。
     *
     * @param  Attendance|null  $attendance  当日または継続中の勤怠
     * @return string 画面表示用の勤怠状態
     */
    private function determineStatus(?Attendance $attendance): string
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
            ->first(function (BreakTime $break): bool {
                return $break->break_out === null;
            });
        if ($activeBreak) {
            return '休憩中';
        }

        // 出勤済み・未退勤・休憩中ではない
        return '出勤中';
    }
}
