<?php

declare(strict_types=1);

namespace App\Model;

enum CurrencyEnum: string
{
    case EUR = 'EUR';
    case USD = 'USD';
}
