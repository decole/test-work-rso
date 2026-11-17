<?php

declare(strict_types=1);

namespace App\Exception;

final class UnsupportedInsuredAmountException extends DomainException
{
    /**
     * @param list<int> $supportedAmounts
     */
    public static function forAmount(int $amount, array $supportedAmounts): self
    {
        return new self(sprintf(
            'Страховая сумма %d не поддерживается. Допустимые суммы: %s.',
            $amount,
            implode(', ', $supportedAmounts)
        ));
    }
}

