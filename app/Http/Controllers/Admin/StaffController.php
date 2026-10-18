<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StaffMonthlyAttendanceBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class StaffController extends Controller
{
    /**
     * スタッフ一覧を表示する。
     *
     * @return View 一般ユーザーのスタッフ一覧画面
     */
    public function staffList(): View
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
     *
     * @param  Request  $request  表示対象の年月を含むリクエスト
     * @param  int  $id  表示対象のスタッフID
     * @param  StaffMonthlyAttendanceBuilder  $builder  月次データ生成処理
     * @return View 指定スタッフの月次勤怠一覧画面
     */
    public function staffAttendanceList(
        Request $request,
        int $id,
        StaffMonthlyAttendanceBuilder $builder
    ): View {
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

        $formattedAttendanceRecords = $builder->build($user, $date);

        return view('admin.staff-attendance-list', [
            'user' => $user,
            'date' => $date,
            'previousMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'formattedAttendanceRecords' => $formattedAttendanceRecords,
        ]);
    }
}
