<?php

namespace App\Support;

/** The screenshot cannot be kept. The reason is one of: type, too_large, unreadable, no_room. */
final class FeedbackImageRejected extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('Feedback image rejected: '.$reason);
    }
}
