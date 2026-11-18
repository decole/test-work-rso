<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class SupportedInsuredAmount extends Constraint
{
    public string $message = 'Недопустимая страховая сумма. Доступны: {{ choices }}.';

    public function validatedBy(): string
    {
        return SupportedInsuredAmountValidator::class;
    }
}
