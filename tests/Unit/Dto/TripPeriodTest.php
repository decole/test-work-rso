<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\TripPeriod;
use App\Exception\InvalidDateRangeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TripPeriodTest extends TestCase
{
    #[DataProvider('provideValidDateRanges')]
    public function testCalculatesDaysCountCorrectly(string $startDate, string $endDate, int $expectedDays): void
    {
        $period = TripPeriod::fromStrings($startDate, $endDate);

        self::assertSame($expectedDays, $period->getDaysCount());
        self::assertSame($startDate, $period->startDate->format('Y-m-d'));
        self::assertSame($endDate, $period->endDate->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function provideValidDateRanges(): iterable
    {
        yield 'one day trip (start === end)' => ['2025-10-01', '2025-10-01', 1];
        yield 'three days trip from prompt' => ['2025-10-01', '2025-10-03', 3];
        yield 'full week trip' => ['2025-10-01', '2025-10-07', 7];
        yield 'crossing month boundary (Feb to Mar non-leap)' => ['2025-02-27', '2025-03-02', 4];
        yield 'crossing leap year boundary (Feb 28 to Mar 1 in 2028)' => ['2028-02-28', '2028-03-01', 3];
        yield 'crossing year boundary' => ['2025-12-30', '2026-01-02', 4];
    }

    public function testThrowsExceptionWhenStartDateIsAfterEndDate(): void
    {
        $this->expectException(InvalidDateRangeException::class);
        $this->expectExceptionMessage('Дата начала поездки (2025-10-05) не может быть позже даты окончания (2025-10-01)');

        TripPeriod::fromStrings('2025-10-05', '2025-10-01');
    }

    public function testThrowsExceptionWhenDateFormatIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Некорректный формат даты');

        TripPeriod::fromStrings('invalid-date', '2025-10-03');
    }
}

