<?php

namespace App\OmniFocus\Enums;

enum RepetitionMethod: string
{
    case Fixed = 'fixed';
    case DueDate = 'due_date';
    case DeferUntilDate = 'defer_until_date';
}
