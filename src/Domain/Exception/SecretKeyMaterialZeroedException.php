<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Exception;

final class SecretKeyMaterialZeroedException extends NostrException
{
    public function __construct()
    {
        parent::__construct('Secret key material has been zeroed and cannot be accessed');
    }
}
