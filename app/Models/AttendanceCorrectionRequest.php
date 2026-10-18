<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'user_id',
        'requested_clock_in',
        'requested_clock_out',
        'requested_comment',
        'status',
    ];

    protected $casts = [
        'requested_clock_in' => 'datetime',
        'requested_clock_out' => 'datetime',
        'status' => 'integer',
    ];

    /**
     * 修正申請に紐づく勤怠情報を取得する。
     *
     * @return BelongsTo 修正対象の勤怠とのリレーション
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    /**
     * 修正申請を行ったユーザーを取得する。
     *
     * @return BelongsTo 申請ユーザーとのリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 修正申請に紐づく休憩修正情報を取得する。
     *
     * @return HasMany 休憩修正情報とのリレーション
     */
    public function correctionBreaks(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionBreak::class);
    }

    /**
     * 提供Blade用の勤怠リレーション名を提供する。
     *
     * @return BelongsTo 修正対象の勤怠とのリレーション
     */
    public function AttendanceRecord(): BelongsTo
    {
        return $this->attendance();
    }

    /**
     * 提供Blade用の承認状態を返す。
     *
     * @return Attribute 提供Blade用の属性定義
     */
    protected function approvalStatus(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->status) {
                0 => '承認待ち',
                1 => '承認済み',
                default => '',
            },
        );
    }

    /**
     * 提供Blade用の申請理由を返す。
     *
     * @return Attribute 提供Blade用の属性定義
     */
    protected function comment(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->requested_comment,
        );
    }

    /**
     * 提供Blade用の申請日時を返す。
     *
     * @return Attribute 提供Blade用の属性定義
     */
    protected function applicationDate(): Attribute
    {
        return Attribute::make(
            get: fn (): ?CarbonInterface => $this->created_at,
        );
    }

    /**
     * 提供Blade用の修正対象日を返す。
     *
     * @return Attribute 提供Blade用の属性定義
     */
    protected function newDate(): Attribute
    {
        return Attribute::make(
            get: fn (): ?CarbonInterface => $this->attendance?->date,
        );
    }

    /**
     * 提供Blade用の修正後出勤時刻を返す。
     *
     * @return Attribute 提供Blade用の属性定義
     */
    protected function newClockIn(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->requested_clock_in?->format('H:i') ?? '',
        );
    }

    /**
     * 提供Blade用の修正後退勤時刻を返す。
     *
     * @return Attribute 提供Blade用の属性定義
     */
    protected function newClockOut(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->requested_clock_out?->format('H:i') ?? '',
        );
    }

    /**
     * 提供Blade用の修正休憩リレーション名を提供する。
     *
     * @return HasMany 休憩修正情報とのリレーション
     */
    public function proposalBreaks(): HasMany
    {
        return $this->correctionBreaks();
    }
}
