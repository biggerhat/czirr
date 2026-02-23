<?php

namespace App\Enums;

enum ListType: string
{
    case Grocery = 'grocery';
    case Shopping = 'shopping';
    case Todo = 'todo';
    case Wishlist = 'wishlist';
    case Custom = 'custom';
}
