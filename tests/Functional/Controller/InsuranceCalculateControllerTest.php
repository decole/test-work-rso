<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Exception\CurrencyRateUnavailableException;
use App\Service\Calculator\InsuranceCalculatorInterface;
use App\Service\Currency\CurrencyRateProviderInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class InsuranceCalculateControllerTest extends WebTestCase
{
    public function testSuccessfulCalculationReturns200WithExactFormulaData(): void
    {
        $client = static::createClient();

        // Stub currency provider to return fixed predictable rate 80.0
        $mockRateProvider = $this->createStub(CurrencyRateProviderInterface::class);
        $mockRateProvider
            ->method('getRate')
            ->willReturn(80.0);

        static::getContainer()->set(CurrencyRateProviderInterface::class, $mockRateProvider);

        $client->request(
            'POST',
            '/api/v1/insurance/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'insured_amount' => 30000,
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-03',
                'currency' => 'EUR',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $responseContent = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals(1.8, $responseContent['cost_in_currency']);
        self::assertEquals(144.0, $responseContent['cost_in_rub']);
        self::assertSame(3, $responseContent['days_count']);
        self::assertEquals(0.6, $responseContent['daily_rate']);
        self::assertEquals(80.0, $responseContent['currency_rate']);
        self::assertSame(30000, $responseContent['insured_amount']);
    }

    public function testOneDayTripCalculationSucceeds(): void
    {
        $client = static::createClient();

        $mockRateProvider = $this->createStub(CurrencyRateProviderInterface::class);
        $mockRateProvider->method('getRate')->willReturn(85.0);
        static::getContainer()->set(CurrencyRateProviderInterface::class, $mockRateProvider);

        $client->request(
            'POST',
            '/api/v1/insurance/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'insured_amount' => 50000,
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-01', // Single day trip
                'currency' => 'USD',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['days_count']);
        self::assertEquals(0.8, $data['daily_rate']);
        self::assertEquals(0.8, $data['cost_in_currency']);
        self::assertEquals(68.0, $data['cost_in_rub']); // 0.8 * 85.0
    }

    public function testReturns422WhenInsuredAmountIsUnsupported(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/insurance/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'insured_amount' => 10000, // Invalid: only 30000 and 50000 allowed
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-03',
                'currency' => 'EUR',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ERROR', $data['type']);
        self::assertNotEmpty($data['data']);
        self::assertSame('insured_amount', $data['data'][0]['property']);
        self::assertStringContainsString('30000, 50000', $data['data'][0]['message']);
    }

    public function testReturns422WhenStartDateIsAfterEndDate(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/insurance/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'insured_amount' => 30000,
                'start_date' => '2025-10-05',
                'end_date' => '2025-10-01', // Start > End
                'currency' => 'EUR',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ERROR', $data['type']);
        self::assertSame('end_date', $data['data'][0]['property']);
        self::assertStringContainsString('не может быть позже даты окончания', $data['data'][0]['message']);
    }

    public function testReturns422WhenCurrencyIsUnsupported(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/insurance/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'insured_amount' => 30000,
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-03',
                'currency' => 'JPY', // Unsupported currency
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ERROR', $data['type']);
        self::assertSame('currency', $data['data'][0]['property']);
    }

    public function testReturns400WhenPayloadIsMalformedJson(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/insurance/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{invalid-json'
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ERROR', $data['type']);
        self::assertStringContainsString('invalid "json"', $data['data']['message']);
    }

    public function testReturns405WhenRequestMethodIsNotPost(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/insurance/calculate');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ERROR', $data['type']);
    }

    public function testReturns5xxWhenCurrencyRateProviderFails(): void
    {
        $client = static::createClient();

        // Stub calculator to throw CurrencyRateUnavailableException
        $mockCalculator = $this->createStub(InsuranceCalculatorInterface::class);
        $mockCalculator
            ->method('calculate')
            ->willThrowException(CurrencyRateUnavailableException::serviceUnavailable());

        static::getContainer()->set(InsuranceCalculatorInterface::class, $mockCalculator);

        $client->request(
            'POST',
            '/api/v1/insurance/calculate',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'insured_amount' => 30000,
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-03',
                'currency' => 'EUR',
            ], JSON_THROW_ON_ERROR)
        );

        // Verification of requirement: return 5xx HTTP response on currency rate fetch failure
        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE); // 503

        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('ERROR', $data['type']);
        self::assertStringContainsString('ЦБ РФ временно недоступен', $data['data']['message']);
    }
}
