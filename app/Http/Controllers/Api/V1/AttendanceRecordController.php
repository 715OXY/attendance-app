<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AttendanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->input('per_page') ?? 20);

        $attendances = Attendance::query()
            ->with(['user', 'breaks'])
            ->when(
                $request->filled('user_id'),
                fn ($query) => $query->where(
                    'user_id',
                    $request->integer('user_id')
                )
            )
            ->when(
                $request->filled('date'),
                fn ($query) => $query->whereDate(
                    'date',
                    $request->input('date')
                )
            )
            ->when(
                $request->filled('month'),
                function ($query) use ($request) {
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
     * Store a newly created resource in storage.
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
     * Display the specified resource.
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
     * Update the specified resource in storage.
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
     * Remove the specified attendance record.
     */
    public function destroy(Attendance $attendanceRecord): Response
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
