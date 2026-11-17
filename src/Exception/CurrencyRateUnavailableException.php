<?php

declare(strict_types=1);

namespace App\Exception;

class CurrencyRateUnavailableException extends \RuntimeException
{
    public static function forCurrency(string $currency, ?\Throwable $previous = null): self
    {
        return new self(sprintf('Курс для валюты %s временно недоступен.', $currency), 0, $previous);
    }

    public static function serviceUnavailable(?\Throwable $previous = null): self
    {
        return new self('Сервис получения курсов валют ЦБ РФ временно недоступен.', 0, $previous);
    }
}

