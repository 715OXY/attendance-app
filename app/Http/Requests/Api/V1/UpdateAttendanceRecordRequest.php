<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Attendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAttendanceRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $attendance = $this->attendanceRecord();

        return $attendance !== null
            && ($this->user()?->can('update', $attendance) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $attendance = $this->attendanceRecord();

        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendances', 'date')
                    ->ignore($attendance?->id)
                    ->where(
                        fn ($query) => $query->where(
                            'user_id',
                            $attendance?->user_id
                        )
                    ),
            ],
            'clock_in' => [
                'sometimes',
                'required',
                'date_format:H:i:s',
            ],
            'clock_out' => [
                'sometimes',
                'nullable',
                'date_format:H:i:s',
            ],
            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $validator->errors()->has('clock_in')
                || $validator->errors()->has('clock_out')
            ) {
                return;
            }

            $attendance = $this->attendanceRecord();

            if (! $attendance) {
                return;
            }

            $clockIn = $this->has('clock_in')
                ? $this->input('clock_in')
                : $attendance->clock_in?->format('H:i:s');

            $clockOut = $this->has('clock_out')
                ? $this->input('clock_out')
                : $attendance->clock_out?->format('H:i:s');

            if ($clockIn === null || $clockOut === null) {
                return;
            }

            if ($clockOut <= $clockIn) {
                $validator->errors()->add(
                    'clock_out',
                    '退勤時刻は出勤時刻より後の時刻を指定してください。'
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',

            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',

            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',

            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }

    /**
     * Get the attendance record from route model binding.
     */
    private function attendanceRecord(): ?Attendance
    {
        $attendance = $this->route('attendanceRecord');

        return $attendance instanceof Attendance
            ? $attendance
            : null;
    }
}
