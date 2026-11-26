<?php

namespace Database\Factories;

use App\Enums\ProspectSource;
use App\Enums\ProspectStatus;
use App\Models\Company;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prospect>
 */
class ProspectFactory extends Factory
{
    protected $model = Prospect::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'whatsapp' => fake()->e164PhoneNumber(),
            'company_name' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'socials' => [
                'linkedin' => 'https://linkedin.com/in/'.fake()->userName(),
                'website' => fake()->url(),
            ],
            'source' => ProspectSource::Import,
            'status' => ProspectStatus::New,
        ];
    }
}
