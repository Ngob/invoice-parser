<?php

declare(strict_types=1);

namespace App\Enum;

enum AttemptStatusEnum: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Done = 'done';
    case Failure = 'failure';
}
