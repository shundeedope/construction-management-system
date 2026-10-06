<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'documentable_type' => null,
            'documentable_id' => null,
            'name' => fake()->sentence(3).'.pdf',
            'description' => fake()->optional()->sentence(),
            'category' => fake()->randomElement(['contract', 'change_order', 'client_invoice', 'subcontractor_invoice', 'plan', 'compliance', 'photo', 'general']),
            'external_url' => null,
            'google_drive_file_id' => null,
            'google_drive_folder_id' => null,
            'uploaded_by' => User::factory(),
        ];
    }

    public function external(): static
    {
        return $this->state(fn (array $attributes): array => ['external_url' => fake()->url()]);
    }
}
