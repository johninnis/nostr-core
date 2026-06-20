<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Failure\Nip05VerificationFailureReason;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05Identifier;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Nip05VerificationResult;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip05VerificationResultTest extends TestCase
{
    private const VALID_PUBKEY_HEX = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';

    public function testSuccessCreatesValidResult(): void
    {
        $identifier = Nip05Identifier::fromString('alice@example.com') ?? throw new RuntimeException('Invalid test identifier');
        $pubkey = PublicKey::fromHex(self::VALID_PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey');

        $result = Nip05VerificationResult::success($identifier, $pubkey);

        $this->assertTrue($result->isValid());
        $this->assertNull($result->getFailureReason());
        $this->assertSame($identifier, $result->getIdentifier());
        $this->assertSame($pubkey, $result->getPubkey());
    }

    public function testFailureCreatesInvalidResult(): void
    {
        $identifier = Nip05Identifier::fromString('bob@example.com') ?? throw new RuntimeException('Invalid test identifier');
        $pubkey = PublicKey::fromHex(self::VALID_PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey');

        $result = Nip05VerificationResult::failure($identifier, $pubkey, Nip05VerificationFailureReason::PubkeyMismatch);

        $this->assertFalse($result->isValid());
        $this->assertSame(Nip05VerificationFailureReason::PubkeyMismatch, $result->getFailureReason());
        $this->assertSame($identifier, $result->getIdentifier());
        $this->assertSame($pubkey, $result->getPubkey());
    }
}
