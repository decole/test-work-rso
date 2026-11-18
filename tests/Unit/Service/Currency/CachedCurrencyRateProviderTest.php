<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Currency;

use App\Enum\CurrencyEnum;
use App\Exception\CurrencyRateUnavailableException;
use App\Service\Currency\CachedCurrencyRateProvider;
use App\Service\Currency\CurrencyRateProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedCurrencyRateProviderTest extends TestCase
{
    public function testReturnsRateFromCacheOnSubsequentCallsWithoutCallingInnerProviderTwice(): void
    {
        $mockInner = $this->createMock(CurrencyRateProviderInterface::class);
        $mockInner
            ->expects(self::once()) // Crucial: inner provider must be called only once
            ->method('getRate')
            ->willReturn(80.5);

        $cache = new ArrayAdapter();
        $cachedProvider = new CachedCurrencyRateProvider($mockInner, $cache);

        $date = new \DateTimeImmutable('2026-11-18');

        // First call: populates cache from inner provider
        $rate1 = $cachedProvider->getRate(CurrencyEnum::EUR, $date);
        self::assertSame(80.5, $rate1);

        // Second call: must read from cache
        $rate2 = $cachedProvider->getRate(CurrencyEnum::EUR, $date);
        self::assertSame(80.5, $rate2);
    }

    public function testActivatesCircuitBreakerWhenInnerProviderFails(): void
    {
        $mockInner = $this->createMock(CurrencyRateProviderInterface::class);
        $mockInner
            ->expects(self::once())
            ->method('getRate')
            ->willThrowException(CurrencyRateUnavailableException::serviceUnavailable());

        $cache = new ArrayAdapter();
        $cachedProvider = new CachedCurrencyRateProvider($mockInner, $cache);

        $date = new \DateTimeImmutable('2026-11-18');

        // First call: fails and activates circuit breaker
        try {
            $cachedProvider->getRate(CurrencyEnum::USD, $date);
            self::fail('Expected CurrencyRateUnavailableException');
        } catch (CurrencyRateUnavailableException) {
            // Expected
        }

        // Second call (different currency or date): immediately blocked by circuit breaker without calling inner
        $this->expectException(CurrencyRateUnavailableException::class);
        $this->expectExceptionMessage('Сервис получения курсов валют ЦБ РФ временно недоступен.');

        $cachedProvider->getRate(CurrencyEnum::EUR, $date);
    }
}
