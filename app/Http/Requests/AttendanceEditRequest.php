<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class AttendanceEditRequest extends FormRequest
{
    /**
     * この共通Requestでは追加の認可判定を行わない。
     *
     * @return bool 常にtrue。アクセス制御は呼び出し元で行う
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 勤怠修正画面の入力ルールを定義する。
     *
     * @return array<string, array<int, string>> 項目ごとの検証ルール
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
     * 勤怠修正画面のエラーメッセージを定義する。
     *
     * @return array<string, string> 検証ルールに対応する日本語メッセージ
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
     * 基本検証の成功後に、勤務時間と休憩時間の整合性を検証する。
     *
     * @param  Validator  $validator  追加検証を登録するバリデータ
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $clockIn = $this->input('new_clock_in');
            $clockOut = $this->input('new_clock_out');

            $breakIns = $this->input('new_break_in', []);
            $breakOuts = $this->input('new_break_out', []);

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? null;

                // 追加用の空欄は検証対象外。
                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                // 片方だけ入力された休憩は不正。
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
