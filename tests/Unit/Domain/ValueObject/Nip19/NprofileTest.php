<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nprofile;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Tests\Support\RelayUrlMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NprofileTest extends TestCase
{
    private const string PUBKEY_HEX = '79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798';

    public function testRefusesAProfileWhoseEncodingWouldPassTheNip19Bound(): void
    {
        $pubkey = PublicKey::tryFromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey');

        $this->assertNull(Nprofile::tryFromPublicKey($pubkey, RelayUrlMother::beyondTheNip19EncodingBound()));
    }

    public function testKeepsARelaySuppliedTwiceOnceAsItsDecoderDoes(): void
    {
        $relay = RelayUrl::fromString('wss://relay.example.com');

        $nprofile = Nprofile::tryFromPublicKey($this->pubkey(), new RelayUrlCollection([$relay, $relay]));

        $this->assertSame(['wss://relay.example.com'], $nprofile?->getRelays()->toStrings());
    }

    public function testRoundTripsARelaySuppliedTwiceToTheSameRelays(): void
    {
        $relay = RelayUrl::fromString('wss://relay.example.com');
        $nprofile = Nprofile::tryFromPublicKey($this->pubkey(), new RelayUrlCollection([$relay, $relay]));
        $this->assertNotNull($nprofile);

        $decoded = Nprofile::tryFromBech32($nprofile->toBech32());

        $this->assertSame($nprofile->getRelays()->toStrings(), $decoded?->getRelays()->toStrings());
    }

    private function pubkey(): PublicKey
    {
        return PublicKey::tryFromHex(self::PUBKEY_HEX) ?? throw new RuntimeException('Invalid test pubkey');
    }
}
