<?php

declare(strict_types=1);

namespace App\Service\Calculator;

use App\Dto\CalculationContext;
use App\Dto\CalculationResult;

interface InsuranceCalculatorInterface
{
    public function calculate(CalculationContext $context): CalculationResult;
}
