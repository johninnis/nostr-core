<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use InvalidArgumentException;

final readonly class Nip05Identifier
{
    public function __construct(
        private string $localPart,
        private string $domain,
    ) {
    }

    public static function fromString(string $identifier): self
    {
        $parts = explode('@', $identifier, 2);

        if (2 !== count($parts)) {
            throw new InvalidArgumentException('Invalid NIP-05 identifier format. Expected: name@domain.com');
        }

        [$localPart, $domain] = $parts;

        if (empty($localPart) || empty($domain)) {
            throw new InvalidArgumentException('NIP-05 identifier cannot have empty local part or domain');
        }

        return new self(trim($localPart), trim($domain));
    }

    public function getLocalPart(): string
    {
        return $this->localPart;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getWellKnownUrl(): string
    {
        return sprintf('https://%s/.well-known/nostr.json?name=%s', $this->domain, $this->localPart);
    }

    public function toString(): string
    {
        return $this->localPart.'@'.$this->domain;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
