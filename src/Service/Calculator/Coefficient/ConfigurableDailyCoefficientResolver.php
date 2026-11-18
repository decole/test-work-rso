<?php

declare(strict_types=1);

namespace App\Service\Calculator\Coefficient;

use App\Exception\UnsupportedInsuredAmountException;

final readonly class ConfigurableDailyCoefficientResolver implements DailyCoefficientResolverInterface
{
    /**
     * @param array<int, float> $coefficients
     */
    public function __construct(
        private array $coefficients = [
            30000 => 0.6,
            50000 => 0.8,
        ],
    ) {}

    public function resolve(int $insuredAmount): float
    {
        return $this->coefficients[$insuredAmount]
            ?? throw UnsupportedInsuredAmountException::forAmount($insuredAmount, $this->getSupportedAmounts());
    }

    public function supports(int $insuredAmount): bool
    {
        return isset($this->coefficients[$insuredAmount]);
    }

    public function getSupportedAmounts(): array
    {
        return array_keys($this->coefficients);
    }
}
