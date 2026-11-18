<?php

declare(strict_types=1);

namespace App\Validator;

use App\Dto\Input\CalculateInsuranceInput;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidDateRangeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidDateRange) {
            throw new UnexpectedTypeException($constraint, ValidDateRange::class);
        }

        if (null === $value) {
            return;
        }

        if (!$value instanceof CalculateInsuranceInput) {
            throw new UnexpectedValueException($value, CalculateInsuranceInput::class);
        }

        if (empty($value->startDate) || empty($value->endDate)) {
            return;
        }

        $startDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $value->startDate);
        $endDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $value->endDate);

        if (false === $startDate || false === $endDate) {
            return; // Handled by individual #[Assert\Date] constraints
        }

        if ($startDate > $endDate) {
            $this->context->buildViolation($constraint->message)
                ->atPath('endDate')
                ->addViolation();
        }
    }
}
