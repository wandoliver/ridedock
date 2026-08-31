<?php

namespace App\Domain\Activities\Enums;

enum ProviderName: string
{
    case Hammerhead = 'hammerhead';
    case Wahoo = 'wahoo';
    case File = 'file';
}
