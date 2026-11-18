<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Calculator\Coefficient;

use App\Exception\UnsupportedInsuredAmountException;
use App\Service\Calculator\Coefficient\ConfigurableDailyCoefficientResolver;
use PHPUnit\Framework\TestCase;

final class ConfigurableDailyCoefficientResolverTest extends TestCase
{
    private ConfigurableDailyCoefficientResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ConfigurableDailyCoefficientResolver([
            30000 => 0.6,
            50000 => 0.8,
        ]);
    }

    public function testResolvesCoefficientForSupportedAmounts(): void
    {
        self::assertSame(0.6, $this->resolver->resolve(30000));
        self::assertSame(0.8, $this->resolver->resolve(50000));
    }

    public function testSupportsCheckReturnsExpectedBooleans(): void
    {
        self::assertTrue($this->resolver->supports(30000));
        self::assertTrue($this->resolver->supports(50000));
        self::assertFalse($this->resolver->supports(10000));
        self::assertFalse($this->resolver->supports(0));
        self::assertFalse($this->resolver->supports(-50000));
    }

    public function testReturnsSupportedAmountsList(): void
    {
        self::assertSame([30000, 50000], $this->resolver->getSupportedAmounts());
    }

    public function testThrowsExceptionForUnsupportedAmount(): void
    {
        $this->expectException(UnsupportedInsuredAmountException::class);
        $this->expectExceptionMessageIsOrContains('Страховая сумма 10000 не поддерживается. Допустимые суммы: 30000, 50000.');

        $this->resolver->resolve(10000);
    }

    public function testCanBeConfiguredWithCustomAmountsAndRates(): void
    {
        $customResolver = new ConfigurableDailyCoefficientResolver([
            20000 => 0.4,
            100000 => 1.5,
        ]);

        self::assertSame(0.4, $customResolver->resolve(20000));
        self::assertSame(1.5, $customResolver->resolve(100000));
        self::assertSame([20000, 100000], $customResolver->getSupportedAmounts());
    }
}
