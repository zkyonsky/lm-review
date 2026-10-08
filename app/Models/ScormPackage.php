<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $material_version_id
 * @property string|null $scorm_version
 * @property string|null $identifier
 * @property string|null $title
 * @property string|null $extract_path
 * @property string|null $launch_path
 * @property string $status
 * @property string|null $error_message
 * @property-read MaterialVersion $materialVersion
 */
#[Fillable(['material_version_id', 'scorm_version', 'identifier', 'title', 'extract_path', 'launch_path', 'status', 'error_message'])]
class ScormPackage extends Model
{
    /** @return BelongsTo<MaterialVersion, $this> */
    public function materialVersion(): BelongsTo
    {
        return $this->belongsTo(MaterialVersion::class);
    }

    /** @return HasMany<ScormSco, $this> */
    public function scos(): HasMany
    {
        return $this->hasMany(ScormSco::class)->orderBy('sort_order');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}
