<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 month', 'now')
            ->format('Y-m-d');

        $clockIn = fake()->dateTimeBetween(
            "{$date} 08:00:00",
            "{$date} 10:00:00"
        );

        $clockOut = fake()->dateTimeBetween(
            "{$date} 17:00:00",
            "{$date} 20:00:00"
        );

        return [
            'user_id' => User::factory(),
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'comment' => null,
        ];
    }
}
