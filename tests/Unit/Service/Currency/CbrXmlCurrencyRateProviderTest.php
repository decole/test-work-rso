<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Currency;

use App\Enum\CurrencyEnum;
use App\Exception\CurrencyRateUnavailableException;
use App\Service\Currency\CbrXmlCurrencyRateProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CbrXmlCurrencyRateProviderTest extends TestCase
{
    private function createSampleCbrXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="windows-1251"?>
<ValCurs Date="18.11.2026" name="Foreign Currency Market">
    <Valute ID="R01235">
        <NumCode>840</NumCode>
        <CharCode>USD</CharCode>
        <Nominal>1</Nominal>
        <Name>Доллар США</Name>
        <Value>82,4529</Value>
        <VunitRate>82,4529</VunitRate>
    </Valute>
    <Valute ID="R01239">
        <NumCode>978</NumCode>
        <CharCode>EUR</CharCode>
        <Nominal>1</Nominal>
        <Name>Евро</Name>
        <Value>90,8136</Value>
        <VunitRate>90,8136</VunitRate>
    </Valute>
    <Valute ID="R01335">
        <NumCode>398</NumCode>
        <CharCode>KZT</CharCode>
        <Nominal>100</Nominal>
        <Name>Казахстанских тенге</Name>
        <Value>18,5538</Value>
        <VunitRate>0,185538</VunitRate>
    </Valute>
</ValCurs>
XML;
    }

    public function testParsesRatesFromValidCbrXml(): void
    {
        $mockResponse = $this->createStub(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn($this->createSampleCbrXml());

        $mockClient = $this->createMock(HttpClientInterface::class);
        $mockClient
            ->expects(self::once())
            ->method('request')
            ->with(
                'GET',
                'scripts/XML_daily.asp',
                self::callback(static fn(array $options): bool => isset($options['query']['date_req'])),
            )
            ->willReturn($mockResponse);

        $provider = new CbrXmlCurrencyRateProvider($mockClient);
        $rate = $provider->getRate(CurrencyEnum::EUR, new \DateTimeImmutable('2026-11-18'));

        self::assertSame(90.8136, $rate);
    }

    public function testThrowsExceptionWhenCurrencyNotFoundInXml(): void
    {
        $xmlWithoutUsd = <<<'XML'
<?xml version="1.0" encoding="windows-1251"?>
<ValCurs Date="18.11.2026" name="Foreign Currency Market">
    <Valute ID="R01239">
        <CharCode>EUR</CharCode>
        <Nominal>1</Nominal>
        <Value>90,8136</Value>
    </Valute>
</ValCurs>
XML;

        $mockResponse = $this->createStub(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getContent')->willReturn($xmlWithoutUsd);

        $mockClient = $this->createStub(HttpClientInterface::class);
        $mockClient->method('request')->willReturn($mockResponse);

        $provider = new CbrXmlCurrencyRateProvider($mockClient);

        $this->expectException(CurrencyRateUnavailableException::class);
        $this->expectExceptionMessage('Курс для валюты USD временно недоступен.');

        $provider->getRate(CurrencyEnum::USD, new \DateTimeImmutable('2026-11-18'));
    }

    public function testThrowsExceptionWhenCbrReturnsNon200StatusCode(): void
    {
        $mockResponse = $this->createStub(ResponseInterface::class);
        $mockResponse->method('getStatusCode')->willReturn(500);

        $mockClient = $this->createStub(HttpClientInterface::class);
        $mockClient->method('request')->willReturn($mockResponse);

        $provider = new CbrXmlCurrencyRateProvider($mockClient);

        $this->expectException(CurrencyRateUnavailableException::class);
        $this->expectExceptionMessage('Сервис получения курсов валют ЦБ РФ временно недоступен.');

        $provider->getRate(CurrencyEnum::EUR, new \DateTimeImmutable('2026-11-18'));
    }

    public function testThrowsExceptionWhenHttpClientThrowsTransportException(): void
    {
        $mockClient = $this->createStub(HttpClientInterface::class);
        $mockClient
            ->method('request')
            ->willThrowException(new \RuntimeException('Connection timed out'));

        $provider = new CbrXmlCurrencyRateProvider($mockClient);

        $this->expectException(CurrencyRateUnavailableException::class);
        $this->expectExceptionMessage('Сервис получения курсов валют ЦБ РФ временно недоступен.');

        $provider->getRate(CurrencyEnum::EUR, new \DateTimeImmutable('2026-11-18'));
    }
}
