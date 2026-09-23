<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Enum;

use Innis\Nostr\Core\Domain\Enum\Nip86Method;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Nip86MethodTest extends TestCase
{
    #[DataProvider('specificationNames')]
    public function testEachSpecificationNameResolvesToItsCase(string $name, Nip86Method $expected): void
    {
        $this->assertSame($expected, Nip86Method::tryFrom($name));
    }

    public function testTheEnumCarriesTheSpecificationsMethodsAndNothingElse(): void
    {
        $expected = array_map(
            static fn (array $row): string => $row[0],
            iterator_to_array(self::specificationNames()),
        );

        $this->assertSame(array_values($expected), array_column(Nip86Method::cases(), 'value'));
    }

    #[DataProvider('relaySpecificNames')]
    public function testARelaySpecificNameIsNotASpecificationMethod(string $name): void
    {
        $this->assertNull(Nip86Method::tryFrom($name));
    }

    /**
     * @return iterable<string, array{string, Nip86Method}>
     */
    public static function specificationNames(): iterable
    {
        yield 'supportedmethods' => ['supportedmethods', Nip86Method::SupportedMethods];
        yield 'banpubkey' => ['banpubkey', Nip86Method::BanPubkey];
        yield 'unbanpubkey' => ['unbanpubkey', Nip86Method::UnbanPubkey];
        yield 'listbannedpubkeys' => ['listbannedpubkeys', Nip86Method::ListBannedPubkeys];
        yield 'allowpubkey' => ['allowpubkey', Nip86Method::AllowPubkey];
        yield 'unallowpubkey' => ['unallowpubkey', Nip86Method::UnallowPubkey];
        yield 'listallowedpubkeys' => ['listallowedpubkeys', Nip86Method::ListAllowedPubkeys];
        yield 'createrole' => ['createrole', Nip86Method::CreateRole];
        yield 'editrole' => ['editrole', Nip86Method::EditRole];
        yield 'deleterole' => ['deleterole', Nip86Method::DeleteRole];
        yield 'assignrole' => ['assignrole', Nip86Method::AssignRole];
        yield 'unassignrole' => ['unassignrole', Nip86Method::UnassignRole];
        yield 'listeventsneedingmoderation' => ['listeventsneedingmoderation', Nip86Method::ListEventsNeedingModeration];
        yield 'allowevent' => ['allowevent', Nip86Method::AllowEvent];
        yield 'banevent' => ['banevent', Nip86Method::BanEvent];
        yield 'listbannedevents' => ['listbannedevents', Nip86Method::ListBannedEvents];
        yield 'changerelayname' => ['changerelayname', Nip86Method::ChangeRelayName];
        yield 'changerelaydescription' => ['changerelaydescription', Nip86Method::ChangeRelayDescription];
        yield 'changerelayicon' => ['changerelayicon', Nip86Method::ChangeRelayIcon];
        yield 'allowkind' => ['allowkind', Nip86Method::AllowKind];
        yield 'disallowkind' => ['disallowkind', Nip86Method::DisallowKind];
        yield 'listallowedkinds' => ['listallowedkinds', Nip86Method::ListAllowedKinds];
        yield 'blockip' => ['blockip', Nip86Method::BlockIp];
        yield 'unblockip' => ['unblockip', Nip86Method::UnblockIp];
        yield 'listblockedips' => ['listblockedips', Nip86Method::ListBlockedIps];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function relaySpecificNames(): iterable
    {
        yield 'getguestpolicy' => ['getguestpolicy'];
        yield 'mixed case' => ['BanPubkey'];
        yield 'empty' => [''];
    }
}
