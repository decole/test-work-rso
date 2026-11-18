<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\CurrencyRateUnavailableException;
use App\Exception\DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener]
final readonly class ExceptionListener
{
    private const string DEV_ENV = 'dev';

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $previous = $exception->getPrevious();

        // 1. Validation errors (including from #[MapRequestPayload])
        $validationException = null;

        if ($exception instanceof ValidationFailedException) {
            $validationException = $exception;
        } elseif ($previous instanceof ValidationFailedException) {
            $validationException = $previous;
        }

        if (null !== $validationException) {
            $errorList = [];

            foreach ($validationException->getViolations() as $violation) {
                $errorList[] = [
                    'type' => 'validation',
                    'property' => $this->toSnakeCase($violation->getPropertyPath()),
                    'message' => (string) $violation->getMessage(),
                ];
            }

            $response = (new JsonResponse())
                ->setData([
                    'type' => 'ERROR',
                    'data' => $errorList,
                ])
                ->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

            $event->setResponse($response);

            return;
        }

        // 2. Denormalization type errors (e.g. string passed instead of int)
        if ($previous instanceof PartialDenormalizationException) {
            $errorList = [];

            foreach ($previous->getErrors() as $error) {
                $errorList[] = [
                    'type' => 'validation',
                    'property' => $this->toSnakeCase((string) $error->getPath()),
                    'message' => sprintf(
                        'Некорректный тип данных. Ожидалось: %s.',
                        implode(', ', $error->getExpectedTypes() ?? ['валидное значение'])
                    ),
                ];
            }

            $response = new JsonResponse()
                ->setData([
                    'type' => 'ERROR',
                    'data' => $errorList,
                ])
                ->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);

            $event->setResponse($response);

            return;
        }

        // 3. Structured logging (warning for 4xx client errors, critical for 5xx server errors)
        $isClientError = $exception instanceof DomainException
            || $exception instanceof \InvalidArgumentException
            || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500);

        if ($isClientError) {
            $this->logger?->warning($exception->getMessage(), [
                'exception' => $exception,
            ]);
        } else {
            $this->logger?->critical($exception->getMessage(), [
                'exception' => $exception,
            ]);
        }

        // 4. Determine HTTP status code
        $statusCode = match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof CurrencyRateUnavailableException => Response::HTTP_SERVICE_UNAVAILABLE, // 503
            $exception instanceof DomainException,
            $exception instanceof \InvalidArgumentException => Response::HTTP_BAD_REQUEST, // 400

            default => Response::HTTP_INTERNAL_SERVER_ERROR, // 500
        };

        // 5. Build JSON error response payload
        $data = [
            'type' => 'exception',
            'message' => $this->getSecuredMessage($exception),
        ];

        if (self::DEV_ENV === $this->environment) {
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();
        }

        if (0 !== $exception->getCode()) {
            $data['errorCode'] = $exception->getCode();
        }

        $response = (new JsonResponse())
            ->setData([
                'type' => 'ERROR',
                'data' => $data,
            ])
            ->setStatusCode($statusCode);

        if ($exception instanceof HttpExceptionInterface) {
            $response->headers->replace($exception->getHeaders());
        }

        $event->setResponse($response);
    }

    private function getSecuredMessage(\Throwable $exception): string
    {
        if (self::DEV_ENV === $this->environment || 'test' === $this->environment) {
            return $exception->getMessage();
        }

        return match (true) {
            $exception instanceof CurrencyRateUnavailableException => 'Курс валюты временно недоступен.',
            $exception instanceof DomainException,
            $exception instanceof \InvalidArgumentException => $exception->getMessage(),
            $exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500 => $exception->getMessage(),

            default => 'Ошибка на стороне сервера.',
        };
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}

