<?php

declare(strict_types=1);

namespace PowerTranz\Exception;

/**
 * Thrown when the gateway returns a non-2xx HTTP response or a gateway-level error.
 */
class ApiException extends PowerTranzException
{
    private int $httpStatus;
    private string $responseBody;

    public function __construct(
        string $message,
        int $httpStatus = 0,
        string $responseBody = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
        $this->httpStatus   = $httpStatus;
        $this->responseBody = $responseBody;
    }

    public static function fromResponse(int $status, string $body): self
    {
        $decoded = json_decode($body, true);
        $message = (is_array($decoded) && isset($decoded['ResponseMessage']))
            ? $decoded['ResponseMessage']
            : sprintf('HTTP %d: Unexpected response from PowerTranz gateway.', $status);

        return new self($message, $status, $body);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
