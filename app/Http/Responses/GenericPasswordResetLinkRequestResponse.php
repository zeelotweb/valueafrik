<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulContract;

/**
 * Replaces Fortify's default success/failure pair for password.email, which
 * otherwise responds differently depending on whether the address belongs
 * to an account — a "We can't find a user with that email address." form
 * error versus a "We have emailed your password reset link." flash status.
 * That difference lets anyone enumerate registered emails by POSTing
 * candidates to this endpoint, on a platform where having an account here
 * at all can be sensitive. Every outcome (sent, unknown email, or the
 * password broker's own per-email throttle) now renders the identical
 * success-shaped response, so there's nothing left to distinguish.
 */
class GenericPasswordResetLinkRequestResponse implements FailedContract, SuccessfulContract
{
    public function __construct(protected string $status)
    {
    }

    public function toResponse($request)
    {
        $message = __('passwords.sent');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}
