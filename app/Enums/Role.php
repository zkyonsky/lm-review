<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Developer = 'pengembang';
    case Reviewer = 'reviewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Developer => 'Pengembang Materi',
            self::Reviewer => 'Reviewer',
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
