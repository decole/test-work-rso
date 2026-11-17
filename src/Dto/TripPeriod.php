<?php

declare(strict_types=1);

namespace App\Dto;

use App\Exception\InvalidDateRangeException;
use DateTimeZone;

final readonly class TripPeriod
{
    public \DateTimeImmutable $startDate;
    public \DateTimeImmutable $endDate;
    public ?DateTimeZone $timezone;

    public function __construct(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ?DateTimeZone $timezone = null,
    ) {
        $normalizedStart = $startDate->setTime(hour: 0, minute: 0);
        $normalizedEnd = $endDate->setTime(hour: 0, minute: 0);

        if ($normalizedStart > $normalizedEnd) {
            throw InvalidDateRangeException::startDateAfterEndDate($normalizedStart, $normalizedEnd);
        }

        $this->startDate = $normalizedStart;
        $this->endDate = $normalizedEnd;
        $this->timezone = $timezone;
    }

    public static function fromStrings(string $startDate, string $endDate, ?DateTimeZone $timezone = null): self
    {
        $start = \DateTimeImmutable::createFromFormat(format: '!Y-m-d', datetime: $startDate, timezone: $timezone);
        $end = \DateTimeImmutable::createFromFormat(format: '!Y-m-d', datetime: $endDate, timezone: $timezone);

        if (false === $start || false === $end) {
            throw new \InvalidArgumentException('Некорректный формат даты (ожидается формат YYYY-mm-dd).');
        }

        return new self(startDate: $start, endDate: $end, timezone: $timezone);
    }

    public function getDaysCount(): int
    {
        return (int) $this->startDate->diff($this->endDate)->days + 1;
    }
}

