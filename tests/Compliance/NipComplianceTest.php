<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Validation\NipEventValidatorAdapter;
use PHPUnit\Framework\TestCase;

final class NipComplianceTest extends TestCase
{
    private NipEventValidatorAdapter $validator;
    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->validator = new NipEventValidatorAdapter();
        $this->keyPair = KeyPair::generate();
    }

    public function testNip01BasicEventCompliance(): void
    {
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('Hello Nostr!')
        );
        $signedEvent = $event->sign($this->keyPair->getPrivateKey());

        $this->validator->validateNip01Compliance($signedEvent);

        // Verify the event passes validation
        $this->assertTrue($signedEvent->isSigned());
        $this->assertTrue($signedEvent->verify());

    }

    public function testNip02ContactListCompliance(): void
    {
        $tags = new TagCollection([
            Tag::pubkey('contact-pubkey-1'),
            Tag::pubkey('contact-pubkey-2')
        ]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::followList(),
            $tags,
            EventContent::fromString('contact list')
        );
        $signedEvent = $event->sign($this->keyPair->getPrivateKey());

        $this->validator->validateNip02Compliance($signedEvent);

        // Verify it's a follow list event
        $this->assertSame(3, $signedEvent->getKind()->toInt());
        $this->assertTrue($signedEvent->getTags()->hasType(\Innis\Nostr\Core\Domain\ValueObject\Tag\TagType::pubkey()));

    }

    public function testNip04EncryptedDirectMessageCompliance(): void
    {
        $tags = new TagCollection([
            Tag::pubkey('recipient-pubkey')
        ]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::encryptedDirectMessage(),
            $tags,
            EventContent::fromString('encrypted-content')
        );
        $signedEvent = $event->sign($this->keyPair->getPrivateKey());

        $this->validator->validateNip04Compliance($signedEvent);

        // Verify it's an encrypted DM with p tag
        $this->assertSame(4, $signedEvent->getKind()->toInt());
        $this->assertTrue($signedEvent->getTags()->hasType(\Innis\Nostr\Core\Domain\ValueObject\Tag\TagType::pubkey()));

    }

    public function testNip09EventDeletionCompliance(): void
    {
        $tags = new TagCollection([
            Tag::event('event-to-delete-id')
        ]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::eventDeletion(),
            $tags,
            EventContent::fromString('spam')
        );
        $signedEvent = $event->sign($this->keyPair->getPrivateKey());

        $this->validator->validateNip09Compliance($signedEvent);

        // Verify it's a deletion event with e tag
        $this->assertSame(5, $signedEvent->getKind()->toInt());
        $this->assertTrue($signedEvent->getTags()->hasType(\Innis\Nostr\Core\Domain\ValueObject\Tag\TagType::event()));

    }

    public function testEventIdCalculationMatchesNip01Specification(): void
    {
        // Test with known values to ensure ID calculation is correct
        $pubkey = str_repeat('a', 64);
        $createdAt = 1234567890;
        $kind = 1;
        $tags = [];
        $content = 'test';

        $event = Event::fromArray([
            'pubkey' => $pubkey,
            'created_at' => $createdAt,
            'kind' => $kind,
            'tags' => $tags,
            'content' => $content
        ]);

        $calculatedId = $event->calculateId();

        // The ID should be a valid SHA-256 hash
        $this->assertSame(64, \strlen($calculatedId->toHex()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $calculatedId->toHex());

        // ID calculation should be deterministic
        $this->assertTrue($calculatedId->equals($event->calculateId()));
    }

    public function testSignatureVerificationMatchesNip01Specification(): void
    {
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('test signature')
        );
        $signedEvent = $event->sign($this->keyPair->getPrivateKey());

        // Signature should verify correctly
        $this->assertTrue($signedEvent->verify());

        // Signature should be valid format
        $signature = $signedEvent->getSignature();
        $this->assertNotNull($signature);
        $this->assertSame(128, \strlen($signature->toHex()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{128}$/', $signature->toHex());
    }
}
