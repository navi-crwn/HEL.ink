<?php

namespace App\Models;

/**
 * The hub's view of the shared api_tokens table. The model itself lives in
 * hop/auth so every app validates tokens the same way; the hub only adds the
 * user relation to its own User class.
 */
class ApiToken extends \Hop\Auth\Models\ApiToken
{
    protected static ?string $userModel = User::class;
}
