<?php

namespace App\Domain\Accounts\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
}
