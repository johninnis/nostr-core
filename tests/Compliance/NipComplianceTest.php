<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\NipComplianceValidator;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NipComplianceTest extends TestCase
{
    private const string TARGET_EVENT_ID = 'b1b2b3b4b5b6b7b8b9b0c1c2c3c4c5c6c7c8c9c0d1d2d3d4d5d6d7d8d9d0e1e2';
    private const string RECIPIENT_PUBKEY = 'f1f2f3f4f5f6f7f8f9f0a1a2a3a4a5a6a7a8a9a0b1b2b3b4b5b6b7b8b9b0c1c2';

    private NipComplianceValidator $validator;
    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->validator = new NipComplianceValidator(CryptoFixtures::signer());
        $this->keyPair = KeyPair::generate(CryptoFixtures::signer());
    }

    public function testNip01BasicEventCompliance(): void
    {
        $signedEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello Nostr!'),
            new TagCollection(),
            Timestamp::now(),
        )->sign($this->keyPair, CryptoFixtures::signer());

        $this->validator->validateNip01Compliance($signedEvent);

        $this->assertTrue($signedEvent->verify(CryptoFixtures::signer()));
    }

    public function testNip02ContactListCompliance(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['p', 'contact-pubkey-1']),
            Tag::fromArray(['p', 'contact-pubkey-2']),
        ]);

        $signedEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::FOLLOW_LIST),
            EventContent::fromString('contact list'),
            $tags,
            Timestamp::now(),
        )->sign($this->keyPair, CryptoFixtures::signer());

        $this->validator->validateNip02Compliance($signedEvent);

        $this->assertSame(3, $signedEvent->getKind()->toInt());
        $this->assertTrue($signedEvent->getTags()->hasType(TagType::pubkey()));
    }

    public function testNip04EncryptedDirectMessageCompliance(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['p', self::RECIPIENT_PUBKEY]),
        ]);

        $signedEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::ENCRYPTED_DIRECT_MESSAGE),
            EventContent::fromString('encrypted-content'),
            $tags,
            Timestamp::now(),
        )->sign($this->keyPair, CryptoFixtures::signer());

        $this->validator->validateNip04Compliance($signedEvent);

        $this->assertSame(4, $signedEvent->getKind()->toInt());
        $this->assertTrue($signedEvent->getTags()->hasType(TagType::pubkey()));
    }

    /**
     * @param list<list<string>> $tags
     */
    #[DataProvider('directMessagesWithoutAReadableRecipient')]
    public function testNip04RequiresAPTagNamingAPublicKey(array $tags): void
    {
        $signedEvent = $this->signed(EventKind::ENCRYPTED_DIRECT_MESSAGE, $tags);

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('p tag naming a public key');

        $this->validator->validateNip04Compliance($signedEvent);
    }

    /**
     * @return iterable<string, array{list<list<string>>}>
     */
    public static function directMessagesWithoutAReadableRecipient(): iterable
    {
        yield 'no p tag' => [[]];
        yield 'a p tag that is not a public key' => [[['p', 'recipient-pubkey']]];
        yield 'a p tag without a value' => [[['p']]];
    }

    /**
     * @param list<list<string>> $tags
     */
    #[DataProvider('deletionsWithoutAReadableTarget')]
    public function testNip09RequiresATargetThatParses(array $tags): void
    {
        $signedEvent = $this->signed(EventKind::EVENT_DELETION, $tags);

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('at least one e or a tag');

        $this->validator->validateNip09Compliance($signedEvent);
    }

    /**
     * @return iterable<string, array{list<list<string>>}>
     */
    public static function deletionsWithoutAReadableTarget(): iterable
    {
        yield 'an e tag that is not an event id' => [[['e', 'event-to-delete-id']]];
        yield 'an a tag that is not a coordinate' => [[['a', 'my-article']]];
        yield 'an a tag naming an author that is not a public key' => [[['a', '30023:alice:my-article']]];
        yield 'an e tag without a value' => [[['e']]];
    }

    public function testNip09AcceptsAReadableTargetBesideAnUnreadableOne(): void
    {
        $signedEvent = $this->signed(EventKind::EVENT_DELETION, [['e', 'event-to-delete-id'], ['e', self::TARGET_EVENT_ID]]);

        $this->validator->validateNip09Compliance($signedEvent);

        $this->assertTrue($signedEvent->isDeletion());
    }

    /**
     * @param list<list<string>> $tags
     */
    private function signed(int $kind, array $tags): Event
    {
        return Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt($kind),
            EventContent::fromString('content'),
            new TagCollection(array_map(Tag::fromArray(...), $tags)),
            Timestamp::now(),
        )->sign($this->keyPair, CryptoFixtures::signer());
    }

    public function testNip09EventDeletionCompliance(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['e', self::TARGET_EVENT_ID]),
            Tag::fromArray(['k', '1']),
        ]);

        $signedEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::EVENT_DELETION),
            EventContent::fromString('spam'),
            $tags,
            Timestamp::now(),
        )->sign($this->keyPair, CryptoFixtures::signer());

        $this->validator->validateNip09Compliance($signedEvent);

        $this->assertSame(5, $signedEvent->getKind()->toInt());
        $this->assertTrue($signedEvent->getTags()->hasType(TagType::event()));
    }

    public function testNip09EventDeletionWithATagCompliance(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['a', '30023:'.str_repeat('a', 64).':my-article']),
            Tag::fromArray(['k', '30023']),
        ]);

        $signedEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::EVENT_DELETION),
            EventContent::fromString('removing article'),
            $tags,
            Timestamp::now(),
        )->sign($this->keyPair, CryptoFixtures::signer());

        $this->validator->validateNip09Compliance($signedEvent);

        $this->assertSame(5, $signedEvent->getKind()->toInt());
    }

    public function testNip09EventDeletionRequiresEOrATag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['k', '1']),
        ]);

        $signedEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::EVENT_DELETION),
            EventContent::fromString('no targets'),
            $tags,
            Timestamp::now(),
        )->sign($this->keyPair, CryptoFixtures::signer());

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('at least one e or a tag');

        $this->validator->validateNip09Compliance($signedEvent);
    }

    public function testNip09EventDeletionAllowsMissingKTag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['e', self::TARGET_EVENT_ID]),
        ]);

        $signedEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::EVENT_DELETION),
            EventContent::fromString('missing k tag'),
            $tags,
            Timestamp::now(),
        )->sign($this->keyPair, CryptoFixtures::signer());

        $this->validator->validateNip09Compliance($signedEvent);

        $this->assertSame(5, $signedEvent->getKind()->toInt());
    }

    public function testNip09EventDeletionTargetingADeletionRequestIsValid(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['e', self::TARGET_EVENT_ID]),
            Tag::fromArray(['k', '5']),
        ]);

        $signedEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::EVENT_DELETION),
            EventContent::fromString('trying to delete a deletion'),
            $tags,
            Timestamp::now(),
        )->sign($this->keyPair, CryptoFixtures::signer());

        $this->validator->validateNip09Compliance($signedEvent);

        $this->assertSame(5, $signedEvent->getKind()->toInt());
    }

    public function testEventIdCalculationMatchesNip01Specification(): void
    {
        $rumour = Rumour::draft(
            PublicKey::tryFromHex(str_repeat('a', 64)) ?? throw new RuntimeException('Invalid test pubkey'),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test'),
            new TagCollection(),
            Timestamp::fromInt(1234567890),
        );

        $calculatedId = $rumour->getId();

        $this->assertSame(64, strlen($calculatedId->toHex()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $calculatedId->toHex());

        $this->assertTrue($calculatedId->equals($rumour->getId()));
    }

    public function testSignatureVerificationMatchesNip01Specification(): void
    {
        $signedEvent = Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('test signature'),
            new TagCollection(),
            Timestamp::now(),
        )->sign($this->keyPair, CryptoFixtures::signer());

        $this->assertTrue($signedEvent->verify(CryptoFixtures::signer()));

        $signature = $signedEvent->getSignature();
        $this->assertSame(128, strlen($signature->toHex()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{128}$/', $signature->toHex());
    }
}
