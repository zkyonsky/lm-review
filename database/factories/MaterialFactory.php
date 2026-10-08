<?php

namespace Database\Factories;

use App\Enums\MaterialStatus;
use App\Enums\MaterialType;
use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'type' => MaterialType::Pdf,
            'status' => MaterialStatus::Draft,
            'sort_order' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function video(): static
    {
        return $this->state(['type' => MaterialType::Video]);
    }

    public function scorm(): static
    {
        return $this->state(['type' => MaterialType::Scorm]);
    }

    /**
     * Buat versi 1 (link Google Drive) dan jadikan versi aktif.
     */
    public function withVersion(): static
    {
        return $this->afterCreating(function (Material $material) {
            $version = MaterialVersion::factory()->for($material)->create([
                'created_by' => $material->created_by,
            ]);
            $material->update(['current_version_id' => $version->id]);
        });
    }
}
