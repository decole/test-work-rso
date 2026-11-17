<?php

declare(strict_types=1);

namespace App\Service\Currency;

use App\Enum\CurrencyEnum;
use App\Exception\CurrencyRateUnavailableException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsDecorator(decorates: CbrXmlCurrencyRateProvider::class, priority: 10)]
#[AsAlias(CurrencyRateProviderInterface::class)]
final readonly class CachedCurrencyRateProvider implements CurrencyRateProviderInterface
{
    private const int CACHE_TTL_SECONDS = 3600; // 1 hour TTL: ensures timely rate updates without overloading CBR
    private const int CIRCUIT_BREAKER_TTL_SECONDS = 30;
    private const string CIRCUIT_BREAKER_CACHE_KEY = 'cbr_failure_cooldown';
    private const string TEMPLATE_RATE_CACHE_KEY = 'cbr_rate_%s_%s';

    public function __construct(
        private CurrencyRateProviderInterface $inner,
        private CacheInterface $cache,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function getRate(CurrencyEnum $currency, \DateTimeImmutable $date): float
    {
        $isServiceInCooldown = $this->cache->get(
            key: self::CIRCUIT_BREAKER_CACHE_KEY,
            callback: static fn(): bool => false
        );

        if (true === $isServiceInCooldown) {
            $this->logger?->warning('CBR service is currently in cooldown period after a recent failure.');

            throw CurrencyRateUnavailableException::serviceUnavailable();
        }

        try {
            return (float) $this->cache->get(
                key: sprintf(self::TEMPLATE_RATE_CACHE_KEY, $currency->value, $date->format('Y-m-d')),
                callback: function (ItemInterface $item) use ($currency, $date): float {
                    $item->expiresAfter(self::CACHE_TTL_SECONDS);

                    return $this->inner->getRate($currency, $date);
                }
            );
        } catch (CurrencyRateUnavailableException $e) {
            $this->activateCircuitBreaker();

            throw $e;
        } catch (\Throwable $e) {
            $this->activateCircuitBreaker();

            throw CurrencyRateUnavailableException::serviceUnavailable($e);
        }
    }

    private function activateCircuitBreaker(): void
    {
        try {
            $this->cache->delete(key: self::CIRCUIT_BREAKER_CACHE_KEY);

            $this->cache->get(
                key: self::CIRCUIT_BREAKER_CACHE_KEY,
                callback: static function (ItemInterface $item): bool {
                    $item->expiresAfter(self::CIRCUIT_BREAKER_TTL_SECONDS);

                    return true;
                }
            );
        } catch (\Throwable) {
            // Ignore cache failure during circuit breaker activation
        }
    }
}
