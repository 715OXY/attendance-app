<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\BreakTime;
use Tests\TestCase;

class AttendanceTotalTimeTest extends TestCase
{
    /**
     * @test
     */
    public function 正常系_休憩がない場合に出勤から退勤までの時間を勤務時間として計算できる(): void
    {
        // Arrange
        $attendance = new Attendance([
            'clock_in' => '2026-09-28 09:00:00',
            'clock_out' => '2026-09-28 18:00:00',
        ]);

        $attendance->setRelation('breaks', collect());

        // Act
        $totalTime = $attendance->total_time;

        // Assert
        $this->assertSame('09:00:00', $totalTime);
    }

    /**
     * @test
     */
    public function 正常系_休憩時間を差し引いた勤務時間を正しく計算できる(): void
    {
        // Arrange
        $attendance = new Attendance([
            'clock_in' => '2026-09-28 09:00:00',
            'clock_out' => '2026-09-28 18:00:00',
        ]);

        $attendance->setRelation('breaks', collect([
            new BreakTime([
                'break_in' => '2026-09-28 12:00:00',
                'break_out' => '2026-09-28 13:00:00',
            ]),
        ]));

        // Act
        $totalTime = $attendance->total_time;

        // Assert
        $this->assertSame('08:00:00', $totalTime);
    }

    /**
     * @test
     */
    public function 異常系_終了していない休憩は勤務時間から差し引かない(): void
    {
        // Arrange
        $attendance = new Attendance([
            'clock_in' => '2026-09-28 09:00:00',
            'clock_out' => '2026-09-28 18:00:00',
        ]);

        $attendance->setRelation('breaks', collect([
            new BreakTime([
                'break_in' => '2026-09-28 12:00:00',
                'break_out' => '2026-09-28 13:00:00',
            ]),
            new BreakTime([
                'break_in' => '2026-09-28 15:00:00',
                'break_out' => null,
            ]),
        ]));

        // Act
        $totalTime = $attendance->total_time;

        // Assert
        $this->assertSame('08:00:00', $totalTime);
    }

    /**
     * @test
     */
    public function 境界値_退勤していない場合は勤務時間を算出しない(): void
    {
        // Arrange
        $attendance = new Attendance([
            'clock_in' => '2026-09-28 09:00:00',
            'clock_out' => null,
        ]);

        $attendance->setRelation('breaks', collect());

        // Act
        $totalTime = $attendance->total_time;

        // Assert
        $this->assertNull($totalTime);
    }

    /**
     * @test
     */
    public function 正常系_複数回の休憩時間を差し引いて勤務時間を計算できる(): void
    {
        // Arrange
        $attendance = new Attendance([
            'clock_in' => '2026-10-01 09:00:00',
            'clock_out' => '2026-10-01 18:00:00',
        ]);

        $attendance->setRelation('breaks', collect([
            new BreakTime([
                'break_in' => '2026-10-01 12:00:00',
                'break_out' => '2026-10-01 12:30:00',
            ]),
            new BreakTime([
                'break_in' => '2026-10-01 15:00:00',
                'break_out' => '2026-10-01 15:15:00',
            ]),
        ]));

        // Act
        $totalTime = $attendance->total_time;

        // Assert
        $this->assertSame('08:15:00', $totalTime);
    }
}
