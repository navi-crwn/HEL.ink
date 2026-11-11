<?php

namespace App\Rules;

use App\Support\EmailDomains;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses an address on a throwaway domain. With `$unlessSameAs` (the address the account holds now) an address that is
 * not being changed passes: the rule applies when an address is set, never to an account that already exists.
 */
class AllowedEmailDomain implements ValidationRule
{
    public function __construct(private readonly ?string $unlessSameAs = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }
        if ($this->unlessSameAs !== null && mb_strtolower(trim($value)) === mb_strtolower(trim($this->unlessSameAs))) {
            return;
        }
        if (EmailDomains::blocked($value)) {
            $fail(__('This email address cannot be used. Please use another address.'));
        }
    }
}
