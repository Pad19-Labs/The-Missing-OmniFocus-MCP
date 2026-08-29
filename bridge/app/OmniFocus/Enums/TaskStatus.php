<?php

namespace App\OmniFocus\Enums;

enum TaskStatus: string
{
    case Available = 'available';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Dropped = 'dropped';
    case DueSoon = 'due_soon';
    case Next = 'next';
    case Overdue = 'overdue';
}
