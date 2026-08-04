<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::ADMINISTRADOR->value,
            'phone' => fake()->numerify('30########'),
            'active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(UserRole $role): static
    {
        return $this->state(fn () => ['role' => $role->value]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
