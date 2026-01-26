<?php

namespace Bead\Contracts\Authentication;

use Bead\Web\Request;
use Bead\Authentication\AuthenticationResult;
use Bead\Contracts\Authentication\Credentials as CredentialsContract;
use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use Bead\Exceptions\Authentication\AuthenticationException;

/** Contract to be implemented by classes providing authentication services for an application. */
interface Authenticator
{
    /**
     * @template T implements Model, Authenticatable
     * Set the model class that the authenticator authenticates.
     *
     * @param class-string<T> $modelClass The model class.
     */
    public function authenticateInstancesOf(string $modelClass): void;

    /**
     * Extract the credentials for an authentication attempt from a Request.
     *
     * @param Request $request The incoming authentication requrest.
     *
     * @return CredentialsContract A set of credentials.
     * @throws AuthenticationException if suitable credentials can't be found in the request.
     */
    public function extractCredentials(Request $request): CredentialsContract;

    /**
     * Identify the Authenticatable that the credentials refer to.
     *
     * @param CredentialsContract $credentials The credentials to check.
     *
     * @return AuthenticatableContract The Authenticatable that the credentials identify.
     * @throws AuthenticationException if no Authenticatable is identified by the credentials.
     */
    public function findAuthenticatable(CredentialsContract $credentials): AuthenticatableContract;

    /**
     * Verify the user-supplied credentials authenticate as the identified Authenticatable.
     *
     * @param CredentialsContract $credentials The user-supplied credentials.
     * @param AuthenticatableContract $authenticatable The identified Authenticatable.
     *
     * @return AuthenticationResult If the authentication succeeds, or could succeed with further credentials (e.g. MFA)
     * @throws AuthenticationException If authentication fails.
     */
    public function verifyCredentials(CredentialsContract $credentials, AuthenticatableContract $authenticatable): AuthenticationResult;

    /**
     * Attempt to authenticate with the app using credentials contained in a Request.
     *
     * @param Request $request The Request with the credentials.
     *
     * @return AuthenticationResult If the authentication succeeds, or could succeed with further credentials (e.g. MFA)
     * @throws AuthenticationException If no usable credentials can be found, no Authenticatable is identified by the
     * credentails, or authentication fails.
     */
    public function authenticate(Request $request): AuthenticationResult;

    /** Fetch the currently authenticated Authenticatable, if there is one. */
    public function currentlyAuthenticated(): ?AuthenticatableContract;

    /**
     * Check whether the currently authenticated Authenticatable's session has been inactive for too long.
     *
     * If the session has been inactive for too long, deauthenticate() is called. Otherwise, the session is touched to
     * keep it active.
     *
     * Throws if called without a currently authenticated Authenticatable.
     */
    public function checkTimeout(): void;

    /** Replace the currently authenticated Authenticatable (if any) with another. */
    public function setCurrentlyAuthenticated(AuthenticatableContract $authenticatable): void;

    /** De-authenticate the currently authenticated Authenticatable, if there is one. */
    public function deauthenticate(): void;
}
