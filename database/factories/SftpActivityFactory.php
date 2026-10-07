<?php

namespace Database\Factories;

use App\Models\SftpActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SftpActivity>
 */
class SftpActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_key' => hash('sha256', fake()->uuid()),
            'action' => 'upload',
            'username' => fake()->userName(),
            'path' => '/'.fake()->word().'.txt',
            'target_path' => null,
            'size' => fake()->numberBetween(1, 10000),
            'protocol' => 'SFTP',
            'occurred_at' => now(),
        ];
    }
}
