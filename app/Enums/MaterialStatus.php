<?php

namespace App\Enums;

enum MaterialStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Reviewed = 'reviewed';
    case Revising = 'revising';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'Sedang Direviu',
            self::Reviewed => 'Selesai Direviu',
            self::Revising => 'Dalam Revisi',
            self::Completed => 'Final',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
