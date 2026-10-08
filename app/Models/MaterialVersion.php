<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $material_id
 * @property int $version_number
 * @property string|null $gdrive_url
 * @property string|null $gdrive_file_id
 * @property string|null $file_path
 * @property string|null $original_filename
 * @property int|null $file_size
 * @property string|null $changelog
 * @property int $created_by
 * @property-read Material $material
 * @property-read ScormPackage|null $scormPackage
 * @property-read string|null $preview_url
 */
#[Fillable(['material_id', 'version_number', 'gdrive_url', 'gdrive_file_id', 'file_path', 'original_filename', 'file_size', 'changelog', 'created_by'])]
class MaterialVersion extends Model
{
    use HasFactory;

    protected $appends = ['preview_url', 'is_active', 'google_drive_id'];

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** @return HasOne<ScormPackage, $this> */
    public function scormPackage(): HasOne
    {
        return $this->hasOne(ScormPackage::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<ReviewComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * URL embed preview Google Drive / SCORM / Konten.
     *
     * @return Attribute<string|null, never>
     */
    protected function previewUrl(): Attribute
    {
        return Attribute::get(function () {
            // SCORM preview
            if ($this->material?->type?->value === 'scorm' && $this->scormPackage && $this->scormPackage->status === 'ready') {
                return "/storage/{$this->scormPackage->extract_path}/{$this->scormPackage->launch_path}";
            }

            if (!$this->gdrive_file_id && !$this->gdrive_url) {
                return null;
            }

            $url = $this->gdrive_url ?? '';
            $id = $this->gdrive_file_id;

            // Google Presentation / Slides
            if (str_contains($url, 'presentation') || str_contains($url, 'docs.google.com/presentation')) {
                return "https://docs.google.com/presentation/d/{$id}/embed?start=false&loop=false&delayms=3000";
            }

            // Google Docs
            if (str_contains($url, 'document') || str_contains($url, 'docs.google.com/document')) {
                return "https://docs.google.com/document/d/{$id}/preview";
            }

            // Google Sheets
            if (str_contains($url, 'spreadsheets') || str_contains($url, 'docs.google.com/spreadsheets')) {
                return "https://docs.google.com/spreadsheets/d/{$id}/preview";
            }

            // YouTube Video
            if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
                return "https://www.youtube.com/embed/" . $matches[1];
            }

            // Standard Google Drive preview (PDF, Video, etc.)
            return $id ? "https://drive.google.com/file/d/{$id}/preview" : $url;
        });
    }

    protected function isActive(): Attribute
    {
        return Attribute::get(fn () => $this->material?->current_version_id === $this->id);
    }

    protected function googleDriveId(): Attribute
    {
        return Attribute::get(fn () => $this->gdrive_file_id);
    }
}
