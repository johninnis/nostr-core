<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Enum;

use Innis\Nostr\Core\Domain\Enum\ThreadingConvention;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThreadingConventionTest extends TestCase
{
    #[DataProvider('threadingKinds')]
    public function testOfKindNamesTheConventionAKindThreadsBy(int $kind, ThreadingConvention $convention): void
    {
        $this->assertSame($convention, ThreadingConvention::ofKind(EventKind::fromInt($kind)));
    }

    /**
     * @return iterable<string, array{int, ThreadingConvention}>
     */
    public static function threadingKinds(): iterable
    {
        yield 'a short note' => [EventKind::TEXT_NOTE, ThreadingConvention::ShortNote];
        yield 'a comment' => [EventKind::COMMENT, ThreadingConvention::Comment];
        yield 'a voice message comment' => [EventKind::VOICE_MESSAGE_COMMENT, ThreadingConvention::Comment];
        yield 'a channel message' => [EventKind::CHANNEL_MESSAGE, ThreadingConvention::Channel];
        yield 'a live chat message' => [EventKind::LIVE_CHAT_MESSAGE, ThreadingConvention::LiveChat];
        yield 'a chat message' => [EventKind::CHAT_MESSAGE, ThreadingConvention::Chat];
    }

    public function testNoKindThreadsAsAShortNote(): void
    {
        $this->assertSame(ThreadingConvention::ShortNote, ThreadingConvention::ofKind(null));
    }

    #[DataProvider('kindsThatDoNotThread')]
    public function testAKindNoNipThreadsHasNoConvention(int $kind): void
    {
        $this->assertNull(ThreadingConvention::ofKind(EventKind::fromInt($kind)));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function kindsThatDoNotThread(): iterable
    {
        yield 'a voice message' => [EventKind::VOICE_MESSAGE];
        yield 'a reaction' => [EventKind::REACTION];
        yield 'a long-form article' => [EventKind::LONGFORM_CONTENT];
    }

    public function testAChannelMessageDoesNotAnswerItsRoot(): void
    {
        $this->assertFalse(ThreadingConvention::Channel->answersItsRoot());
    }

    #[DataProvider('conventionsThatAnswerTheirRoot')]
    public function testEveryOtherConventionAnswersItsRoot(ThreadingConvention $convention): void
    {
        $this->assertTrue($convention->answersItsRoot());
    }

    /**
     * @return iterable<string, array{ThreadingConvention}>
     */
    public static function conventionsThatAnswerTheirRoot(): iterable
    {
        yield 'a short note' => [ThreadingConvention::ShortNote];
        yield 'a comment' => [ThreadingConvention::Comment];
        yield 'a live chat message' => [ThreadingConvention::LiveChat];
        yield 'a chat message' => [ThreadingConvention::Chat];
    }
}
