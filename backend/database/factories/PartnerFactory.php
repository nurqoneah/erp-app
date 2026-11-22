<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contacts\Models\Partner;

/** @extends Factory<Partner> */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'type' => Partner::TYPE_COMPANY,
            'name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'is_customer' => true,
            'is_vendor' => false,
            'is_active' => true,
        ];
    }

    public function person(): static
    {
        return $this->state(fn () => ['type' => Partner::TYPE_PERSON, 'name' => fake()->name()]);
    }

    public function vendor(): static
    {
        return $this->state(fn () => ['is_customer' => false, 'is_vendor' => true]);
    }
}
