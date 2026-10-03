<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use InvalidArgumentException;

final readonly class Nip86Request
{
    public const string MEDIA_TYPE = 'application/nostr+json+rpc';

    /**
     * @param list<mixed> $params
     */
    private function __construct(
        private string $method,
        private array $params,
    ) {
    }

    /**
     * @param array<array-key, mixed> $params
     */
    public static function tryFrom(string $method, array $params = []): ?self
    {
        return '' === $method || !array_is_list($params) ? null : new self($method, $params);
    }

    /**
     * @param list<mixed> $params
     */
    public static function from(string $method, array $params = []): self
    {
        return self::tryFrom($method, $params) ?? throw new InvalidArgumentException('A NIP-86 request must name a method');
    }

    // Deliberate: a string, not Nip86Method — a relay may serve methods the specification does not define — see ADR-0069
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return list<mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return array{method: string, params: list<mixed>}
     */
    public function toArray(): array
    {
        return ['method' => $this->method, 'params' => $this->params];
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

        $method = JsonWireFormat::stringField($fields, 'method');
        $params = array_key_exists('params', $fields) ? $fields['params'] : [];

        return null === $method || !is_array($params) ? null : self::tryFrom($method, $params);
    }

    public static function tryFromJson(string $json): ?self
    {
        return self::tryFromArray(JsonWireFormat::decode($json));
    }
}
