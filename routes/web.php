<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\AttendanceController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsGeneral;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

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

Route::view('/admin/login', 'admin.admin-login')
    ->middleware('guest:web')
    ->name('admin.login');

Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest:web', 'throttle:login'])
    ->name('admin.login.store');

Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(EnsureUserIsAdmin::class)
    ->name('admin.logout');

Route::get('/admin/attendance/list', function (Request $request) {
    $validator = Validator::make($request->query(), [
        'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
    ]);

    abort_if($validator->fails(), 400, '日付の指定が正しくありません。');

    $validated = $validator->validated();

    $date = isset($validated['date'])
        ? Carbon::createFromFormat(
            '!Y-m-d',
            $validated['date'],
            'Asia/Tokyo'
        )
        : Carbon::today('Asia/Tokyo');

    return view('admin.admin-attendance-list', [
        'date' => $date,
        'previousDay' => $date->copy()->subDay()->format('Y-m-d'),
        'nextDay' => $date->copy()->addDay()->format('Y-m-d'),

        // 認証・画面表示の確認用。勤怠一覧の実装時に置き換える。
        'users' => collect(),
        'attendanceRecords' => collect(),
    ]);
})->middleware(EnsureUserIsAdmin::class)
    ->name('admin.attendance.index');
