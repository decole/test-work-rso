<?php

declare(strict_types=1);

namespace App\Enum;

enum CurrencyEnum: string
{
    case EUR = 'EUR';
    case USD = 'USD';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
