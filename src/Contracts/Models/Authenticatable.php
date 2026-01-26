<?php

namespace Bead\Contracts\Models;

use Bead\Contracts\Authentication\Credentials as CredentialsContract;

/**
 * Interface for an entity (probably a Model) that can be authenticated with the app.
 *
 * In most cases you've probably got a User model that authenticates with PasswordCredentials (i.e. username/email and
 * password). But other schemes are possible by implementing this contract, and the Credentials contract.
 */
interface Authenticatable
{
    /**
     * Fetch an Authenticatable instance by its primary key.
     *
     * This enables authenticators to retrieve the signed-in authenticatable based on a primary key stored in session
     * data, for example.
     *
     * @param mixed $id
     * @return Authenticatable|null
     */
    public static function fetch(mixed $id): ?static;

    /**
     * Find an Authenticatable matching the provided credentials.
     *
     * The user need not (yet) be verified by the secret part of the credentials (e.g. the password), just the unique
     * Authenticatable that matches the identifier part of the credentials (e.g. username).
     *
     * @return Authenticatable|null Null if no matching Authenticatable can be found.
     */
    public static function fromCredentials(CredentialsContract $credentials): ?Authenticatable;

    /**
     * Verify that the secret part of the provided credentials matches the authenticatable.
     *
     * For example, does the User's stored password hash match the password in the credentials.
     *
     * @param CredentialsContract $credentials The credentials to verify.
     *
     * @return bool true if the credentials match, false otherwise.
     */
    public function verify(CredentialsContract $credentials): bool;
}
