<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Failure\NutzapVerificationFailure;
use Innis\Nostr\Core\Domain\Service\NutzapVerifier;
use Innis\Nostr\Core\Tests\Support\NutzapMother;
use PHPUnit\Framework\TestCase;

final class NutzapVerifierTest extends TestCase
{
    private const string STRANGER = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    public function testANutzapPayingAMintItsRecipientListsInProofsLockedToItsKeyVerifies(): void
    {
        $this->assertNull(new NutzapVerifier()->verify(NutzapMother::payingNutzap(), NutzapMother::recipientsInfo()));
    }

    public function testEveryProofLockedToTheRecipientsKeyVerifies(): void
    {
        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(5)]);

        $this->assertNull(new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testALockWrittenWithAnOddPrefixVerifiesAsTheSameKey(): void
    {
        $nutzap = NutzapMother::nutzap([['p', NutzapMother::RECIPIENT], ['u', NutzapMother::MINT], NutzapMother::proofTag(21, '03'.NutzapMother::P2PK_KEY)]);

        $this->assertNull(new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testInfoAuthoredBySomeoneOtherThanTheRecipientIsARecipientMismatch(): void
    {
        $this->assertSame(
            NutzapVerificationFailure::RecipientMismatch,
            new NutzapVerifier()->verify(NutzapMother::payingNutzap(), NutzapMother::recipientsInfo(self::STRANGER)),
        );
    }

    public function testANutzapNamingNoRecipientIsARecipientMismatch(): void
    {
        $nutzap = NutzapMother::nutzap([['u', NutzapMother::MINT], NutzapMother::proofTag(21)]);

        $this->assertSame(NutzapVerificationFailure::RecipientMismatch, new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testAMintTheInfoDoesNotListIsNotListed(): void
    {
        $nutzap = NutzapMother::nutzap([['p', NutzapMother::RECIPIENT], ['u', 'https://other.example'], NutzapMother::proofTag(21)]);

        $this->assertSame(NutzapVerificationFailure::MintNotListed, new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testAListedMintWrittenAnotherWayIsNotListed(): void
    {
        $nutzap = NutzapMother::nutzap([['p', NutzapMother::RECIPIENT], ['u', 'HTTPS://mint.example'], NutzapMother::proofTag(21)]);

        $this->assertSame(NutzapVerificationFailure::MintNotListed, new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testANutzapNamingNoMintIsNotListed(): void
    {
        $nutzap = NutzapMother::nutzap([['p', NutzapMother::RECIPIENT], NutzapMother::proofTag(21)]);

        $this->assertSame(NutzapVerificationFailure::MintNotListed, new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testANutzapWithNoProofStatingAnAmountHasNoProof(): void
    {
        $nutzap = NutzapMother::nutzap([['p', NutzapMother::RECIPIENT], ['u', NutzapMother::MINT], ['proof', 'not a proof']]);

        $this->assertSame(NutzapVerificationFailure::NoProof, new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testAProofLockedToAnotherKeyIsNotLockedToTheRecipient(): void
    {
        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(5, '02'.NutzapMother::OTHER_P2PK_KEY)]);

        $this->assertSame(NutzapVerificationFailure::ProofNotLockedToRecipient, new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testAProofLockedToTheRecipientsNostrKeyIsNotLockedToTheRecipient(): void
    {
        $nutzap = NutzapMother::payingNutzap([NutzapMother::proofTag(5, '02'.NutzapMother::RECIPIENT)]);

        $this->assertSame(NutzapVerificationFailure::ProofNotLockedToRecipient, new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testAProofTheSenderCanAlsoSpendIsNotLockedToTheRecipient(): void
    {
        $secret = json_encode(['P2PK', ['nonce' => 'n', 'data' => '02'.NutzapMother::P2PK_KEY, 'tags' => [['pubkeys', '02'.NutzapMother::OTHER_P2PK_KEY]]]], JSON_THROW_ON_ERROR);
        $nutzap = NutzapMother::payingNutzap([['proof', json_encode(['amount' => 5, 'secret' => $secret], JSON_THROW_ON_ERROR)]]);

        $this->assertSame(NutzapVerificationFailure::ProofNotLockedToRecipient, new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testTheRecipientIsCheckedBeforeTheMint(): void
    {
        $nutzap = NutzapMother::nutzap([['p', self::STRANGER], ['u', 'https://other.example'], NutzapMother::proofTag(21)]);

        $this->assertSame(NutzapVerificationFailure::RecipientMismatch, new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }

    public function testTheMintIsCheckedBeforeTheProofs(): void
    {
        $nutzap = NutzapMother::nutzap([['p', NutzapMother::RECIPIENT], ['u', 'https://other.example']]);

        $this->assertSame(NutzapVerificationFailure::MintNotListed, new NutzapVerifier()->verify($nutzap, NutzapMother::recipientsInfo()));
    }
}
