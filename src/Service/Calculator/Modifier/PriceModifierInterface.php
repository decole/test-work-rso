<?php

declare(strict_types=1);

namespace App\Service\Calculator\Modifier;

use App\Dto\CalculationContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.insurance.price_modifier')]
interface PriceModifierInterface
{
    /**
     * Modify the price in currency based on calculation context.
     *
     * @param numeric-string $currentPriceInCurrency
     *
     * @return numeric-string
     */
    public function modify(string $currentPriceInCurrency, CalculationContext $context): string;
}
