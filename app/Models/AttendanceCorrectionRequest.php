<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function correctionBreaks()
    {
        return $this->hasMany(AttendanceCorrectionBreak::class);
    }

    /**
     * 提供Blade用の勤怠リレーション名を提供する。
     */
    public function AttendanceRecord()
    {
        return $this->attendance();
    }

    /**
     * 提供Blade用の承認状態を返す。
     */
    protected function approvalStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                0 => '承認待ち',
                1 => '承認済み',
                default => '',
            },
        );
    }

    /**
     * 提供Blade用の申請理由を返す。
     */
    protected function comment(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->requested_comment,
        );
    }

    /**
     * 提供Blade用の申請日時を返す。
     */
    protected function applicationDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at,
        );
    }

    /**
     * 提供Blade用の修正対象日を返す。
     */
    protected function newDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attendance?->date,
        );
    }

    /**
     * 提供Blade用の修正後出勤時刻を返す。
     */
    protected function newClockIn(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->requested_clock_in?->format('H:i') ?? '',
        );
    }

    /**
     * 提供Blade用の修正後退勤時刻を返す。
     */
    protected function newClockOut(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->requested_clock_out?->format('H:i') ?? '',
        );
    }

    /**
     * 提供Blade用の修正休憩リレーション名を提供する。
     */
    public function proposalBreaks()
    {
        return $this->correctionBreaks();
    }
}
