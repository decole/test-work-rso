<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Calculator;

use App\Dto\CalculationContext;
use App\Dto\TripPeriod;
use App\Enum\CurrencyEnum;
use App\Service\Calculator\Coefficient\ConfigurableDailyCoefficientResolver;
use App\Service\Calculator\InsuranceCalculator;
use App\Service\Calculator\Modifier\PriceModifierInterface;
use App\Service\Currency\CurrencyRateProviderInterface;
use PHPUnit\Framework\TestCase;

final class InsuranceCalculatorTest extends TestCase
{
    private ConfigurableDailyCoefficientResolver $coefficientResolver;

    protected function setUp(): void
    {
        $this->coefficientResolver = new ConfigurableDailyCoefficientResolver([
            30000 => 0.6,
            50000 => 0.8,
        ]);
    }

    public function testCalculatesExactSpecificationExample(): void
    {
        // Specification: 3 days (2025-10-01 to 2025-10-03), 30k EUR, rate = 80 -> cost 1.8 EUR, 144 RUB
        $mockRateProvider = $this->createMock(CurrencyRateProviderInterface::class);
        $mockRateProvider
            ->expects(self::once())
            ->method('getRate')
            ->with(CurrencyEnum::EUR, self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(80.0);

        $calculator = new InsuranceCalculator($this->coefficientResolver, $mockRateProvider);

        $period = TripPeriod::fromStrings('2025-10-01', '2025-10-03');
        $context = new CalculationContext(30000, $period, CurrencyEnum::EUR);

        $result = $calculator->calculate($context);

        self::assertSame(3, $result->daysCount);
        self::assertSame(0.6, $result->dailyRate);
        self::assertSame(1.8, $result->costInCurrency);
        self::assertSame(80.0, $result->currencyRate);
        self::assertSame(144.0, $result->costInRub);
        self::assertSame(30000, $result->insuredAmount);
        self::assertSame(CurrencyEnum::EUR, $result->currency);
    }

    public function testCalculatesOneDayTripWithUsdCurrency(): void
    {
        $mockRateProvider = $this->createMock(CurrencyRateProviderInterface::class);
        $mockRateProvider
            ->expects(self::once())
            ->method('getRate')
            ->with(CurrencyEnum::USD, self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn(82.4529);

        $calculator = new InsuranceCalculator($this->coefficientResolver, $mockRateProvider);

        $period = TripPeriod::fromStrings('2025-10-01', '2025-10-01');
        $context = new CalculationContext(50000, $period, CurrencyEnum::USD);

        $result = $calculator->calculate($context);

        // 1 day * 0.8 = 0.8 USD
        // 0.8 * 82.4529 = 65.96232 -> rounded to 65.96 RUB
        self::assertSame(1, $result->daysCount);
        self::assertSame(0.8, $result->dailyRate);
        self::assertSame(0.8, $result->costInCurrency);
        self::assertSame(82.4529, $result->currencyRate);
        self::assertSame(65.96, $result->costInRub);
    }

    public function testFormulaExtensibilityViaPriceModifierChain(): void
    {
        $mockRateProvider = $this->createStub(CurrencyRateProviderInterface::class);
        $mockRateProvider
            ->method('getRate')
            ->willReturn(80.0);

        // Simulate an active sport coefficient modifier (+50% / x1.5)
        $sportModifier = new class implements PriceModifierInterface {
            public function modify(string $currentPriceInCurrency, CalculationContext $context): string
            {
                return bcmul($currentPriceInCurrency, '1.5', 4);
            }
        };

        $calculator = new InsuranceCalculator(
            $this->coefficientResolver,
            $mockRateProvider,
            [$sportModifier]
        );

        $period = TripPeriod::fromStrings('2025-10-01', '2025-10-03'); // 3 days
        $context = new CalculationContext(30000, $period, CurrencyEnum::EUR);

        $result = $calculator->calculate($context);

        // 3 days * 0.6 = 1.8 * 1.5 = 2.70 EUR
        // 2.70 * 80.0 = 216.00 RUB
        self::assertSame(2.7, $result->costInCurrency);
        self::assertSame(216.0, $result->costInRub);
    }
}
