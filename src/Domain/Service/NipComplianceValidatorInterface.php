<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;

interface NipComplianceValidatorInterface
{
    public function validateNip01Compliance(Event $event): void;

    public function validateNip02Compliance(Event $event): void;

    public function validateNip04Compliance(Event $event): void;

    public function validateNip09Compliance(Event $event): void;
}
