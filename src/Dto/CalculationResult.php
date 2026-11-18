<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\CurrencyEnum;

final readonly class CalculationResult
{
    public function __construct(
        public float $costInCurrency,
        public float $costInRub,
        public int $daysCount,
        public float $dailyRate,
        public float $currencyRate,
        public int $insuredAmount,
        public CurrencyEnum $currency,
    ) {}
}
