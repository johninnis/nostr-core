<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Enum\CommentScope;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\Service\ReplyChainAnalyser;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\ExternalContentId;
use Innis\Nostr\Core\Domain\ValueObject\Reference\EventReference;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use InvalidArgumentException;

final readonly class CommentMetadata
{
    private function __construct(
        private string $rootKind,
        private string $parentKind,
        private CommentScope $rootScope,
    ) {
    }

    // Deliberate: NIP-22's K and k MUST be present, so an empty kind names none — see ADR-0085
    public static function tryFrom(string $rootKind, string $parentKind, CommentScope $rootScope): ?self
    {
        $isKind = static fn (string $kind): bool => '' !== $kind && mb_check_encoding($kind, 'UTF-8');

        return $isKind($rootKind) && $isKind($parentKind) ? new self($rootKind, $parentKind, $rootScope) : null;
    }

    public static function from(string $rootKind, string $parentKind, CommentScope $rootScope): self
    {
        return self::tryFrom($rootKind, $parentKind, $rootScope)
            ?? throw new InvalidArgumentException('Comment root and parent kinds must be non-empty UTF-8');
    }

    public function getRootKind(): string
    {
        return $this->rootKind;
    }

    public function getParentKind(): string
    {
        return $this->parentKind;
    }

    public function getRootScope(): CommentScope
    {
        return $this->rootScope;
    }

    public static function tryFromTagCollection(TagCollection $tags): ?self
    {
        $rootKind = $tags->getSoleValueByType(TagType::rootKind())->getValue();
        if (null === $rootKind) {
            return null;
        }

        $parentKind = $tags->getSoleValueByType(TagType::parentKind())->getValue();
        if (null === $parentKind) {
            return null;
        }

        $rootScope = self::determineRootScope($tags);
        if (null === $rootScope) {
            return null;
        }

        return self::tryFrom($rootKind, $parentKind, $rootScope);
    }

    // Deliberate: the scope is the comment's one root, read address first, then event, then external content, every tag name as one claim — see ADR-0085
    private static function determineRootScope(TagCollection $tags): ?CommentScope
    {
        $root = ReplyChainAnalyser::analyse($tags, EventKind::fromInt(EventKind::COMMENT))->getRoot();

        return match (true) {
            $root instanceof EventCoordinate => CommentScope::Address,
            $root instanceof EventReference => CommentScope::Event,
            $root instanceof ExternalContentId => CommentScope::External,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'root_kind' => $this->rootKind,
            'parent_kind' => $this->parentKind,
            'root_scope' => $this->rootScope->value,
        ];
    }

    public static function tryFromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $rootKind = JsonWireFormat::stringField($data, 'root_kind');
        $parentKind = JsonWireFormat::stringField($data, 'parent_kind');
        $rootScopeValue = JsonWireFormat::stringField($data, 'root_scope');
        if (null === $rootKind || null === $parentKind || null === $rootScopeValue) {
            return null;
        }

        $rootScope = CommentScope::tryFrom($rootScopeValue);

        return null === $rootScope ? null : self::tryFrom($rootKind, $parentKind, $rootScope);
    }

    public function equals(self $other): bool
    {
        return $this->rootKind === $other->rootKind
            && $this->parentKind === $other->parentKind
            && $this->rootScope === $other->rootScope;
    }
}
