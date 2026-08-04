<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class KeyPairTest extends TestCase
{
    // Deliberate: a caller-supplied pair could hold a public key that does not derive from its private key, and Rumour::sign would then mint an event whose signature never verifies against its own pubkey — see ADR-0061
    public function testTheConstructorIsNotPublic(): void
    {
        $constructor = new ReflectionClass(KeyPair::class)->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertFalse($constructor->isPublic());
    }
}
