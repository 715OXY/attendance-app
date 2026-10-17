<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
    ];

    /**
     * 勤怠に紐づくユーザーを取得する。
     *
     * @return BelongsTo 勤怠の所有ユーザーとのリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 勤怠に紐づく休憩情報を取得する。
     *
     * @return HasMany 休憩記録とのリレーション
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    /**
     * 勤怠に紐づく修正申請を取得する。
     *
     * @return HasMany 勤怠修正申請とのリレーション
     */
    public function correctionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    /**
     * 終了済みの休憩時間を合計する。
     *
     * @return string HH:MM:SS形式の合計休憩時間
     */
    public function getTotalBreakTimeAttribute(): string
    {
        return gmdate('H:i:s', $this->calculateTotalBreakSeconds());
    }

    /**
     * 休憩時間を差し引いた実勤務時間を算出する。
     *
     * @return string|null HH:MM:SS形式の勤務時間。出退勤が未完了ならnull
     */
    public function getTotalTimeAttribute(): ?string
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return null;
        }

        $workSeconds = Carbon::parse($this->clock_in)
            ->diffInSeconds(Carbon::parse($this->clock_out));

        $totalWorkSeconds = $workSeconds - $this->calculateTotalBreakSeconds();

        return gmdate('H:i:s', $totalWorkSeconds);
    }

    /**
     * 開始・終了の両時刻がある休憩について、合計秒数を算出する。
     *
     * @return int 終了済み休憩の合計秒数
     */
    private function calculateTotalBreakSeconds(): int
    {
        return (int) $this->breaks->sum(function (BreakTime $break): int {
            if (! $break->break_in || ! $break->break_out) {
                return 0;
            }

            return (int) Carbon::parse($break->break_in)
                ->diffInSeconds(Carbon::parse($break->break_out));
        });
    }
}
