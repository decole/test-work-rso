<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\CurrencyEnum;

final readonly class CalculationContext
{
    public function __construct(
        public int $insuredAmount,
        public TripPeriod $period,
        public CurrencyEnum $currency,
    ) {}
}
