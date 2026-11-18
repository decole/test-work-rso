<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidDateRange extends Constraint
{
    public string $message = 'Дата начала поездки не может быть позже даты окончания.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
