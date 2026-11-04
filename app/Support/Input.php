<?php

namespace App\Support;

/**
 * A request value as text. ?x[]=1 and a JSON object are not text: they count as
 * absent, because (string) on an array raises an error, which would be a 500.
 */
class Input
{
    public static function text(mixed $value): string
    {
        return is_array($value) ? '' : (string) $value;
    }
}
