<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AttendanceController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsGeneral;
use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Http\Requests\AttendanceCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/attendance', [AttendanceController::class, 'index'])
    ->middleware(EnsureUserIsGeneral::class)
    ->name('attendance.index');

Route::post('/attendance', [AttendanceController::class, 'store'])
    ->middleware(EnsureUserIsGeneral::class)
    ->name('attendance.store');

Route::get('/attendance/list', [AttendanceController::class, 'list'])
    ->middleware(EnsureUserIsGeneral::class)
    ->name('attendance.list');

Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])
    ->middleware(EnsureUserIsGeneral::class)
    ->name('attendance.show');

Route::get('/attendance/{id}', function (Request $request, $id) {
    $user = $request->user('web');

    // 提供Bladeの /attendance/{id} を認証ユーザー種別に応じて振り分ける
    if (! $user) {
        return redirect()->route('login');
    }

    if ($user->admin_status) {
        return redirect()->route('admin.attendance.show', ['id' => $id]);
    }

    return redirect()->route('attendance.show', ['id' => $id]);
})
    ->name('attendance.detail.redirect');

Route::post('/attendance/{id}', function (Request $request, $id) {
    $user = $request->user('web');

    // 提供Bladeの /attendance/{id} を認証ユーザー種別に応じて振り分ける
    if (! $user) {
        return redirect()->route('login');
    }

    if ($user->admin_status) {
        return app(AdminAttendanceController::class)
            ->update(
                app(AdminAttendanceUpdateRequest::class),
                $id
            );
    }

    if (! $user->hasVerifiedEmail()) {
        return redirect()->route('verification.notice');
    }

    return app(AttendanceController::class)
        ->requestCorrection(
            app(AttendanceCorrectionRequest::class),
            $id
        );
})
    ->name('attendance.correction.store');

Route::get('/stamp_correction_request/list', function (Request $request) {
    $user = $request->user('web');

    if (! $user) {
        return redirect()->route('login');
    }

    if ($user->admin_status) {
        return app()->call([
            app(AdminAttendanceController::class),
            'applicationList',
        ]);
    }

    if (! $user->hasVerifiedEmail()) {
        return redirect()->route('verification.notice');
    }

    return app()->call([
        app(AttendanceController::class),
        'applicationList',
    ]);
})
    ->name('attendance.application.list');

Route::get('/application/{id}', [AttendanceController::class, 'applicationDetail'])
    ->middleware(EnsureUserIsGeneral::class)
    ->name('attendance.application.detail');

Route::view('/admin/login', 'admin.admin-login')
    ->middleware('guest:web')
    ->name('admin.login');

Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest:web', 'throttle:login'])
    ->name('admin.login.store');

Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(EnsureUserIsAdmin::class)
    ->name('admin.logout');

// 管理者用勤怠一覧・詳細
Route::get(
    '/admin/attendance/list',
    [AdminAttendanceController::class, 'index']
)
    ->middleware(EnsureUserIsAdmin::class)
    ->name('admin.attendance.index');

Route::get(
    '/admin/attendance/{id}',
    [AdminAttendanceController::class, 'show']
)
    ->middleware(EnsureUserIsAdmin::class)
    ->name('admin.attendance.show');

Route::get(
    '/admin/staff/list',
    [AdminAttendanceController::class, 'staffList']
)
    ->middleware(EnsureUserIsAdmin::class)
    ->name('admin.staff.index');

Route::get(
    '/admin/attendance/staff/{id}',
    [AdminAttendanceController::class, 'staffAttendanceList']
)
    ->middleware(EnsureUserIsAdmin::class)
    ->name('admin.staff.attendance.index');

Route::post(
    '/export',
    [AdminAttendanceController::class, 'export']
)
    ->middleware(EnsureUserIsAdmin::class)
    ->name('admin.staff.attendance.export');

Route::get(
    '/stamp_correction_request/approve/{attendance_correct_request_id}',
    [AdminAttendanceController::class, 'applicationDetail']
)
    ->middleware(EnsureUserIsAdmin::class)
    ->name('admin.application.show');

Route::post(
    '/stamp_correction_request/approve/{attendance_correct_request_id}',
    [AdminAttendanceController::class, 'approveApplication']
)
    ->middleware(EnsureUserIsAdmin::class)
    ->name('admin.application.approve');
