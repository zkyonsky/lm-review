<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\MaterialVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MaterialVersion>
 */
class MaterialVersionFactory extends Factory
{
    public function definition(): array
    {
        $fileId = Str::random(33);

        return [
            'material_id' => Material::factory(),
            'version_number' => 1,
            'gdrive_url' => "https://drive.google.com/file/d/{$fileId}/view?usp=sharing",
            'gdrive_file_id' => $fileId,
            'changelog' => null,
            'created_by' => User::factory(),
        ];
    }
}
