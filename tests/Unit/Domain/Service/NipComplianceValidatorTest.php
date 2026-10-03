<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\NipComplianceValidator;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;

final class NipComplianceValidatorTest extends TestCase
{
    private const string TARGET_EVENT_ID = 'b1b2b3b4b5b6b7b8b9b0c1c2c3c4c5c6c7c8c9c0d1d2d3d4d5d6d7d8d9d0e1e2';

    public function testTheNip09ShapeCheckNeverVerifiesTheSignature(): void
    {
        $signer = $this->createMock(SignatureServiceInterface::class);
        $signer->expects($this->never())->method('verify');

        new NipComplianceValidator($signer)->validateNip09Shape($this->event(EventKind::EVENT_DELETION, [['e', self::TARGET_EVENT_ID]]));
    }

    public function testTheNip09ShapeCheckRefusesAnotherKind(): void
    {
        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('NIP-09 events must be kind 5');

        $this->validator()->validateNip09Shape($this->event(EventKind::TEXT_NOTE, [['e', self::TARGET_EVENT_ID]]));
    }

    public function testTheNip09ShapeCheckRefusesADeletionNamingNoTarget(): void
    {
        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('NIP-09 events must have at least one e or a tag');

        $this->validator()->validateNip09Shape($this->event(EventKind::EVENT_DELETION, []));
    }

    public function testTheNip09ShapeCheckAcceptsACoordinateWhoseRelayHintIsMalformed(): void
    {
        $coordinate = '30023:'.KeyMother::alicePublicKey()->toHex().':slug';

        $this->validator()->validateNip09Shape($this->event(EventKind::EVENT_DELETION, [['a', $coordinate, 'not a relay']]));

        $this->addToAssertionCount(1);
    }

    public function testTheNip09ShapeCheckRefusesADeletionWhoseOnlyCoordinateIsMalformed(): void
    {
        $this->expectException(InvalidEventException::class);

        $this->validator()->validateNip09Shape($this->event(EventKind::EVENT_DELETION, [['a', '30023:not-a-pubkey:slug']]));
    }

    public function testNip09ComplianceAddsTheSignatureBaselineToTheShape(): void
    {
        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event signature is invalid');

        new NipComplianceValidator(FakeSignatureService::rejecting())
            ->validateNip09Compliance($this->event(EventKind::EVENT_DELETION, [['e', self::TARGET_EVENT_ID]]));
    }

    public function testNip01ComplianceDoesNotJudgeCreatedAt(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            KeyMother::alicePublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            createdAt: Timestamp::fromInt(4_000_000_000),
        ));

        $this->validator()->validateNip01Compliance($event);

        $this->addToAssertionCount(1);
    }

    private function validator(): NipComplianceValidator
    {
        return new NipComplianceValidator(FakeSignatureService::accepting());
    }

    /**
     * @param list<list<string>> $tags
     */
    private function event(int $kind, array $tags): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            KeyMother::alicePublicKey(),
            EventKind::fromInt($kind),
            tags: new TagCollection(array_map(Tag::fromArray(...), $tags)),
        ));
    }
}
