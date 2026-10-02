<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminAttendanceUpdateRequest extends FormRequest
{
    /**
     * リクエストの認可可否を判定する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 管理者による勤怠修正のバリデーションルールを定義する。
     */
    public function rules(): array
    {
        return [
            'new_date' => [
                'required',
                'date_format:m月d日',
            ],
            'new_clock_in' => [
                'required',
                'date_format:H:i',
            ],
            'new_clock_out' => [
                'required',
                'date_format:H:i',
                'after:new_clock_in',
            ],
            'new_break_in' => [
                'nullable',
                'array',
            ],
            'new_break_in.*' => [
                'nullable',
                'date_format:H:i',
            ],
            'new_break_out' => [
                'nullable',
                'array',
            ],
            'new_break_out.*' => [
                'nullable',
                'date_format:H:i',
            ],
            'comment' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * バリデーションメッセージを定義する。
     */
    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_in.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',

            'new_break_in.*.date_format' => '休憩時間が不適切な値です',
            'new_break_out.*.date_format' => '休憩時間もしくは退勤時間が不適切な値です',

            'comment.required' => '備考を記入してください',
            'comment.max' => '備考は255文字以内で入力してください',
        ];
    }

    /**
     * 出退勤時刻と休憩時刻の整合性を追加検証する。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $clockIn = $this->input('new_clock_in');
            $clockOut = $this->input('new_clock_out');

            $breakIns = $this->input('new_break_in', []);
            $breakOuts = $this->input('new_break_out', []);

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                if (blank($breakIn) || blank($breakOut)) {
                    $validator->errors()->add(
                        "new_break_in.$index",
                        '休憩時間が不適切な値です'
                    );

                    continue;
                }

                if ($breakIn < $clockIn || $breakIn >= $clockOut) {
                    $validator->errors()->add(
                        "new_break_in.$index",
                        '休憩時間が不適切な値です'
                    );
                }

                if ($breakOut <= $breakIn || $breakOut > $clockOut) {
                    $validator->errors()->add(
                        "new_break_out.$index",
                        '休憩時間もしくは退勤時間が不適切な値です'
                    );
                }
            }
        });
    }
}
