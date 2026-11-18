<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\CurrencyEnum;
use App\Service\Currency\CurrencyRateProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:currency:sync',
    description: 'Синхронизирует и прогревает кэш актуальных курсов валют ЦБ РФ на сегодня',
)]
final class SyncCurrencyRatesCommand extends Command
{
    public function __construct(
        private readonly CurrencyRateProviderInterface $rateProvider,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable();

        $io->title(\sprintf('Синхронизация курсов валют ЦБ РФ на %s', $today->format('Y-m-d')));

        $tableRows = [];
        $hasErrors = false;

        foreach (CurrencyEnum::cases() as $currency) {
            try {
                $rate = $this->rateProvider->getRate($currency, $today);

                $tableRows[] = [
                    $currency->value,
                    \sprintf('%.4f RUB', $rate),
                    'Успешно (в кэше)',
                ];
            } catch (\Throwable $e) {
                $hasErrors = true;

                $tableRows[] = [
                    $currency->value,
                    '-',
                    \sprintf('Ошибка: %s', $e->getMessage()),
                ];
            }
        }

        $io->table(['Валюта', 'Курс к рублю', 'Статус'], $tableRows);

        if ($hasErrors) {
            $io->warning('Некоторые курсы не удалось получить.');

            return Command::FAILURE;
        }

        $io->success('Курсы валют успешно получены и сохранены в кэш.');

        return Command::SUCCESS;
    }
}
