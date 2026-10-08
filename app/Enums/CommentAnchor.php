<?php

namespace App\Enums;

enum CommentAnchor: string
{
    case General = 'general';
    case Page = 'page';
    case Timestamp = 'timestamp';
    case Sco = 'sco';

    /**
     * Anchor yang valid untuk tipe materi tertentu.
     *
     * @return array<int, self>
     */
    public static function forMaterialType(MaterialType $type): array
    {
        return match ($type) {
            MaterialType::Pdf => [self::General, self::Page],
            MaterialType::Video => [self::General, self::Timestamp],
            MaterialType::Scorm => [self::General, self::Sco],
        };
    }
}
