<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

enum HttpFetchFailure: string
{
    case NotFound = 'not_found';
    case NoAnswer = 'no_answer';
}
