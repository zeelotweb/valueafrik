<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * The one account allowed to actually be named "valueAFRIK" — the
     * official brand account. Reserved by email rather than an admin flag:
     * email is already unique platform-wide, so whoever legitimately holds
     * this address is definitionally the one account the name belongs to,
     * with no separate "super admin" concept needed.
     */
    private const RESERVED_BRAND_EMAIL = 'valueafrik@yahoo.com';

    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null, ?string $email = null): array
    {
        return [
            'name' => $this->nameRules($email),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * $email is the address this name would belong to (the one being
     * registered, or the account's own current/submitted email on a
     * profile edit) — it's how the brand-name reservation below tells the
     * one legitimate account apart from everyone else trying to name
     * themselves "valueAFRIK".
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(?string $email = null): array
    {
        return [
            'required',
            'string',
            'max:255',
            function (string $attribute, mixed $value, \Closure $fail) use ($email) {
                $normalized = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $value));

                if (str_contains($normalized, 'valueafrik') && mb_strtolower((string) $email) !== self::RESERVED_BRAND_EMAIL) {
                    $fail(__('That name isn\'t available.'));
                }
            },
        ];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
