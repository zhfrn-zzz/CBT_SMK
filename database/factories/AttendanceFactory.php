<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attendance> */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'subject_id' => Subject::factory(),
            'user_id' => User::factory()->guru(),
            'meeting_date' => today(),
            'meeting_number' => 1,
            'access_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'code_expires_at' => now()->addMinutes(30),
            'is_open' => true,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['is_open' => false]);
    }
}
