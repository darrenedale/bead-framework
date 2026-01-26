<?php

namespace Bead\Authentication;

use Bead\Contracts\Authentication\Credentials as CredentialsContract;
use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use Bead\Contracts\Web\Request as RequestContract;
use Bead\Exceptions\Authentication\AuthenticationException;
use Bead\Facades\Application;
use Bead\Validation\Validator;

use function Bead\Helpers\I18n\tr;

/** Authenticator that uses username/email and password. */
class PasswordAuthenticator extends AbstractAuthenticator
{
    /** The POST data key for the credentials' username. */
    protected static string $usernameKey = "email";

    /** The POST data key for the credentials' password. */
    protected static string $passwordKey = "password";

    /** * Fetch the username and password, and optional second factor method and password, from a Request. */
    public function extractCredentials(RequestContract $request): CredentialsContract
    {
        $validator = new Validator(
            $request->formFields([static::$usernameKey, static::$passwordKey,]),
            [
                static::$usernameKey => ["string", "filled",],
                static::$passwordKey => ["string", "filled",],
            ]
        );

        if (!$validator->passes()) {
            // delay before responding to make brute-force attacks less effective
            usleep(Application::config("app.auth-delay", 2) * 1000000);
            throw new AuthenticationException(tr("Invalid authentication data provided."));
        }

        $validatedInput = $validator->validated();

        return new PasswordCredentials(
            $validatedInput[static::$usernameKey],
            $validatedInput[static::$passwordKey],
        );
    }

    /** Locate the user whose username matches the credentials, if any. */
    public function findAuthenticatable(CredentialsContract $credentials): AuthenticatableContract
    {
        $authenticatable = ($this->authenticatableClass)::fromCredentials($credentials);

        if (null === $authenticatable) {
            throw new AuthenticationException(tr("The email and/or password is not valid."));
        }

        return $authenticatable;
    }

    /** Verify some credentials authenticate a given user. */
    public function verifyCredentials(CredentialsContract $credentials, AuthenticatableContract $authenticatable): AuthenticationResult
    {
        if (!$authenticatable->verify($credentials)) {
            throw new AuthenticationException(tr("The email and/or password is not valid."));
        }

        return new AuthenticationResult(AuthenticationResultCode::Authenticated, $authenticatable);
    }
}
