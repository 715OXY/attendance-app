<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AttendanceRecordController extends Controller
{
    /**
     * 検索条件に一致する勤怠をページ単位で取得する。
     *
     * @param  IndexAttendanceRecordRequest  $request  検証済みの検索条件
     * @return AnonymousResourceCollection ページ情報を含む勤怠一覧
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->input('per_page') ?? 20);

        $attendances = Attendance::query()
            ->with(['user', 'breaks'])
            ->when(
                $request->filled('user_id'),
                fn (Builder $query): Builder => $query->where(
                    'user_id',
                    $request->integer('user_id')
                )
            )
            ->when(
                $request->filled('date'),
                fn (Builder $query): Builder => $query->whereDate(
                    'date',
                    $request->input('date')
                )
            )
            ->when(
                $request->filled('month'),
                function (Builder $query) use ($request): void {
                    [$year, $month] = explode(
                        '-',
                        $request->input('month')
                    );

                    $query
                        ->whereYear('date', $year)
                        ->whereMonth('date', $month);
                }
            )
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return AttendanceRecordResource::collection($attendances);
    }

    /**
     * 認証ユーザーの勤怠を新規登録する。
     *
     * @param  StoreAttendanceRecordRequest  $request  検証済みの登録内容
     * @return JsonResponse 登録した勤怠とHTTP 201のレスポンス
     */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $attendance = $request->user()
            ->attendanceRecords()
            ->create([
                'date' => $validated['date'],
                'clock_in' => $validated['date'].' '.$validated['clock_in'],
                'clock_out' => isset($validated['clock_out'])
                    ? $validated['date'].' '.$validated['clock_out']
                    : null,
                'comment' => $validated['comment'] ?? null,
            ]);

        $attendance->load([
            'user',
            'breaks',
        ]);

        return (new AttendanceRecordResource($attendance))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 関連情報を含む勤怠詳細を返す。
     *
     * @param  Attendance  $attendanceRecord  表示対象の勤怠
     * @return AttendanceRecordResource 勤怠詳細のAPIリソース
     */
    public function show(Attendance $attendanceRecord): AttendanceRecordResource
    {
        $attendanceRecord->load([
            'user',
            'breaks',
            'correctionRequests.correctionBreaks',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 未送信の項目を保持し、送信された項目で勤怠を更新する。
     *
     * @param  UpdateAttendanceRecordRequest  $request  認可・検証済みの更新内容
     * @param  Attendance  $attendanceRecord  更新対象の勤怠
     * @return AttendanceRecordResource 更新後の勤怠リソース
     */
    public function update(
        UpdateAttendanceRecordRequest $request,
        Attendance $attendanceRecord
    ): AttendanceRecordResource {
        $validated = $request->validated();

        $currentDate = $attendanceRecord->date->format('Y-m-d');

        $effectiveDate = $validated['date']
            ?? $currentDate;

        $updates = [];

        if (array_key_exists('date', $validated)) {
            $updates['date'] = $validated['date'];

            if (
                ! array_key_exists('clock_in', $validated)
                && $attendanceRecord->clock_in
            ) {
                $updates['clock_in'] = $effectiveDate.' '
                    .$attendanceRecord->clock_in->format('H:i:s');
            }

            if (
                ! array_key_exists('clock_out', $validated)
                && $attendanceRecord->clock_out
            ) {
                $updates['clock_out'] = $effectiveDate.' '
                    .$attendanceRecord->clock_out->format('H:i:s');
            }
        }

        if (array_key_exists('clock_in', $validated)) {
            $updates['clock_in'] = $effectiveDate.' '
                .$validated['clock_in'];
        }

        if (array_key_exists('clock_out', $validated)) {
            $updates['clock_out'] = $validated['clock_out'] !== null
                ? $effectiveDate.' '.$validated['clock_out']
                : null;
        }

        if (array_key_exists('comment', $validated)) {
            $updates['comment'] = $validated['comment'];
        }

        $attendanceRecord->update($updates);

        $attendanceRecord->load([
            'user',
            'breaks',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 認可されたユーザーによる勤怠削除を行う。
     *
     * @param  Attendance  $attendanceRecord  削除対象の勤怠
     * @return Response HTTP 204のレスポンス
     */
    public function destroy(Attendance $attendanceRecord): Response
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
