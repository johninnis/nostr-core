<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Exception\SerialisationException;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class JsonWireFormatTest extends TestCase
{
    public function testEncodeReturnsJsonForAValidValue(): void
    {
        $this->assertSame('{"a":1}', JsonWireFormat::encode(['a' => 1], JsonWireFormat::MESSAGE));
    }

    public function testEventEncodingEscapesAControlCharacterNip01DoesNotNameAsALowerCaseUnicodeEscape(): void
    {
        $this->assertSame('["a\\u0001b","\\u001f"]', JsonWireFormat::encode(["a\u{1}b", "\u{1f}"], JsonWireFormat::EVENT));
    }

    public function testEventEncodingWritesTheControlCharactersNip01NamesWithTheirShortEscapes(): void
    {
        $this->assertSame('"\\n\\"\\\\\\r\\t\\b\\f"', JsonWireFormat::encode("\n\"\\\r\t\u{8}\u{c}", JsonWireFormat::EVENT));
    }

    public function testEventEncodingWritesDeleteAndTheLineAndParagraphSeparatorsVerbatim(): void
    {
        $this->assertSame("\"\u{7f}\u{2028}\u{2029}\"", JsonWireFormat::encode("\u{7f}\u{2028}\u{2029}", JsonWireFormat::EVENT));
    }

    public function testMessageEncodingWritesTheLineAndParagraphSeparatorsVerbatim(): void
    {
        $this->assertSame("\"\u{2028}\u{2029}\"", JsonWireFormat::encode("\u{2028}\u{2029}", JsonWireFormat::MESSAGE));
    }

    public function testEncodeThrowsSerialisationExceptionForInvalidUtf8(): void
    {
        $this->expectException(SerialisationException::class);
        $this->expectExceptionMessage('Failed to serialise value to JSON');

        JsonWireFormat::encode("\xb1\x31", JsonWireFormat::EVENT);
    }

    public function testDecodeReturnsNullForJsonNestedBeyondTheDepthLimit(): void
    {
        $depthBomb = str_repeat('[', 600).str_repeat(']', 600);

        $this->assertNull(JsonWireFormat::decode($depthBomb));
    }

    public function testDecodeDecodesJsonWithinTheDepthLimit(): void
    {
        $this->assertSame(['a' => 1], JsonWireFormat::decode('{"a":1}'));
    }

    public function testDecodeRefusesJsonNestedPastItsDepthLimit(): void
    {
        $this->assertNull(JsonWireFormat::decode(str_repeat('[', 600).str_repeat(']', 600)));
    }

    public function testDecodeKeepsAnEmptyJsonObjectApartFromAnEmptyList(): void
    {
        $this->assertEquals([new stdClass(), []], JsonWireFormat::decode('[{},[]]'));
    }

    public function testDecodeKeepsAJsonObjectKeyedLikeAListAsAnObject(): void
    {
        $this->assertInstanceOf(stdClass::class, JsonWireFormat::decode('{"0":"a","1":"b"}'));
    }

    public function testDecodeReadsAnyOtherJsonObjectAsAnArray(): void
    {
        $this->assertSame(['a' => ['b' => 1]], JsonWireFormat::decode('{"a":{"b":1}}'));
    }

    public function testDecodeReturnsNullForMalformedJson(): void
    {
        $this->assertNull(JsonWireFormat::decode('{not json'));
    }

    #[DataProvider('jsonWithAnObjectKeyStartingWithNul')]
    public function testDecodeReturnsNullForAnObjectKeyStartingWithNul(string $json): void
    {
        $this->assertNull(JsonWireFormat::decode($json));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function jsonWithAnObjectKeyStartingWithNul(): iterable
    {
        yield 'top-level object' => ['{"\u0000tags":[]}'];
        yield 'key that is only NUL' => ['{"\u0000":1}'];
        yield 'object inside a message' => ['["EVENT",{"kind":1,"\u0000":1}]'];
    }

    public function testDecodeReadsAnObjectKeyHoldingNulAfterItsFirstCharacter(): void
    {
        $this->assertSame(["a\0b" => 1], JsonWireFormat::decode('{"a\u0000b":1}'));
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    #[DataProvider('jsonObjects')]
    public function testDecodeObjectReadsTheFieldsOfAJsonObject(string $json, array $fields): void
    {
        $this->assertEquals($fields, JsonWireFormat::decodeObject($json));
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>}>
     */
    public static function jsonObjects(): iterable
    {
        yield 'empty object' => ['{}', []];
        yield 'object keyed like a list' => ['{"0":"a","1":"b"}', ['a', 'b']];
        yield 'object' => ['{"a":{"b":1}}', ['a' => ['b' => 1]]];
        yield 'object holding an empty object' => ['{"a":{}}', ['a' => new stdClass()]];
    }

    #[DataProvider('jsonThatIsNoObject')]
    public function testDecodeObjectIsNullForJsonThatIsNoObject(string $json): void
    {
        $this->assertNull(JsonWireFormat::decodeObject($json));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function jsonThatIsNoObject(): iterable
    {
        yield 'empty list' => ['[]'];
        yield 'list' => ['["a"]'];
        yield 'string' => ['"a"'];
        yield 'number' => ['1'];
        yield 'null' => ['null'];
        yield 'malformed' => ['{not json'];
    }

    public function testObjectFieldsReadsADecodedObjectKeyedLikeAList(): void
    {
        $this->assertSame(['a'], JsonWireFormat::objectFields(JsonWireFormat::decode('{"0":"a"}')));
    }

    public function testObjectFieldsIsNullForADecodedList(): void
    {
        $this->assertNull(JsonWireFormat::objectFields(JsonWireFormat::decode('["a"]')));
    }
}
