<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Override;
use Stringable;

final readonly class Nip05Identifier implements Stringable
{
    // Deliberate: rejects an out-of-charset local part (including any upper-case) rather than normalising it, so verification never matches a rewritten key — see ADR-0052
    private const string LOCAL_PART_PATTERN = '/^[a-z0-9._-]+$/D';
    private const string DOMAIN_PATTERN = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/iD';

    private function __construct(
        private string $localPart,
        private string $domain,
    ) {
    }

    public static function tryFromString(string $identifier): ?self
    {
        $parts = explode('@', $identifier, 2);

        if (2 !== count($parts)) {
            return null;
        }

        $localPart = trim($parts[0]);
        $domain = strtolower(trim($parts[1]));

        if ('' === $localPart || '' === $domain) {
            return null;
        }

        if (!preg_match(self::LOCAL_PART_PATTERN, $localPart)) {
            return null;
        }

        if (!preg_match(self::DOMAIN_PATTERN, $domain)) {
            return null;
        }

        if (false !== filter_var($domain, FILTER_VALIDATE_IP)) {
            return null;
        }

        return new self($localPart, $domain);
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
        return sprintf(
            'https://%s/.well-known/nostr.json?name=%s',
            $this->domain,
            rawurlencode($this->localPart),
        );
    }

    #[Override]
    public function __toString(): string
    {
        return $this->localPart.'@'.$this->domain;
    }
}
