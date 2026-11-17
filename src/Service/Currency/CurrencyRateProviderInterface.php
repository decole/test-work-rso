<?php

declare(strict_types=1);

namespace App\Service\Currency;

use App\Enum\CurrencyEnum;
use App\Exception\CurrencyRateUnavailableException;

interface CurrencyRateProviderInterface
{
    /**
     * Получить курс валюты по отношению к рублю (RUB) на указанную дату.
     *
     * @throws CurrencyRateUnavailableException
     */
    public function getRate(CurrencyEnum $currency, \DateTimeImmutable $date): float;
}

