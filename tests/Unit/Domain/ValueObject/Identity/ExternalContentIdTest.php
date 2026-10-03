<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Identity\ExternalContentId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExternalContentIdTest extends TestCase
{
    private const string EPISODE = 'podcast:item:guid:d98d189b-dc7b-45b1-8720-d4b98690f31f';
    private const string EPISODE_HINT = 'https://fountain.fm/episode/z1y9TMQRuqXl2awyrQxg';
    private const string EPISODE_KIND = 'podcast:item:guid';

    public function testTryFromStringKeepsTheValueVerbatim(): void
    {
        $id = ExternalContentId::tryFromString('iso3166:US-CA', 'iso3166') ?? throw new RuntimeException('Expected a valid external content id');

        $this->assertSame('iso3166:US-CA', $id->getValue());
    }

    public function testTryFromStringKeepsAUrlHint(): void
    {
        $id = ExternalContentId::tryFromString(self::EPISODE, self::EPISODE_KIND, self::EPISODE_HINT) ?? throw new RuntimeException('Expected a valid external content id');

        $this->assertSame(self::EPISODE_HINT, (string) $id->getHint());
    }

    public function testTryFromStringDropsAHintThatIsNotAUrl(): void
    {
        $id = ExternalContentId::tryFromString(self::EPISODE, self::EPISODE_KIND, 'not a url') ?? throw new RuntimeException('Expected a valid external content id');

        $this->assertNull($id->getHint());
    }

    public function testTryFromStringRefusesAnEmptyValue(): void
    {
        $this->assertNull(ExternalContentId::tryFromString('', 'web'));
    }

    public function testTryFromStringKeepsTheNip73Kind(): void
    {
        $id = ExternalContentId::tryFromString(self::EPISODE, self::EPISODE_KIND) ?? throw new RuntimeException('Expected a valid external content id');

        $this->assertSame(self::EPISODE_KIND, $id->getKind());
    }

    public function testTryFromStringRefusesAnEmptyKind(): void
    {
        $this->assertNull(ExternalContentId::tryFromString(self::EPISODE, ''));
    }

    public function testTryFromStringRefusesAValueThatIsNotUtf8(): void
    {
        $this->assertNull(ExternalContentId::tryFromString("isbn:\xff", 'isbn'));
    }

    public function testTryFromStringRefusesAKindThatIsNotUtf8(): void
    {
        $this->assertNull(ExternalContentId::tryFromString(self::EPISODE, "podcast:\xff"));
    }

    public function testToStringIsTheValue(): void
    {
        $id = ExternalContentId::tryFromString('https://abc.com/articles/1', 'web') ?? throw new RuntimeException('Expected a valid external content id');

        $this->assertSame('https://abc.com/articles/1', (string) $id);
    }

    public function testEqualityIsByValueAndIgnoresTheHint(): void
    {
        $withHint = ExternalContentId::tryFromString(self::EPISODE, self::EPISODE_KIND, self::EPISODE_HINT) ?? throw new RuntimeException('Expected a valid external content id');
        $withoutHint = ExternalContentId::tryFromString(self::EPISODE, self::EPISODE_KIND) ?? throw new RuntimeException('Expected a valid external content id');

        $this->assertTrue($withHint->equals($withoutHint));
    }

    public function testDifferentValuesAreNotEqual(): void
    {
        $book = ExternalContentId::tryFromString('isbn:9780765382030', 'isbn') ?? throw new RuntimeException('Expected a valid external content id');
        $episode = ExternalContentId::tryFromString(self::EPISODE, self::EPISODE_KIND) ?? throw new RuntimeException('Expected a valid external content id');

        $this->assertFalse($book->equals($episode));
    }

    public function testToArrayTryFromArrayRoundTrip(): void
    {
        $id = ExternalContentId::tryFromString(self::EPISODE, self::EPISODE_KIND, self::EPISODE_HINT) ?? throw new RuntimeException('Expected a valid external content id');

        $this->assertSame($id->toArray(), ExternalContentId::tryFromArray($id->toArray())?->toArray());
    }

    public function testTryFromArrayRefusesAValueThatIsNotAnArray(): void
    {
        $this->assertNull(ExternalContentId::tryFromArray('#bitcoin'));
    }

    public function testTryFromArrayRefusesAMissingValue(): void
    {
        $this->assertNull(ExternalContentId::tryFromArray(['hint' => self::EPISODE_HINT]));
    }

    public function testTryFromArrayRefusesANonStringHint(): void
    {
        $this->assertNull(ExternalContentId::tryFromArray(['value' => self::EPISODE, 'kind' => self::EPISODE_KIND, 'hint' => 42]));
    }

    public function testTryFromArrayRefusesAMissingKind(): void
    {
        $this->assertNull(ExternalContentId::tryFromArray(['value' => self::EPISODE]));
    }
}
