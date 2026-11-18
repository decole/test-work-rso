<?php

declare(strict_types=1);

namespace App\Dto\Output;

use App\Dto\CalculationResult;

final readonly class CalculateInsuranceOutput
{
    public function __construct(
        public float $costInCurrency,
        public float $costInRub,
        public int $daysCount,
        public float $dailyRate,
        public float $currencyRate,
        public int $insuredAmount,
    ) {
    }

    public static function fromResult(CalculationResult $result): self
    {
        return new self(
            costInCurrency: $result->costInCurrency,
            costInRub: $result->costInRub,
            daysCount: $result->daysCount,
            dailyRate: $result->dailyRate,
            currencyRate: $result->currencyRate,
            insuredAmount: $result->insuredAmount,
        );
    }
}

