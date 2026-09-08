<?php

namespace App\Exceptions;

use Exception;

/**
 * Wrong email or password at the login endpoint.
 *
 * Day 8 signalled this with ValidationException::withMessages()->status(401),
 * which produced an envelope reading `code: "validation_failed"` alongside HTTP
 * 401. The shape was right and the meaning was wrong: a client keying on `code`
 * — which is the entire point of having one — would treat a rejected sign-in as
 * a malformed form. Corrected on Day 11 to its own code.
 *
 * The message is deliberately identical for an unknown email and a wrong
 * password, so the endpoint cannot be used to enumerate accounts.
 */
class InvalidCredentialsException extends Exception
{
    public function __construct(string $message = 'The provided credentials are invalid.')
    {
        parent::__construct($message);
    }
}
