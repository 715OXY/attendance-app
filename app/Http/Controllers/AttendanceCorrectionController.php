<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest as AttendanceCorrectionFormRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionBreak;
use App\Models\AttendanceCorrectionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionController extends Controller
{
    /**
     * 自分の勤怠について修正申請を登録する。
     *
     * @param  AttendanceCorrectionFormRequest  $request  検証済みの修正内容
     * @param  int  $id  修正対象の勤怠ID
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function store(
        AttendanceCorrectionFormRequest $request,
        int $id
    ): RedirectResponse {
        $user = auth()->user();

        $attendance = Attendance::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

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

        DB::transaction(function () use ($attendance, $user, $validated, $date, $requestedClockIn, $requestedClockOut): void {
            $correctionRequest = AttendanceCorrectionRequest::create([
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'requested_clock_in' => $requestedClockIn,
                'requested_clock_out' => $requestedClockOut,
                'requested_comment' => $validated['comment'],
                'status' => 0,
            ]);

            $this->storeRequestedBreaks($correctionRequest, $validated, $date);
        });

        return redirect()
            ->route('attendance.show', ['id' => $attendance->id]);
    }

    /**
     * 修正申請に紐づく休憩を保存する。
     *
     * @param  AttendanceCorrectionRequest  $correctionRequest  保存先の修正申請
     * @param  array<string, mixed>  $validated  検証済みの修正内容
     * @param  string  $date  勤怠日（Y-m-d形式）
     */
    private function storeRequestedBreaks(
        AttendanceCorrectionRequest $correctionRequest,
        array $validated,
        string $date
    ): void {
        $breakIns = $validated['new_break_in'] ?? [];
        $breakOuts = $validated['new_break_out'] ?? [];

        foreach ($breakIns as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            // 提供Blade末尾の追加用空欄は保存しない。
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
    }
}
