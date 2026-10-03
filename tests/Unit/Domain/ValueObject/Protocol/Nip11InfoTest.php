<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class Nip11InfoTest extends TestCase
{
    private RelayUrl $relayUrl;

    protected function setUp(): void
    {
        $relayUrl = RelayUrl::tryFromString('wss://relay.example.com');
        $this->assertNotNull($relayUrl);
        $this->relayUrl = $relayUrl;
    }

    public function testCanCreateWithMinimalData(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl);

        $this->assertTrue($info->getRelayUrl()->equals($this->relayUrl));
        $this->assertNull($info->getName());
        $this->assertNull($info->getDescription());
        $this->assertNull($info->getPubkey());
        $this->assertNull($info->getContact());
        $this->assertNull($info->getSupportedNips());
        $this->assertNull($info->getSoftware());
        $this->assertNull($info->getVersion());
        $this->assertNull($info->getBanner());
        $this->assertNull($info->getIcon());
    }

    public function testToJsonWritesAnEmptyDocumentAsAJsonObject(): void
    {
        $this->assertSame('{}', Nip11Info::fromArray($this->relayUrl)->toJson());
    }

    public function testToJsonRoundTripsThroughTryFromJson(): void
    {
        $json = '{"name":"Relay","supported_nips":[1,11],"limitation":{},"description":"ünïcode/path"}';

        $this->assertSame($json, Nip11Info::tryFromJson($this->relayUrl, $json)?->toJson());
    }

    public function testTryFromJsonReadsAJsonObject(): void
    {
        $info = Nip11Info::tryFromJson($this->relayUrl, '{"name":"Example Relay","supported_nips":[1,11]}');

        $this->assertNotNull($info);
        $this->assertSame('Example Relay', $info->getName());
        $this->assertSame([1, 11], $info->getSupportedNips());
    }

    public function testTryFromJsonReadsAnEmptyJsonObjectAsADocumentStatingNothing(): void
    {
        $info = Nip11Info::tryFromJson($this->relayUrl, '{}');

        $this->assertNotNull($info);
        $this->assertNull($info->getName());
    }

    #[DataProvider('jsonThatIsNotAnObject')]
    public function testTryFromJsonRefusesJsonThatIsNotAnObject(string $json): void
    {
        $this->assertNull(Nip11Info::tryFromJson($this->relayUrl, $json));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function jsonThatIsNotAnObject(): iterable
    {
        yield 'malformed' => ['{"name":'];
        yield 'an array' => ['[{"name":"Example Relay"}]'];
        yield 'an empty array' => ['[]'];
        yield 'a string' => ['"Example Relay"'];
    }

    public function testCanCreateWithAllFields(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, [
            'name' => 'Test Relay',
            'description' => 'A test relay',
            'pubkey' => str_repeat('a', 64),
            'contact' => 'admin@example.com',
            'supported_nips' => [1, 11, 42],
            'software' => 'nostr-relay',
            'version' => '1.0.0',
            'banner' => 'https://example.com/banner.png',
            'icon' => 'https://example.com/icon.png',
        ]);

        $this->assertSame('Test Relay', $info->getName());
        $this->assertSame('A test relay', $info->getDescription());
        $this->assertSame(str_repeat('a', 64), $info->getPubkey()?->toHex());
        $this->assertSame('admin@example.com', $info->getContact());
        $this->assertSame([1, 11, 42], $info->getSupportedNips());
        $this->assertSame('nostr-relay', $info->getSoftware());
        $this->assertSame('1.0.0', $info->getVersion());
        $this->assertSame('https://example.com/banner.png', $info->getBanner());
        $this->assertSame('https://example.com/icon.png', $info->getIcon());
    }

    public function testFromArrayCreatesInstanceFromRelayData(): void
    {
        $data = [
            'name' => 'Test Relay',
            'description' => 'A test relay for testing',
            'pubkey' => str_repeat('b', 64),
            'contact' => 'hello@example.com',
            'supported_nips' => [1, 11],
            'software' => 'strfry',
            'version' => '2.0.0',
            'banner' => 'https://example.com/banner.jpg',
            'icon' => 'https://example.com/icon.jpg',
        ];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertTrue($info->getRelayUrl()->equals($this->relayUrl));
        $this->assertSame('Test Relay', $info->getName());
        $this->assertSame('A test relay for testing', $info->getDescription());
        $this->assertSame(str_repeat('b', 64), $info->getPubkey()?->toHex());
        $this->assertSame('hello@example.com', $info->getContact());
        $this->assertSame([1, 11], $info->getSupportedNips());
        $this->assertSame('strfry', $info->getSoftware());
        $this->assertSame('2.0.0', $info->getVersion());
        $this->assertSame('https://example.com/banner.jpg', $info->getBanner());
        $this->assertSame('https://example.com/icon.jpg', $info->getIcon());
    }

    public function testFromArrayHandlesMissingFields(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getName());
        $this->assertNull($info->getDescription());
        $this->assertNull($info->getPubkey());
        $this->assertNull($info->getContact());
        $this->assertNull($info->getSupportedNips());
        $this->assertNull($info->getSoftware());
        $this->assertNull($info->getVersion());
        $this->assertNull($info->getBanner());
        $this->assertNull($info->getIcon());
    }

    public function testToArrayReturnsRawDataPassedToConstructor(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, [
            'name' => 'Test Relay',
            'description' => 'A test relay',
        ]);

        $array = $info->toArray();

        $this->assertSame(['name' => 'Test Relay', 'description' => 'A test relay'], $array);
        $this->assertSame('Test Relay', $info->getName());
        $this->assertNull($info->getPubkey());
        $this->assertArrayNotHasKey('pubkey', $array);
    }

    public function testToArrayReturnsRawDataWhenCreatedViaFromArray(): void
    {
        $data = [
            'name' => 'Test Relay',
            'custom_field' => 'custom_value',
            'limitation' => ['max_limit' => 100],
        ];

        $info = Nip11Info::fromArray($this->relayUrl, $data);
        $array = $info->toArray();

        $this->assertSame($data, $array);
    }

    public function testGetSelfReturnsTheRelaysOwnPublicKey(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, ['self' => str_repeat('c', 64)]);

        $this->assertSame(str_repeat('c', 64), $info->getSelf()?->toHex());
    }

    public function testGetSelfReturnsNullWhenNotPresent(): void
    {
        $this->assertNull(Nip11Info::fromArray($this->relayUrl)->getSelf());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function selfValuesThatAreNotA32BytePublicKey(): iterable
    {
        yield 'not a string' => [42];
        yield 'not hex' => [str_repeat('z', 64)];
        yield 'too short' => [str_repeat('c', 62)];
        yield 'uppercase hex' => [str_repeat('C', 64)];
    }

    #[DataProvider('selfValuesThatAreNotA32BytePublicKey')]
    public function testGetSelfReturnsNullForAValueThatIsNotA32ByteHexPublicKey(mixed $self): void
    {
        $this->assertNull(Nip11Info::fromArray($this->relayUrl, ['self' => $self])->getSelf());
    }

    public function testGetLimitationReturnsLimitationData(): void
    {
        $data = [
            'limitation' => [
                'max_subscriptions' => 20,
                'max_limit' => 5000,
                'auth_required' => true,
                'payment_required' => false,
            ],
        ];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $limitation = $info->getLimitation();
        $this->assertNotNull($limitation);
        $this->assertSame(20, $limitation['max_subscriptions']);
    }

    public function testGetLimitationReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getLimitation());
    }

    public function testGetMaxSubscriptionsReturnsValue(): void
    {
        $data = [
            'limitation' => ['max_subscriptions' => 20],
        ];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame(20, $info->getMaxSubscriptions());
    }

    public function testGetMaxSubscriptionsReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getMaxSubscriptions());
    }

    public function testGetMaxLimitReturnsValue(): void
    {
        $data = [
            'limitation' => ['max_limit' => 5000],
        ];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame(5000, $info->getMaxLimit());
    }

    public function testGetMaxLimitReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getMaxLimit());
    }

    public function testIsAuthRequiredReturnsTrueWhenRequired(): void
    {
        $data = [
            'limitation' => ['auth_required' => true],
        ];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertTrue($info->isAuthRequired());
    }

    public function testIsAuthRequiredReturnsFalseByDefault(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertFalse($info->isAuthRequired());
    }

    public function testIsPaymentRequiredReturnsTrueWhenRequired(): void
    {
        $data = [
            'limitation' => ['payment_required' => true],
        ];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertTrue($info->isPaymentRequired());
    }

    public function testIsPaymentRequiredReturnsFalseByDefault(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertFalse($info->isPaymentRequired());
    }

    public function testGetPaymentsUrlReturnsUrl(): void
    {
        $data = ['payments_url' => 'https://example.com/payments'];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame('https://example.com/payments', $info->getPaymentsUrl());
    }

    public function testGetPaymentsUrlReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getPaymentsUrl());
    }

    public function testGetFeesReturnsFeeStructure(): void
    {
        $fees = ['admission' => [['amount' => 1000, 'unit' => 'msats']]];
        $data = ['fees' => $fees];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame($fees, $info->getFees());
    }

    public function testGetFeesReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getFees());
    }

    public function testGetTermsOfServiceReturnsUrl(): void
    {
        $data = ['terms_of_service' => 'https://example.com/tos'];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame('https://example.com/tos', $info->getTermsOfService());
    }

    public function testGetTermsOfServiceReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getTermsOfService());
    }

    public function testFromArrayCoercesNonStringTopLevelFieldsToNull(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, [
            'name' => 42,
            'description' => ['not', 'a', 'string'],
            'pubkey' => null,
            'software' => true,
            'banner' => 3.14,
            'icon' => new stdClass(),
        ]);

        $this->assertNull($info->getName());
        $this->assertNull($info->getDescription());
        $this->assertNull($info->getPubkey());
        $this->assertNull($info->getSoftware());
        $this->assertNull($info->getBanner());
        $this->assertNull($info->getIcon());
    }

    public function testFromArrayCoercesNonArraySupportedNipsToNull(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, [
            'supported_nips' => 'not-an-array',
        ]);

        $this->assertNull($info->getSupportedNips());
    }

    public function testLimitationAccessorsReturnNullWhenLimitationIsNotArray(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, [
            'limitation' => 'unexpected-string',
        ]);

        $this->assertNull($info->getLimitation());
        $this->assertNull($info->getMaxSubscriptions());
        $this->assertNull($info->getMaxLimit());
        $this->assertFalse($info->isAuthRequired());
        $this->assertFalse($info->isPaymentRequired());
    }

    public function testLimitationNumericAccessorsReturnNullWhenFieldIsNotInt(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, [
            'limitation' => [
                'max_subscriptions' => '20',
                'max_limit' => 100.5,
            ],
        ]);

        $this->assertNull($info->getMaxSubscriptions());
        $this->assertNull($info->getMaxLimit());
    }

    public function testLimitationBoolAccessorsReturnFalseForTruthyNonBool(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, [
            'limitation' => [
                'auth_required' => 'yes',
                'payment_required' => 1,
            ],
        ]);

        $this->assertFalse($info->isAuthRequired());
        $this->assertFalse($info->isPaymentRequired());
    }

    public function testStringAccessorsReturnNullWhenFieldIsNotString(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, [
            'payments_url' => ['http://example.com'],
            'terms_of_service' => null,
        ]);

        $this->assertNull($info->getPaymentsUrl());
        $this->assertNull($info->getTermsOfService());
    }

    public function testFeesIsNullWhenFieldIsNotAnObject(): void
    {
        $this->assertNull(Nip11Info::fromArray($this->relayUrl, ['fees' => 'free'])->getFees());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedNipsThatAreNotAListOfIntegers(): iterable
    {
        yield 'strings and objects' => ['{"supported_nips":["one",{}]}'];
        yield 'a fraction' => ['{"supported_nips":[1.5]}'];
        yield 'an object keyed like a list' => ['{"supported_nips":{"0":1}}'];
    }

    #[DataProvider('supportedNipsThatAreNotAListOfIntegers')]
    public function testSupportedNipsIsNullUnlessAJsonArrayOfIntegers(string $json): void
    {
        $this->assertNull(Nip11Info::tryFromJson($this->relayUrl, $json)?->getSupportedNips());
    }

    public function testLimitationWrittenAsAJsonArrayIsNull(): void
    {
        $this->assertNull(Nip11Info::tryFromJson($this->relayUrl, '{"limitation":[true]}')?->getLimitation());
    }

    public function testLimitationWrittenAsAnEmptyJsonObjectStatesNothing(): void
    {
        $this->assertSame([], Nip11Info::tryFromJson($this->relayUrl, '{"limitation":{}}')?->getLimitation());
    }

    public function testLimitationKeyedLikeAListIsStillAnObject(): void
    {
        $this->assertSame([0 => true], Nip11Info::tryFromJson($this->relayUrl, '{"limitation":{"0":true}}')?->getLimitation());
    }

    public function testFeesWrittenAsAJsonArrayIsNull(): void
    {
        $this->assertNull(Nip11Info::tryFromJson($this->relayUrl, '{"fees":[{"amount":1}]}')?->getFees());
    }
}
