<?php

namespace App\Domain\Activities\Enums;

enum ConnectionStatus: string
{
    case Active = 'active';
    case Syncing = 'syncing';
    case Error = 'error';
    case Revoked = 'revoked';
}
