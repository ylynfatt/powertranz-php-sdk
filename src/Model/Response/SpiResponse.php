<?php

declare(strict_types=1);

namespace PowerTranz\Model\Response;

use Brick\Money\Money;
use PowerTranz\Enum\CurrencyCode;
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
 * The {@see $totalAmount} is a {@see Money} object when the API response includes
 * a recognisable currency code, or {@code null} when the currency cannot be resolved
 * (forward-compatibility).  The raw decimal string is always accessible via
 * {@see getRaw()}.
 *
 * Concrete response classes extend this with operation-specific fields.
 * The raw decoded response array is preserved for forward-compatibility —
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

    /**
     * The transaction amount as an arbitrary-precision Money object.
     *
     * {@code null} when the API response omits the currency code or uses a code
     * not currently in {@see CurrencyCode}.  Use {@see getRaw('TotalAmount')} for
     * the raw numeric value in that case.
     */
    public ?Money $totalAmount;

    public bool $approved;

    /**
     * True when the gateway returned RedirectData (IsoResponseCode SP4 or HP0)
     * that must be rendered in an iframe before the flow can continue.
     */
    public bool $requiresRedirect;

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

        $isoCode               = IsoResponseCode::tryFrom((string) ($data['IsoResponseCode'] ?? ''));
        $this->isoResponseCode = $isoCode ?? IsoResponseCode::DO_NOT_HONOUR;

        $this->approved         = $this->isoResponseCode->isApproved();
        $this->requiresRedirect = $this->isoResponseCode->requiresRedirect();

        $txType                = isset($data['TransactionType']) ? TransactionType::tryFrom((int) $data['TransactionType']) : null;
        $this->transactionType = $txType;

        $this->totalAmount = $this->hydrateAmount($data);
    }

    /**
     * Build a {@see Money} from the raw response data.
     *
     * The API returns {@code TotalAmount} as a JSON number and {@code CurrencyCode}
     * as a numeric string (e.g. {@code "840"} for USD).  We resolve the ISO alpha
     * code via {@see CurrencyCode} so that {@see Money} can be constructed correctly.
     *
     * @param array<string, mixed> $data
     */
    private function hydrateAmount(array $data): ?Money
    {
        if (!isset($data['TotalAmount'])) {
            return null;
        }

        $numericCode  = (int) ($data['CurrencyCode'] ?? 0);
        $currencyEnum = $numericCode !== 0 ? CurrencyCode::tryFrom($numericCode) : null;

        if ($currencyEnum === null) {
            return null;
        }

        // JSON-decoded floats can lose precision; format to 2 dp before passing to Money.
        $amountStr = number_format((float) $data['TotalAmount'], 2, '.', '');

        return Money::of($amountStr, $currencyEnum->isoAlpha());
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
