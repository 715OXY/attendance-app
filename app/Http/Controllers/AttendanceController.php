<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest as AttendanceCorrectionFormRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionBreak;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        $attendance = Attendance::with('breaks')
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
     * 月次勤怠一覧を表示する。
     */
    public function list(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m'],
        ]);

        $date = isset($validated['date'])
            ? Carbon::createFromFormat('!Y-m', $validated['date'], 'Asia/Tokyo')
            : now('Asia/Tokyo')->startOfMonth();

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
            ->keyBy(function ($attendanceRecord) {
                return $attendanceRecord->date->format('Y-m-d');
            });

        $formattedAttendanceRecords = collect();

        for (
            $currentDate = $startOfMonth->copy();
            $currentDate->lte($endOfMonth);
            $currentDate->addDay()
        ) {
            $attendanceRecord = $attendanceRecords->get(
                $currentDate->format('Y-m-d')
            );

            $totalBreakSeconds = 0;

            if ($attendanceRecord) {
                $totalBreakSeconds = $attendanceRecord->breaks
                    ->filter(function ($break) {
                        return $break->break_in && $break->break_out;
                    })
                    ->sum(function ($break) {
                        return $break->break_in->diffInSeconds(
                            $break->break_out
                        );
                    });
            }

            $totalWorkSeconds = null;

            if (
                $attendanceRecord &&
                $attendanceRecord->clock_in &&
                $attendanceRecord->clock_out
            ) {
                $totalWorkSeconds = $attendanceRecord->clock_in
                    ->diffInSeconds($attendanceRecord->clock_out)
                    - $totalBreakSeconds;
            }

            $formattedAttendanceRecords->push([
                'id' => $attendanceRecord?->id,
                'date' => $currentDate->isoFormat('MM/DD(ddd)'),
                'clock_in' => $attendanceRecord?->clock_in?->format('H:i') ?? '',
                'clock_out' => $attendanceRecord?->clock_out?->format('H:i') ?? '',
                'total_break_time' => $this->formatDuration(
                    $attendanceRecord ? $totalBreakSeconds : null
                ),
                'total_time' => $this->formatDuration($totalWorkSeconds),
            ]);
        }

        return view('user.user-attendance-list', [
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
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
     * 勤怠詳細画面を表示する。
     */
    public function show(int $id)
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
                ->map(function ($break) {
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
                ->map(function ($break) {
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
     * 勤怠修正申請を登録する。
     */
    public function requestCorrection(
        AttendanceCorrectionFormRequest $request,
        int $id
    ) {
        $user = auth()->user();

        $attendance = Attendance::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // 承認待ち申請がすでに存在する場合は重複申請させない。
        $hasPendingRequest = AttendanceCorrectionRequest::where(
            'attendance_id',
            $attendance->id
        )
            ->where('user_id', $user->id)
            ->where('status', 0)
            ->exists();

        if ($hasPendingRequest) {
            return redirect()
                ->route('attendance.show', ['id' => $attendance->id]);
        }

        $validated = $request->validated();

        $date = $attendance->date->format('Y-m-d');

        $requestedClockIn = Carbon::createFromFormat(
            'Y-m-d H:i',
            $date.' '.$validated['new_clock_in'],
            'Asia/Tokyo'
        );

        $requestedClockOut = Carbon::createFromFormat(
            'Y-m-d H:i',
            $date.' '.$validated['new_clock_out'],
            'Asia/Tokyo'
        );

        DB::transaction(function () use ($attendance, $user, $validated, $date, $requestedClockIn, $requestedClockOut) {
            $correctionRequest = AttendanceCorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'requested_clock_in' => $requestedClockIn,
                'requested_clock_out' => $requestedClockOut,
                'requested_comment' => $validated['comment'],
                'status' => 0,
            ]);

            $breakIns = $validated['new_break_in'] ?? [];
            $breakOuts = $validated['new_break_out'] ?? [];

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                // Blade末尾の追加用空欄は保存しない。
                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                AttendanceCorrectionBreak::create([
                    'attendance_correction_request_id' => $correctionRequest->id,

                    'requested_break_in' => Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $date.' '.$breakIn,
                        'Asia/Tokyo'
                    ),

                    'requested_break_out' => Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $date.' '.$breakOut,
                        'Asia/Tokyo'
                    ),
                ]);
            }
        });

        return redirect()
            ->route('attendance.show', ['id' => $attendance->id]);
    }

    /**
     * 修正申請一覧を表示する。
     */
    public function applicationList()
    {
        $user = auth()->user();

        $applications = AttendanceCorrectionRequest::with('attendance')
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get();

        $formattedApplications = $applications
            ->map(function ($application) {
                return [
                    'id' => $application->id,
                    'approval_status' => $application->status === 0
                        ? '承認待ち'
                        : '承認済み',
                    'date' => $application->attendance->date->format('Y/m/d'),
                    'comment' => $application->requested_comment,
                    'application_date' => $application->created_at->format('Y/m/d'),
                ];
            });

        return view('user.user-application-list', [
            'user' => $user,
            'formattedApplications' => $formattedApplications,
        ]);
    }

    /**
     * 修正申請から対象の勤怠詳細画面へ遷移する。
     */
    public function applicationDetail(int $id)
    {
        $user = auth()->user();

        $application = AttendanceCorrectionRequest::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return redirect()->route('attendance.show', [
            'id' => $application->attendance_id,
        ]);
    }

    /**
     * 出勤を登録する。
     */
    private function clockIn(int $userId, $now)
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
     */
    private function clockOut(int $userId, $now)
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
            ->contains(function ($break) {
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
     */
    private function breakOut(int $userId, $now)
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

    /**
     * 勤怠レコードから現在の勤怠ステータスを判定する。
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
            ->first(function ($break) {
                return $break->break_out === null;
            });
        if ($activeBreak) {
            return '休憩中';
        }

        // 出勤済み・未退勤・休憩中ではない
        return '出勤中';
    }

    /**
     * 秒数を H:i 形式へ変換する。
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
