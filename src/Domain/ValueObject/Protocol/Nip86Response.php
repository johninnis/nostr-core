<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use InvalidArgumentException;

final readonly class Nip86Response
{
    private function __construct(
        private mixed $result,
        private ?string $error,
    ) {
    }

    public static function success(mixed $result): self
    {
        return new self($result, null);
    }

    public static function failure(string $error): self
    {
        return self::tryFailure($error) ?? throw new InvalidArgumentException('A NIP-86 failure must carry an error message');
    }

    public function isSuccess(): bool
    {
        return null === $this->error;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @return array{result: mixed}|array{error: string}
     */
    // Deliberate: exactly one of result and error is emitted, never both, because a reply carrying each would let two readers disagree about whether the call succeeded — see ADR-0069
    public function toArray(): array
    {
        return null === $this->error
            ? ['result' => $this->result]
            : ['error' => $this->error];
    }

    public function toJson(): string
    {
        return JsonWireFormat::encode($this->toArray(), JsonWireFormat::MESSAGE);
    }

    public static function tryFromArray(mixed $data): ?self
    {
        $fields = JsonWireFormat::objectFields($data);

        if (null === $fields) {
            return null;
        }

        $error = $fields['error'] ?? null;

        if (null !== $error) {
            return is_string($error) ? self::tryFailure($error) : null;
        }

        return array_key_exists('result', $fields) ? self::success($fields['result']) : null;
    }

    public static function tryFromJson(string $json): ?self
    {
        return self::tryFromArray(JsonWireFormat::decode($json));
    }

    private static function tryFailure(string $error): ?self
    {
        return '' === $error ? null : new self(null, $error);
    }
}
