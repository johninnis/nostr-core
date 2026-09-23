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
    public function __construct(
        private string $method,
        private array $params = [],
    ) {
        if ('' === $method) {
            throw new InvalidArgumentException('A NIP-86 request must name a method');
        }
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

    /**
     * @param array<array-key, mixed> $data
     */
    public static function tryFromArray(array $data): ?self
    {
        $method = JsonWireFormat::stringField($data, 'method');

        if (null === $method || '' === $method) {
            return null;
        }

        $params = $data['params'] ?? [];

        if (!is_array($params) || !array_is_list($params)) {
            return null;
        }

        return new self($method, $params);
    }

    public static function tryFromJson(string $json): ?self
    {
        $data = JsonWireFormat::decodeArray($json);

        return null === $data ? null : self::tryFromArray($data);
    }
}
