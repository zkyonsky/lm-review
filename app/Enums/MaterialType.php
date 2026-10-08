<?php

namespace App\Enums;

enum MaterialType: string
{
    case Pdf = 'pdf';
    case Video = 'video';
    case Scorm = 'scorm';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Video => 'Video',
            self::Scorm => 'SCORM',
        };
    }

    public function usesGoogleDrive(): bool
    {
        return $this !== self::Scorm;
    }

    /**
     * @return array<int, array{value: string, label: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'name' => $case->label(),
        ], self::cases());
    }
}
