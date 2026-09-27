<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correction_request_id',
        'requested_break_in',
        'requested_break_out',
    ];

    protected $casts = [
        'requested_break_in' => 'datetime',
        'requested_break_out' => 'datetime',
    ];

    public function attendanceCorrectionRequest()
    {
        return $this->belongsTo(AttendanceCorrectionRequest::class);
    }

    /**
     * 提供Blade用の休憩開始時刻を返す。
     */
    protected function breakIn(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->requested_break_in,
        );
    }

    /**
     * 提供Blade用の休憩終了時刻を返す。
     */
    protected function breakOut(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->requested_break_out,
        );
    }
}
