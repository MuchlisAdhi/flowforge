<?php

namespace Database\Factories;

use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
