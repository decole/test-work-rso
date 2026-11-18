<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\SyncCurrencyRatesCommand;
use App\Enum\CurrencyEnum;
use App\Exception\CurrencyRateUnavailableException;
use App\Service\Currency\CurrencyRateProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncCurrencyRatesCommandTest extends TestCase
{
    public function testExecutesSuccessfullyAndDisplaysRates(): void
    {
        $mockProvider = $this->createStub(CurrencyRateProviderInterface::class);
        $mockProvider
            ->method('getRate')
            ->willReturnCallback(static function (CurrencyEnum $currency): float {
                return match ($currency) {
                    CurrencyEnum::EUR => 90.5,
                    CurrencyEnum::USD => 85.0,
                };
            });

        $command = new SyncCurrencyRatesCommand($mockProvider);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $tester->getDisplay();

        self::assertStringContainsString('Синхронизация курсов валют ЦБ РФ', $output);
        self::assertStringContainsString('90.5000 RUB', $output);
        self::assertStringContainsString('85.0000 RUB', $output);
        self::assertStringContainsString('Успешно (в кэше)', $output);
    }

    public function testHandlesRateFetchFailureGracefully(): void
    {
        $mockProvider = $this->createStub(CurrencyRateProviderInterface::class);
        $mockProvider
            ->method('getRate')
            ->willThrowException(CurrencyRateUnavailableException::serviceUnavailable());

        $command = new SyncCurrencyRatesCommand($mockProvider);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        $output = $tester->getDisplay();

        self::assertStringContainsString('Ошибка', $output);
        self::assertStringContainsString('Некоторые курсы не удалось получить', $output);
    }
}
