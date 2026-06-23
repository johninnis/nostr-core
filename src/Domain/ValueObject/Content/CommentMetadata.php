<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Enum\CommentScope;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class CommentMetadata
{
    public function __construct(
        private string $rootKind,
        private string $parentKind,
        private CommentScope $rootScope,
    ) {
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

    public static function fromTagCollection(TagCollection $tags): ?self
    {
        $rootKind = $tags->getFirstValueByType(TagType::rootKind());
        if (null === $rootKind) {
            return null;
        }

        $parentKind = $tags->getFirstValueByType(TagType::parentKind());
        if (null === $parentKind) {
            return null;
        }

        $rootScope = self::determineRootScope($tags);
        if (null === $rootScope) {
            return null;
        }

        return new self($rootKind, $parentKind, $rootScope);
    }

    private static function determineRootScope(TagCollection $tags): ?CommentScope
    {
        if ($tags->hasType(TagType::rootEvent())) {
            return CommentScope::Event;
        }

        if ($tags->hasType(TagType::rootAddress())) {
            return CommentScope::Address;
        }

        if ($tags->hasType(TagType::externalIdentity())) {
            return CommentScope::External;
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'root_kind' => $this->rootKind,
            'parent_kind' => $this->parentKind,
            'root_scope' => $this->rootScope->value,
        ];
    }

    public static function fromArray(array $data): ?self
    {
        $rootKind = JsonWireFormat::stringField($data, 'root_kind');
        $parentKind = JsonWireFormat::stringField($data, 'parent_kind');
        $rootScopeValue = JsonWireFormat::stringField($data, 'root_scope');
        if (null === $rootKind || null === $parentKind || null === $rootScopeValue) {
            return null;
        }

        $rootScope = CommentScope::tryFrom($rootScopeValue);
        if (null === $rootScope) {
            return null;
        }

        return new self($rootKind, $parentKind, $rootScope);
    }

    public function equals(self $other): bool
    {
        return $this->rootKind === $other->rootKind
            && $this->parentKind === $other->parentKind
            && $this->rootScope === $other->rootScope;
    }
}
