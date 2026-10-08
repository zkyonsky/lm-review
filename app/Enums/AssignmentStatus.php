<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum Dimulai',
            self::InProgress => 'Sedang Dikerjakan',
            self::Submitted => 'Sudah Dikirim',
        };
    }
}
