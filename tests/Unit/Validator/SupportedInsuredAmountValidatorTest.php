<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Service\Calculator\Coefficient\DailyCoefficientResolverInterface;
use App\Validator\SupportedInsuredAmount;
use App\Validator\SupportedInsuredAmountValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SupportedInsuredAmountValidatorTest extends TestCase
{
    private function createValidator(DailyCoefficientResolverInterface $resolver): ValidatorInterface
    {
        $factory = new class ($resolver) implements ConstraintValidatorFactoryInterface {
            public function __construct(private readonly DailyCoefficientResolverInterface $resolver) {}

            public function getInstance(Constraint $constraint): ConstraintValidatorInterface
            {
                if ($constraint instanceof SupportedInsuredAmount) {
                    return new SupportedInsuredAmountValidator($this->resolver);
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

    public function testDoesNotBuildViolationWhenAmountIsSupported(): void
    {
        $mockResolver = $this->createMock(DailyCoefficientResolverInterface::class);
        $mockResolver->expects($this->once())->method('supports')->with(30000)->willReturn(true);

        $validator = $this->createValidator($mockResolver);
        $violations = $validator->validate(30000, new SupportedInsuredAmount());

        self::assertCount(0, $violations);
    }

    public function testBuildsViolationWhenAmountIsUnsupported(): void
    {
        $mockResolver = $this->createMock(DailyCoefficientResolverInterface::class);
        $mockResolver->expects($this->once())->method('supports')->with(10000)->willReturn(false);
        $mockResolver->expects($this->once())->method('getSupportedAmounts')->willReturn([30000, 50000]);

        $validator = $this->createValidator($mockResolver);
        $violations = $validator->validate(10000, new SupportedInsuredAmount());

        self::assertCount(1, $violations);
        $violation = $violations[0] ?? null;
        self::assertNotNull($violation);
        self::assertSame('Недопустимая страховая сумма. Доступны: 30000, 50000.', $violation->getMessage());
    }
}
