<?php

namespace App\Enums;

enum ListVisibility: string
{
    case Everyone = 'everyone';
    case Parents = 'parents';
    case Children = 'children';
    case Specific = 'specific';
}
