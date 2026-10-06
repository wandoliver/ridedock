<?php

namespace App\Domain\Activities\Enums;

enum ConnectionStatus: string
{
    case Active = 'active';
    case Queued = 'queued';
    case Syncing = 'syncing';
    case Waiting = 'waiting';
    case Error = 'error';
    case Revoked = 'revoked';
}
