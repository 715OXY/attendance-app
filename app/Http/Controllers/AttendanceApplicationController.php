<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrectionRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AttendanceApplicationController extends Controller
{
    /**
     * ログインユーザーの修正申請一覧を表示する。
     *
     * @return View ユーザー情報と整形済みの修正申請一覧を含む画面
     */
    public function index(): View
    {
        $user = auth()->user();

        $applications = AttendanceCorrectionRequest::with('attendance')
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get();

        $formattedApplications = $applications
            ->map(function (AttendanceCorrectionRequest $application): array {
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
     * 自分の修正申請に対応する勤怠詳細画面へ転送する。
     *
     * @param  int  $id  修正申請ID
     * @return RedirectResponse 対応する勤怠詳細画面へのリダイレクト
     */
    public function show(int $id): RedirectResponse
    {
        $user = auth()->user();

        $application = AttendanceCorrectionRequest::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return redirect()->route('attendance.show', [
            'id' => $application->attendance_id,
        ]);
    }
}
