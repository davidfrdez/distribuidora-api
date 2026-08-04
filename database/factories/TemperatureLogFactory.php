<?php

namespace Database\Factories;

use App\Models\TemperatureLog;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemperatureLog>
 */
class TemperatureLogFactory extends Factory
{
    protected $model = TemperatureLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouseId' => Warehouse::factory(),
            'temperature' => 2,
            'expectedMin' => 0,
            'expectedMax' => 4,
            'outOfRange' => false,
            'source' => 'MANUAL',
            'recordedAt' => now(),
        ];
    }
}
