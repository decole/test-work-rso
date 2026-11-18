<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Input\CalculateInsuranceInput;
use App\Dto\Output\CalculateInsuranceOutput;
use App\Service\Calculator\InsuranceCalculatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class InsuranceCalculateController extends AbstractController
{
    #[Route('/api/v1/insurance/calculate', name: 'api_insurance_calculate', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload]
        CalculateInsuranceInput $input,
        InsuranceCalculatorInterface $calculator,
    ): JsonResponse {
        $result = $calculator->calculate($input->toContext());

        return $this->json(CalculateInsuranceOutput::fromResult($result), Response::HTTP_OK);
    }
}
