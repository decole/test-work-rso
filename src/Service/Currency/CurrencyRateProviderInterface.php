<?php

declare(strict_types=1);

namespace App\Service\Currency;

use App\Enum\CurrencyEnum;
use App\Exception\CurrencyRateUnavailableException;

interface CurrencyRateProviderInterface
{
    /**
     * Retrieve the exchange rate of the given currency against the Russian Ruble (RUB) for the specified date.
     *
     * @throws CurrencyRateUnavailableException
     */
    public function getRate(CurrencyEnum $currency, \DateTimeImmutable $date): float;
}

