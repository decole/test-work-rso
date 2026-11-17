<?php

declare(strict_types=1);

namespace App\Exception;

final class InvalidDateRangeException extends DomainException
{
    public static function startDateAfterEndDate(\DateTimeInterface $startDate, \DateTimeInterface $endDate): self
    {
        return new self(sprintf(
            'Дата начала поездки (%s) не может быть позже даты окончания (%s).',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ));
    }
}

