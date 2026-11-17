<?php

declare(strict_types=1);

namespace App\Service\Calculator\Coefficient;

interface DailyCoefficientResolverInterface
{
    public function resolve(int $insuredAmount): float;

    public function supports(int $insuredAmount): bool;

    /**
     * @return list<int>
     */
    public function getSupportedAmounts(): array;
}

