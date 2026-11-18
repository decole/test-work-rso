<?php

declare(strict_types=1);

namespace App\Validator;

use App\Service\Calculator\Coefficient\DailyCoefficientResolverInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class SupportedInsuredAmountValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DailyCoefficientResolverInterface $coefficientResolver,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SupportedInsuredAmount) {
            throw new UnexpectedTypeException($constraint, SupportedInsuredAmount::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_int($value) && !ctype_digit((string) $value)) {
            return;
        }

        $intAmount = (int) $value;

        if (!$this->coefficientResolver->supports($intAmount)) {
            $supportedAmounts = $this->coefficientResolver->getSupportedAmounts();

            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ choices }}', implode(', ', $supportedAmounts))
                ->addViolation();
        }
    }
}

