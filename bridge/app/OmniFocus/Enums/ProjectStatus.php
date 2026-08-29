<?php

namespace App\OmniFocus\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Done = 'done';
    case Dropped = 'dropped';
    case OnHold = 'on_hold';
}
