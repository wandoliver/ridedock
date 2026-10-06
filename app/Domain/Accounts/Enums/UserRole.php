<?php

namespace App\Domain\Accounts\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Admin = 'admin';
}
