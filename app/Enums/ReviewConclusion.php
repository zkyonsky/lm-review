<?php

namespace App\Enums;

enum ReviewConclusion: string
{
    case Relevant = 'relevant';
    case NeedsRevision = 'needs_revision';

    public function label(): string
    {
        return match ($this) {
            self::Relevant => 'Masih Relevan',
            self::NeedsRevision => 'Perlu Revisi',
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
