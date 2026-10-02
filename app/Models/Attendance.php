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
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 勤怠に紐づく休憩情報を取得する。
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    /**
     * 勤怠に紐づく修正申請を取得する。
     */
    public function correctionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    /**
     * 合計休憩時間を算出する。
     */
    public function getTotalBreakTimeAttribute(): string
    {
        $totalBreakSeconds = $this->breaks->sum(function ($break) {
            if ($break->break_in && $break->break_out) {
                return Carbon::parse($break->break_in)
                    ->diffInSeconds(Carbon::parse($break->break_out));
            }

            return 0;
        });

        return gmdate('H:i:s', $totalBreakSeconds);
    }

    /**
     * 休憩時間を差し引いた実勤務時間を算出する。
     */
    public function getTotalTimeAttribute(): ?string
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return null;
        }

        $workSeconds = Carbon::parse($this->clock_in)
            ->diffInSeconds(Carbon::parse($this->clock_out));

        $breakSeconds = $this->breaks->sum(function ($break) {
            if ($break->break_in && $break->break_out) {
                return Carbon::parse($break->break_in)
                    ->diffInSeconds(Carbon::parse($break->break_out));
            }

            return 0;
        });

        $totalWorkSeconds = $workSeconds - $breakSeconds;

        return gmdate('H:i:s', $totalWorkSeconds);
    }
}
