<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Enum;

use Innis\Nostr\Core\Domain\Enum\EoseHint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EoseHintTest extends TestCase
{
    #[DataProvider('nip67Hints')]
    public function testEachHintIsTheWordNip67Defines(EoseHint $hint, string $word): void
    {
        $this->assertSame($word, $hint->value);
    }

    /**
     * @return iterable<string, array{EoseHint, string}>
     */
    public static function nip67Hints(): iterable
    {
        yield 'finish' => [EoseHint::Finish, 'finish'];
        yield 'more' => [EoseHint::More, 'more'];
        yield 'auth' => [EoseHint::Auth, 'auth'];
    }
}
