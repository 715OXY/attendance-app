<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceController extends Controller
{
    /**
     * 管理者用の日次勤怠一覧を表示する。
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);

        abort_if(
            $validator->fails(),
            400,
            '日付の指定が正しくありません。'
        );

        $validated = $validator->validated();

        $date = isset($validated['date'])
            ? Carbon::createFromFormat(
                '!Y-m-d',
                $validated['date'],
                'Asia/Tokyo'
            )
            : Carbon::today('Asia/Tokyo');

        $attendanceRecords = Attendance::with('breaks')
            ->whereDate('date', $date->format('Y-m-d'))
            ->get();

        $users = User::whereIn(
            'id',
            $attendanceRecords->pluck('user_id')
        )->get();

        return view('admin.admin-attendance-list', [
            'date' => $date,
            'previousDay' => $date->copy()->subDay()->format('Y-m-d'),
            'nextDay' => $date->copy()->addDay()->format('Y-m-d'),
            'users' => $users,
            'attendanceRecords' => $attendanceRecords,
        ]);
    }

    /**
     * スタッフ一覧を表示する。
     */
    public function staffList()
    {
        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get();

        return view('admin.staff-list', [
            'users' => $users,
        ]);
    }

    /**
     * スタッフ別の月次勤怠一覧を表示する。
     */
    public function staffAttendanceList(Request $request, int $id)
    {
        $validator = Validator::make($request->query(), [
            'date' => ['sometimes', 'required', 'date_format:Y-m'],
        ]);

        abort_if(
            $validator->fails(),
            400,
            '年月の指定が正しくありません。'
        );

        $validated = $validator->validated();

        $date = isset($validated['date'])
            ? Carbon::createFromFormat(
                '!Y-m',
                $validated['date'],
                'Asia/Tokyo'
            )
            : now('Asia/Tokyo')->startOfMonth();

        $user = User::where('admin_status', false)
            ->findOrFail($id);

        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();

        $attendanceRecords = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $startOfMonth->format('Y-m-d'),
                $endOfMonth->format('Y-m-d'),
            ])
            ->get()
            ->keyBy(function ($attendance) {
                return $attendance->date->format('Y-m-d');
            });

        $formattedAttendanceRecords = collect();

        $currentDate = $startOfMonth->copy();

        while ($currentDate->lte($endOfMonth)) {
            $attendanceRecord = $attendanceRecords->get(
                $currentDate->format('Y-m-d')
            );

            $hasIncompleteBreak = $attendanceRecord
                ? $attendanceRecord->breaks->contains(function ($break) {
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

        return view('admin.staff-attendance-list', [
            'user' => $user,
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
        ]);
    }

    /**
     * 管理者用の勤怠詳細画面を表示する。
     */
    public function show(Request $request, int $id)
    {
        $attendance = Attendance::with([
            'user',
            'breaks',
            'correctionRequests',
        ])->findOrFail($id);

        $application = $attendance->correctionRequests
            ->where('status', 0)
            ->sortByDesc('id')
            ->first();

        $breaks = $attendance->breaks
            ->map(function ($break) {
                return [
                    'break_in' => $break->break_in?->format('H:i') ?? '',
                    'break_out' => $break->break_out?->format('H:i') ?? '',
                ];
            })
            ->values()
            ->toArray();

        if (
            $request->hasSession()
            && $request->session()->hasOldInput('new_break_in')
        ) {
            $oldBreakIns = $request->old('new_break_in', []);
            $oldBreakOuts = $request->old('new_break_out', []);

            $breaks = [];

            $count = max(
                count($oldBreakIns),
                count($oldBreakOuts)
            );

            for ($index = 0; $index < $count; $index++) {
                $breaks[] = [
                    'break_in' => $oldBreakIns[$index] ?? '',
                    'break_out' => $oldBreakOuts[$index] ?? '',
                ];
            }
        }

        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => $attendance->date->format('Y年'),
            'date' => $attendance->date->format('m月d日'),
            'clock_in' => $request->old(
                'new_clock_in',
                $attendance->clock_in?->format('H:i') ?? ''
            ),
            'clock_out' => $request->old(
                'new_clock_out',
                $attendance->clock_out?->format('H:i') ?? ''
            ),
            'breaks' => $breaks,
            'comment' => $request->old(
                'comment',
                $attendance->comment ?? ''
            ),
            'application' => $application,
        ];

        return view('admin.admin-detail', [
            'user' => $attendance->user,
            'attendanceRecord' => $attendanceRecord,
        ]);
    }

    /**
     * 管理者による勤怠情報の直接修正を行う。
     */
    public function update(AdminAttendanceUpdateRequest $request, int $id)
    {
        $attendance = Attendance::with([
            'breaks',
            'correctionRequests',
        ])->findOrFail($id);

        $hasPendingRequest = $attendance->correctionRequests
            ->where('status', 0)
            ->isNotEmpty();

        if ($hasPendingRequest) {
            return back()
                ->withInput()
                ->withErrors([
                    'new_clock_in' => '承認待ちのため修正はできません。',
                ]);
        }

        $validated = $request->validated();

        $date = $attendance->date->format('Y-m-d');

        DB::transaction(function () use ($attendance, $validated, $date) {
            $attendance->update([
                'clock_in' => $date.' '.$validated['new_clock_in'].':00',
                'clock_out' => $date.' '.$validated['new_clock_out'].':00',
                'comment' => $validated['comment'] ?? null,
            ]);

            $attendance->breaks()->delete();

            $breakIns = $validated['new_break_in'] ?? [];
            $breakOuts = $validated['new_break_out'] ?? [];

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                $attendance->breaks()->create([
                    'break_in' => $date.' '.$breakIn.':00',
                    'break_out' => $date.' '.$breakOut.':00',
                ]);
            }
        });

        return redirect()
            ->route('admin.attendance.show', ['id' => $attendance->id]);
    }

    /**
     * 全スタッフの修正申請一覧を表示する。
     */
    public function applicationList()
    {
        $applications = AttendanceCorrectionRequest::with([
            'user',
            'AttendanceRecord',
        ])
            ->latest('created_at')
            ->get();

        return view('admin.admin-application-list', [
            'applications' => $applications,
        ]);
    }

    /**
     * 修正申請の詳細を表示する。
     */
    public function applicationDetail(int $attendance_correct_request_id)
    {
        $application = AttendanceCorrectionRequest::with([
            'user',
            'attendance',
            'proposalBreaks',
        ])->findOrFail($attendance_correct_request_id);

        return view('admin.admin-application-detail', [
            'application' => $application,
            'user' => $application->user,
        ]);
    }

    /**
     * 修正申請を承認し、正式な勤怠情報へ反映する。
     */
    public function approveApplication(int $attendance_correct_request_id)
    {
        $application = AttendanceCorrectionRequest::with([
            'attendance.breaks',
            'correctionBreaks',
        ])->findOrFail($attendance_correct_request_id);

        if ($application->status === 1) {
            return redirect()->route('admin.application.show', [
                'attendance_correct_request_id' => $application->id,
            ]);
        }

        DB::transaction(function () use ($application) {
            $attendance = $application->attendance;

            $attendance->update([
                'clock_in' => $application->requested_clock_in,
                'clock_out' => $application->requested_clock_out,
                'comment' => $application->requested_comment,
            ]);

            $attendance->breaks()->delete();

            foreach ($application->correctionBreaks as $correctionBreak) {
                $attendance->breaks()->create([
                    'break_in' => $correctionBreak->requested_break_in,
                    'break_out' => $correctionBreak->requested_break_out,
                ]);
            }

            $application->update([
                'status' => 1,
            ]);
        });

        return redirect()->route('admin.application.show', [
            'attendance_correct_request_id' => $application->id,
        ]);
    }
}
