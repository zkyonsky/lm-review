<?php

namespace App\Enums;

enum CommentStatus: string
{
    case Open = 'open';
    case Addressed = 'addressed';
}
