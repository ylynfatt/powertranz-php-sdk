<?php

declare(strict_types=1);

namespace PowerTranz\Model\Response;

use PowerTranz\Enum\IsoResponseCode;
use PowerTranz\Enum\TransactionType;

/**
 * Base response for all SPI and transaction operations.
 *
 * Properties are initialised via {@see hydrate()} called from the {@see fromArray()}
 * static factory. They are not declared readonly because PHP requires readonly
 * properties to be assigned in the constructor; instead, they are exposed as
 * public read-only state with no public setters.
 *
 * Concrete response classes extend this with operation-specific fields.
 * The raw decoded response array is preserved in for forward-compatibility —
 * access unknown fields via {@see getRaw()}.
 */
class SpiResponse
{
    public IsoResponseCode $isoResponseCode;
    public string $responseCode;
    public string $responseMessage;
    public string $transactionIdentifier;
    public ?string $orderIdentifier;
    public ?string $referenceNumber;
    public ?string $authorizationCode;
    public ?string $panToken;
    public ?string $spiToken;
    public ?string $cardBrand;
    public ?TransactionType $transactionType;
    public float $totalAmount;
    public bool $approved;
    public bool $requiresThreeDsChallenge;

    /** @var array<string, mixed> */
    private array $rawData = [];

    /**
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $instance = new static(); // @phpstan-ignore new.static
        $instance->hydrate($data);

        return $instance;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function hydrate(array $data): void
    {
        $this->rawData = $data;

        $this->responseCode          = (string) ($data['ResponseCode'] ?? '');
        $this->responseMessage       = (string) ($data['ResponseMessage'] ?? '');
        $this->transactionIdentifier = (string) ($data['TransactionIdentifier'] ?? '');
        $this->orderIdentifier       = isset($data['OrderIdentifier']) ? (string) $data['OrderIdentifier'] : null;
        $this->referenceNumber       = isset($data['ReferenceNumber']) ? (string) $data['ReferenceNumber'] : null;
        $this->authorizationCode     = isset($data['AuthorizationCode']) ? (string) $data['AuthorizationCode'] : null;
        $this->panToken              = isset($data['PanToken']) && $data['PanToken'] !== '' ? (string) $data['PanToken'] : null;
        $this->spiToken              = isset($data['SpiToken']) && $data['SpiToken'] !== '' ? (string) $data['SpiToken'] : null;
        $this->cardBrand             = isset($data['CardBrand']) ? (string) $data['CardBrand'] : null;
        $this->totalAmount           = (float) ($data['TotalAmount'] ?? 0.0);

        $isoCode               = IsoResponseCode::tryFrom((string) ($data['IsoResponseCode'] ?? ''));
        $this->isoResponseCode = $isoCode ?? IsoResponseCode::DO_NOT_HONOUR;

        $this->approved                 = $this->isoResponseCode->isApproved();
        $this->requiresThreeDsChallenge = $this->isoResponseCode->requires3dsChallenge();

        $txType                = isset($data['TransactionType']) ? TransactionType::tryFrom((int) $data['TransactionType']) : null;
        $this->transactionType = $txType;
    }

    /**
     * Access a raw field from the decoded API response.
     * Useful for fields not yet modelled as typed properties.
     */
    public function getRaw(string $key, mixed $default = null): mixed
    {
        return $this->rawData[$key] ?? $default;
    }
}
