<?php

declare(strict_types=1);

namespace App\Service\Currency;

use App\Enum\CurrencyEnum;
use App\Exception\CurrencyRateUnavailableException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CbrXmlCurrencyRateProvider implements CurrencyRateProviderInterface
{
    public function __construct(
        #[Autowire(service: 'cbr.client')]
        private HttpClientInterface $httpClient,
    ) {
    }

    public function getRate(CurrencyEnum $currency, \DateTimeImmutable $date): float
    {
        $dateFormatted = $date->format('d/m/Y');

        try {
            $response = $this->httpClient->request('GET', 'scripts/XML_daily.asp', [
                'query' => [
                    'date_req' => $dateFormatted,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if (200 !== $statusCode) {
                throw CurrencyRateUnavailableException::serviceUnavailable();
            }

            $xmlContent = $response->getContent();
        } catch (\Throwable $e) {
            if ($e instanceof CurrencyRateUnavailableException) {
                throw $e;
            }

            throw CurrencyRateUnavailableException::serviceUnavailable($e);
        }

        return $this->parseRate($xmlContent, $currency);
    }

    private function parseRate(string $xmlContent, CurrencyEnum $currency): float
    {
        if (false !== stripos($xmlContent, 'windows-1251')) {
            $xmlContent = mb_convert_encoding($xmlContent, 'UTF-8', 'Windows-1251');
            $xmlContent = (string) preg_replace('/encoding=["\']windows-1251["\']/i', 'encoding="UTF-8"', $xmlContent);
        }

        $useErrors = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($xmlContent);

            if (false === $xml) {
                throw CurrencyRateUnavailableException::serviceUnavailable();
            }

            foreach ($xml->Valute as $valute) {
                if ((string) $valute->CharCode === $currency->value) {
                    $nominal = (int) $valute->Nominal;
                    $nominal = $nominal > 0 ? $nominal : 1;
                    $value = (float) str_replace(',', '.', (string) $valute->Value);

                    return round($value / $nominal, 4);
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof CurrencyRateUnavailableException) {
                throw $e;
            }

            throw CurrencyRateUnavailableException::forCurrency($currency->value, $e);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useErrors);
        }

        throw CurrencyRateUnavailableException::forCurrency($currency->value);
    }
}

