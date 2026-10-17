<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class AttendanceApplicationController extends Controller
{
    /**
     * 全スタッフの修正申請一覧を表示する。
     *
     * @return View 全スタッフの勤怠修正申請一覧画面
     */
    public function index(): View
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
     *
     * @param  int  $attendance_correct_request_id  表示対象の修正申請ID
     * @return View 修正申請の詳細・承認画面
     */
    public function show(int $attendance_correct_request_id): View
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
     *
     * @param  int  $attendance_correct_request_id  承認対象の修正申請ID
     * @return RedirectResponse 修正申請の詳細画面へのリダイレクト
     */
    public function approve(
        int $attendance_correct_request_id
    ): RedirectResponse {
        $application = AttendanceCorrectionRequest::with([
            'attendance.breaks',
            'correctionBreaks',
        ])->findOrFail($attendance_correct_request_id);

        if ($application->status === 1) {
            return redirect()->route('admin.application.show', [
                'attendance_correct_request_id' => $application->id,
            ]);
        }

        DB::transaction(function () use ($application): void {
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
