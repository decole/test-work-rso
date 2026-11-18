<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Dto\Input\CalculateInsuranceInput;
use App\Validator\ValidDateRange;
use App\Validator\ValidDateRangeValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidDateRangeValidatorTest extends TestCase
{
    private function createValidator(): ValidatorInterface
    {
        $factory = new class implements ConstraintValidatorFactoryInterface {
            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                if ($constraint instanceof ValidDateRange) {
                    return new ValidDateRangeValidator();
                }

                $class = $constraint->validatedBy();
                /** @var ConstraintValidatorInterface $validator */
                $validator = new $class();

                return $validator;
            }
        };

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory($factory)
            ->getValidator();
    }

    public function testDoesNotBuildViolationWhenStartDateIsBeforeOrEqualEndDate(): void
    {
        $validator = $this->createValidator();

        $validInput = new CalculateInsuranceInput(30000, '2025-10-01', '2025-10-03', 'EUR');
        $violations = $validator->validate($validInput, new ValidDateRange());
        self::assertCount(0, $violations);

        $sameDayInput = new CalculateInsuranceInput(30000, '2025-10-01', '2025-10-01', 'EUR');
        $violationsSameDay = $validator->validate($sameDayInput, new ValidDateRange());
        self::assertCount(0, $violationsSameDay);
    }

    public function testBuildsViolationOnEndDateWhenStartDateIsAfterEndDate(): void
    {
        $validator = $this->createValidator();

        $invalidInput = new CalculateInsuranceInput(30000, '2025-10-05', '2025-10-01', 'EUR');
        $violations = $validator->validate($invalidInput, new ValidDateRange());

        self::assertCount(1, $violations);
        $violation = $violations[0] ?? null;
        self::assertNotNull($violation);
        self::assertSame('endDate', $violation->getPropertyPath());
        self::assertSame('Дата начала поездки не может быть позже даты окончания.', $violation->getMessage());
    }
}
