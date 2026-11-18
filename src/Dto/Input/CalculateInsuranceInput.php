<?php

declare(strict_types=1);

namespace App\Dto\Input;

use App\Dto\CalculationContext;
use App\Dto\TripPeriod;
use App\Enum\CurrencyEnum;
use App\Validator\SupportedInsuredAmount;
use App\Validator\ValidDateRange;
use Symfony\Component\Validator\Constraints as Assert;

#[ValidDateRange]
final readonly class CalculateInsuranceInput
{
    public function __construct(
        #[Assert\NotNull(message: 'Страховая сумма обязательна.')]
        #[Assert\Type(type: 'integer', message: 'Страховая сумма должна быть целым числом.')]
        #[SupportedInsuredAmount]
        public int $insuredAmount,

        #[Assert\NotBlank(message: 'Дата начала поездки обязательна.')]
        #[Assert\Date(message: 'Некорректный формат даты начала поездки (ожидается YYYY-mm-dd).')]
        public string $startDate,

        #[Assert\NotBlank(message: 'Дата окончания поездки обязательна.')]
        #[Assert\Date(message: 'Некорректный формат даты окончания поездки (ожидается YYYY-mm-dd).')]
        public string $endDate,

        #[Assert\NotBlank(message: 'Код валюты обязателен.')]
        #[Assert\Length(exactly: 3, exactMessage: 'Код валюты должен состоять ровно из 3 символов.')]
        #[Assert\Choice(
            callback: [CurrencyEnum::class, 'values'],
            message: 'Недопустимая валюта. Доступны: {{ choices }}.'
        )]
        public string $currency,
    ) {
    }

    public function toContext(): CalculationContext
    {
        $period = TripPeriod::fromStrings($this->startDate, $this->endDate);
        $currencyEnum = CurrencyEnum::from(strtoupper($this->currency));

        return new CalculationContext($this->insuredAmount, $period, $currencyEnum);
    }
}

