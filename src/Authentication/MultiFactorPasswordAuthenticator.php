<?php

namespace Bead\Authentication;

use Bead\Contracts\Authentication\Credentials as CredentialsContract;
use Bead\Contracts\Models\Authenticatable as AuthenticatableContract;
use Bead\Contracts\Models\MultiFactorAuthenticatable as MultiFactorAuthenticatableContract;
use Bead\Contracts\Web\Request as RequestContract;
use Bead\Exceptions\Authentication\AuthenticationException;
use Bead\Exceptions\Authentication\MultiFactorAuthenticationException;
use Bead\Facades\Application;
use Bead\Validation\Validator;

use function Bead\Helpers\I18n\tr;

/** Authenticator that uses username/email and password, and supports MFA if configured for the Authenticatable. */
class MultiFactorPasswordAuthenticator extends AbstractAuthenticator
{
    /** The POST data key for the credentials' username. */
    protected static string $usernameKey = "email";

    /** The POST data key for the credentials' password. */
    protected static string $passwordKey = "password";

    /** The POST data key for the credentials' second-factor method. */
    protected static string $secondFactorMethodKey = "second-factor-method";

    /** The POST data key for the credentials' second-factor password. */
    protected static string $secondFactorPasswordKey = "second-factor-password";

    /** * Fetch the username and password, and optional second factor method and password, from a Request. */
    public function extractCredentials(RequestContract $request): CredentialsContract
    {
        $validator = new Validator(
            $request->formFields([static::$usernameKey, static::$passwordKey, static::$secondFactorMethodKey, static::$secondFactorPasswordKey,]),
            [
                static::$usernameKey => ["string", "filled",],
                static::$passwordKey => ["string", "filled",],
                static::$secondFactorMethodKey => ["optional", "string", "in:totp",],
                static::$secondFactorPasswordKey => ["optional", "string", "length:6",],
            ]
        );

        /** @psalm-suppress MissingThrowsDocblock LogicException won't be thrown here. */
        if (!$validator->passes()) {
            // delay before responding to make brute-force attacks less effective
            usleep(Application::config("app.auth-delay", 2) * 1000000);
            throw new AuthenticationException(tr("Invalid authentication data provided"));
        }

        /** @psalm-suppress MissingThrowsDocblock LogicException won't be thrown here. */
        $validatedInput = $validator->validated();

        return new MultiFactorPasswordCredentials(
            $validatedInput[static::$usernameKey],
            $validatedInput[static::$passwordKey],
            $validatedInput[static::$secondFactorMethodKey] ?? null,
            $validatedInput[static::$secondFactorPasswordKey] ?? null,
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
            throw new AuthenticationException(tr("The email and/or password is not valid"));
        }

        if (!($authenticatable instanceof MultiFactorAuthenticatableContract) || !$authenticatable->hasMultiFactorEnabled()) {
            // verified and no MFA, so all good
            return AuthenticationResult::authenticated($authenticatable);
        }

        if ($credentials instanceof MultiFactorPasswordCredentials && $credentials->hasMultiFactorCredentials()) {
            if ($authenticatable->verifyMultiFactor($credentials)) {
                // correct MFA credentials
                return AuthenticationResult::authenticated($authenticatable);
            }

            // incorrect MFA credentials
            throw new MultiFactorAuthenticationException(tr("The code is not valid, please try again with the next code"));
        }

        // no MFA credentials, so ask for them
        return AuthenticationResult::multiFactorRequired($authenticatable->multiFactorMethods());
    }
}
