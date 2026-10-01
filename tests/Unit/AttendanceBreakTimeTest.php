<?php

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\BreakTime;
use Tests\TestCase;

class AttendanceBreakTimeTest extends TestCase
{
    /**
     * @test
     */
    public function 正常系_休憩が1回の場合に休憩時間を正しく合計できる(): void
    {
        // Arrange
        $attendance = new Attendance;

        $attendance->setRelation('breaks', collect([
            new BreakTime([
                'break_in' => '2026-09-28 12:00:00',
                'break_out' => '2026-09-28 13:00:00',
            ]),
        ]));

        // Act
        $totalBreakTime = $attendance->total_break_time;

        // Assert
        $this->assertSame('01:00:00', $totalBreakTime);
    }

    /**
     * @test
     */
    public function 正常系_複数回の休憩時間を正しく合計できる(): void
    {
        // Arrange
        $attendance = new Attendance;

        $attendance->setRelation('breaks', collect([
            new BreakTime([
                'break_in' => '2026-09-28 12:00:00',
                'break_out' => '2026-09-28 13:00:00',
            ]),
            new BreakTime([
                'break_in' => '2026-09-28 15:00:00',
                'break_out' => '2026-09-28 15:15:00',
            ]),
        ]));

        // Act
        $totalBreakTime = $attendance->total_break_time;

        // Assert
        $this->assertSame('01:15:00', $totalBreakTime);
    }

    /**
     * @test
     */
    public function 異常系_終了していない休憩は休憩時間の合計に含めない(): void
    {
        // Arrange
        $attendance = new Attendance;

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
        $totalBreakTime = $attendance->total_break_time;

        // Assert
        $this->assertSame('01:00:00', $totalBreakTime);
    }

    /**
     * @test
     */
    public function 境界値_休憩がない場合は休憩時間が0になる(): void
    {
        // Arrange
        $attendance = new Attendance;

        $attendance->setRelation('breaks', collect());

        // Act
        $totalBreakTime = $attendance->total_break_time;

        // Assert
        $this->assertSame('00:00:00', $totalBreakTime);
    }

    /**
     * @test
     */
    public function 境界値_1秒の休憩時間を正しく計算できる(): void
    {
        // Arrange
        $attendance = new Attendance;

        $attendance->setRelation('breaks', collect([
            new BreakTime([
                'break_in' => '2026-09-28 12:00:00',
                'break_out' => '2026-09-28 12:00:01',
            ]),
        ]));

        // Act
        $totalBreakTime = $attendance->total_break_time;

        // Assert
        $this->assertSame('00:00:01', $totalBreakTime);
    }
}
