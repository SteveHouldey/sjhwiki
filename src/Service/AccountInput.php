<?php
declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;

class AccountInput
{
    /** @return string */
    public static function email(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        $email = strtolower(trim($value));
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        return $email;
    }

    /** @return string */
    public static function username(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/\A[a-zA-Z0-9._-]{3,100}\z/D', $value)) {
            throw new InvalidArgumentException(
                'Usernames must be 3-100 letters, numbers, dots, underscores or hyphens.',
            );
        }

        return $value;
    }

    /** @return string */
    public static function password(mixed $value): string
    {
        if (!is_string($value) || strlen($value) < 12 || strlen($value) > 72 || str_contains($value, "\0")) {
            throw new InvalidArgumentException('Passwords must be between 12 and 72 bytes.');
        }

        return $value;
    }
}
