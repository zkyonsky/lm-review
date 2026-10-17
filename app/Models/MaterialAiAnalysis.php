<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $material_version_id
 * @property int|null $user_id
 * @property string $model
 * @property string $status
 * @property string|null $summary
 * @property array|null $identified_regulations
 * @property array|null $recommendations
 * @property string|null $suggested_conclusion
 * @property string|null $conclusion_rationale
 * @property array|null $raw_response
 * @property string|null $error_message
 * @property-read MaterialVersion $materialVersion
 * @property-read User|null $user
 */
#[Fillable([
    'material_version_id',
    'user_id',
    'model',
    'status',
    'summary',
    'identified_regulations',
    'recommendations',
    'suggested_conclusion',
    'conclusion_rationale',
    'raw_response',
    'error_message',
])]
class MaterialAiAnalysis extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'identified_regulations' => 'array',
            'recommendations' => 'array',
            'raw_response' => 'array',
        ];
    }

    /** @return BelongsTo<MaterialVersion, $this> */
    public function materialVersion(): BelongsTo
    {
        return $this->belongsTo(MaterialVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
