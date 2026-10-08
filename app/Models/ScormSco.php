<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $scorm_package_id
 * @property string $identifier
 * @property string $title
 * @property string $launch_path
 * @property int $sort_order
 */
#[Fillable(['scorm_package_id', 'identifier', 'title', 'launch_path', 'sort_order'])]
class ScormSco extends Model
{
    /** @return BelongsTo<ScormPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class, 'scorm_package_id');
    }
}
