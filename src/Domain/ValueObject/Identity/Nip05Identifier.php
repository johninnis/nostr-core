<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Service\Ipv4Literal;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Override;
use Stringable;

final readonly class Nip05Identifier implements Stringable
{
    // Deliberate: rejects an out-of-charset local part (including any upper-case) rather than normalising it, so verification never matches a rewritten key — see ADR-0091
    private const string LOCAL_PART_PATTERN = '/^[a-z0-9._-]+$/D';
    // Deliberate: checked before lower-casing, because Unicode folds U+212A KELVIN SIGN into "k" — see ADR-0091
    private const string ASCII_PATTERN = '/^[\x00-\x7F]*$/D';
    private const string DOMAIN_PATTERN = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/D';
    // Deliberate: the ECMAScript String.prototype.trim set, not \s, so this parser and nostr-core-ts accept the same inputs — see ADR-0091
    private const string SURROUNDING_WHITESPACE_PATTERN = '/^[\t\n\x{0B}\f\r\p{Zs}\x{2028}\x{2029}\x{FEFF}]+|[\t\n\x{0B}\f\r\p{Zs}\x{2028}\x{2029}\x{FEFF}]+$/uD';

    private function __construct(
        private string $localPart,
        private string $domain,
    ) {
    }

    public static function tryFromString(string $identifier): ?self
    {
        $trimmed = preg_replace(self::SURROUNDING_WHITESPACE_PATTERN, '', $identifier);

        if (null === $trimmed) {
            return null;
        }

        $parts = explode('@', $trimmed, 2);

        if (2 !== count($parts)) {
            return null;
        }

        [$localPart, $rawDomain] = $parts;

        if (!preg_match(self::ASCII_PATTERN, $rawDomain)) {
            return null;
        }

        $domain = strtolower($rawDomain);

        if (!preg_match(self::LOCAL_PART_PATTERN, $localPart)) {
            return null;
        }

        if (!preg_match(self::DOMAIN_PATTERN, $domain) || Ipv4Literal::endsInNumber($domain)) {
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

    public function getWellKnownUrl(): HttpUrl
    {
        return HttpUrl::fromString(sprintf(
            'https://%s/.well-known/nostr.json?name=%s',
            $this->domain,
            rawurlencode($this->localPart),
        ));
    }

    #[Override]
    public function __toString(): string
    {
        return $this->localPart.'@'.$this->domain;
    }
}
