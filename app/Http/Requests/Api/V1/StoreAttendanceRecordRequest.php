<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRecordRequest extends FormRequest
{
    /**
     * このRequestでは追加の認可判定を行わない。
     *
     * @return bool 常にtrue。認証はルート側で行う
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 勤怠の新規登録に使用する検証ルールを定義する。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendances', 'date')
                    ->where(
                        fn (Builder $query): Builder => $query->where(
                            'user_id',
                            $this->user()?->id
                        )
                    ),
            ],
            'clock_in' => [
                'required',
                'date_format:H:i:s',
            ],
            'clock_out' => [
                'nullable',
                'date_format:H:i:s',
                'after:clock_in',
            ],
            'comment' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * 入力検証に使用する日本語メッセージを定義する。
     *
     * @return array<string, string> 検証ルールに対応するエラーメッセージ
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
            'clock_out.after' => '退勤時刻は出勤時刻より後の時刻を指定してください。',

            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
