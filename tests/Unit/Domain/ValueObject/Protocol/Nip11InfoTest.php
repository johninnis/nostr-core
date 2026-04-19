<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip11Info;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use PHPUnit\Framework\TestCase;
use stdClass;

final class Nip11InfoTest extends TestCase
{
    private RelayUrl $relayUrl;

    protected function setUp(): void
    {
        $relayUrl = RelayUrl::fromString('wss://relay.example.com');
        $this->assertNotNull($relayUrl);
        $this->relayUrl = $relayUrl;
    }

    public function testCanCreateWithMinimalData(): void
    {
        $info = new Nip11Info($this->relayUrl);

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

    public function testCanCreateWithAllFields(): void
    {
        $info = new Nip11Info(
            relayUrl: $this->relayUrl,
            name: 'Test Relay',
            description: 'A test relay',
            pubkey: str_repeat('a', 64),
            contact: 'admin@example.com',
            supportedNips: [1, 11, 42],
            software: 'nostr-relay',
            version: '1.0.0',
            banner: 'https://example.com/banner.png',
            icon: 'https://example.com/icon.png',
        );

        $this->assertSame('Test Relay', $info->getName());
        $this->assertSame('A test relay', $info->getDescription());
        $this->assertSame(str_repeat('a', 64), $info->getPubkey());
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
        $this->assertSame(str_repeat('b', 64), $info->getPubkey());
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

    public function testToArrayReturnsFilteredDataWhenCreatedViaConstructor(): void
    {
        $info = new Nip11Info(
            relayUrl: $this->relayUrl,
            name: 'Test Relay',
            description: 'A test relay',
        );

        $array = $info->toArray();

        $this->assertSame('Test Relay', $array['name']);
        $this->assertSame('A test relay', $array['description']);
        $this->assertArrayNotHasKey('pubkey', $array);
        $this->assertArrayNotHasKey('contact', $array);
        $this->assertArrayNotHasKey('supported_nips', $array);
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

    public function testGetRetentionReturnsRetentionData(): void
    {
        $retentionData = [['kinds' => [0, 1], 'time' => 3600]];
        $data = ['retention' => $retentionData];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame($retentionData, $info->getRetention());
    }

    public function testGetRetentionReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getRetention());
    }

    public function testGetRelayCountriesReturnsCountryCodes(): void
    {
        $data = ['relay_countries' => ['GB', 'US', 'DE']];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame(['GB', 'US', 'DE'], $info->getRelayCountries());
    }

    public function testGetRelayCountriesReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getRelayCountries());
    }

    public function testGetLanguageTagsReturnsLanguages(): void
    {
        $data = ['language_tags' => ['en', 'de', 'fr']];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame(['en', 'de', 'fr'], $info->getLanguageTags());
    }

    public function testGetLanguageTagsReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getLanguageTags());
    }

    public function testGetTagsReturnsTags(): void
    {
        $data = ['tags' => ['sfw-only', 'bitcoin']];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame(['sfw-only', 'bitcoin'], $info->getTags());
    }

    public function testGetTagsReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getTags());
    }

    public function testGetPostingPolicyReturnsUrl(): void
    {
        $data = ['posting_policy' => 'https://example.com/policy'];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame('https://example.com/policy', $info->getPostingPolicy());
    }

    public function testGetPostingPolicyReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getPostingPolicy());
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

    public function testGetPrivacyPolicyReturnsUrl(): void
    {
        $data = ['privacy_policy' => 'https://example.com/privacy'];

        $info = Nip11Info::fromArray($this->relayUrl, $data);

        $this->assertSame('https://example.com/privacy', $info->getPrivacyPolicy());
    }

    public function testGetPrivacyPolicyReturnsNullWhenNotPresent(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, []);

        $this->assertNull($info->getPrivacyPolicy());
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
            'posting_policy' => 42,
            'payments_url' => ['http://example.com'],
            'privacy_policy' => true,
            'terms_of_service' => null,
        ]);

        $this->assertNull($info->getPostingPolicy());
        $this->assertNull($info->getPaymentsUrl());
        $this->assertNull($info->getPrivacyPolicy());
        $this->assertNull($info->getTermsOfService());
    }

    public function testArrayAccessorsReturnNullWhenFieldIsNotArray(): void
    {
        $info = Nip11Info::fromArray($this->relayUrl, [
            'retention' => 'unexpected',
            'relay_countries' => 42,
            'language_tags' => true,
            'tags' => null,
            'fees' => 'free',
        ]);

        $this->assertNull($info->getRetention());
        $this->assertNull($info->getRelayCountries());
        $this->assertNull($info->getLanguageTags());
        $this->assertNull($info->getTags());
        $this->assertNull($info->getFees());
    }
}
