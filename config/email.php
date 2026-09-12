<?php
declare(strict_types=1);

/** Keep the contact address, but compare consumer Gmail aliases as one inbox. */
function email_identity(string $email): string
{
    $email = mb_strtolower(trim($email));
    $parts = explode('@', $email, 2);
    if (count($parts) === 2 && in_array($parts[1], ['gmail.com', 'googlemail.com'], true)) {
        return str_replace('.', '', explode('+', $parts[0], 2)[0]) . '@gmail.com';
    }
    // Other providers and Workspace custom domains can treat dots/tags differently.
    return $email;
}
