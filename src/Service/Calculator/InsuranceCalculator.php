<?php

declare(strict_types=1);

namespace App\Service\Calculator;

use App\Dto\CalculationContext;
use App\Dto\CalculationResult;
use App\Service\Calculator\Coefficient\DailyCoefficientResolverInterface;
use App\Service\Calculator\Modifier\PriceModifierInterface;
use App\Service\Currency\CurrencyRateProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class InsuranceCalculator implements InsuranceCalculatorInterface
{
    /**
     * @param iterable<PriceModifierInterface> $modifiers
     */
    public function __construct(
        private DailyCoefficientResolverInterface $coefficientResolver,
        private CurrencyRateProviderInterface $rateProvider,
        #[AutowireIterator('app.insurance.price_modifier')]
        private iterable $modifiers = [],
    ) {
    }

    public function calculate(CalculationContext $context): CalculationResult
    {
        $daysCount = $context->period->getDaysCount();
        $dailyRate = $this->coefficientResolver->resolve($context->insuredAmount);

        // Base calculation: (Daily coefficient) * (number of days)
        // Using bcmath to ensure precision during intermediate calculations
        $baseCostInCurrency = bcmul((string) $dailyRate, (string) $daysCount, 4);

        // Formula extensibility: apply price modifiers chain if registered
        $finalCostInCurrency = $baseCostInCurrency;

        foreach ($this->modifiers as $modifier) {
            $finalCostInCurrency = $modifier->modify($finalCostInCurrency, $context);
        }

        // Retrieve exchange rate for today
        $today = new \DateTimeImmutable();

        $currencyRate = $this->rateProvider->getRate($context->currency, $today);

        // Calculate cost in rubles: (cost in currency) * exchange rate
        $costInRub = bcmul($finalCostInCurrency, (string) $currencyRate, 4);

        // Round money amounts to 2 decimal places and currency rate to 4 places per specification
        return new CalculationResult(
            costInCurrency: round((float) $finalCostInCurrency, 2),
            costInRub: round((float) $costInRub, 2),
            daysCount: $daysCount,
            dailyRate: $dailyRate,
            currencyRate: round($currencyRate, 4),
            insuredAmount: $context->insuredAmount,
            currency: $context->currency,
        );
    }
}

