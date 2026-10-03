<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Application\Port;

use Innis\Nostr\Core\Application\Port\HttpFetchFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpFetchFailureTest extends TestCase
{
    #[DataProvider('caseCodes')]
    public function testValueIsAStableMachineCode(HttpFetchFailure $failure, string $code): void
    {
        $this->assertSame($code, $failure->value);
    }

    /**
     * @return iterable<string, array{HttpFetchFailure, string}>
     */
    public static function caseCodes(): iterable
    {
        yield 'not found' => [HttpFetchFailure::NotFound, 'not_found'];
        yield 'no answer' => [HttpFetchFailure::NoAnswer, 'no_answer'];
    }
}
